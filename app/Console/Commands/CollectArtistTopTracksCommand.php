<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use App\Services\SpotifyService;

class CollectArtistTopTracksCommand extends Command
{
    protected $signature = 'collect:artist-top-tracks
        {--artist-ids= : Comma-separated local artist IDs}
        {--spotify-ids= : Comma-separated Spotify artist IDs}
        {--limit= : Batasi jumlah artis yang diproses}
        {--market=US : Market code untuk top tracks (US, ID, etc)}
        {--force-refresh : Refresh top tracks walaupun sudah ada}';

    protected $description = 'Ambil top tracks per artis dari Spotify, insert ke songs, dan simpan relasi ke artist_top_tracks';

    public function handle(SpotifyService $spotify): int
    {
        try {
            $spotifyIds = $this->resolveArtistSpotifyIds();
            if (empty($spotifyIds)) {
                $this->warn('No artists to process.');
                return self::SUCCESS;
            }

            $limitOpt = $this->option('limit');
            if ($limitOpt !== null) {
                $spotifyIds = array_slice($spotifyIds, 0, (int)$limitOpt);
            }

            $market = strtoupper((string)$this->option('market'));
            $forceRefresh = $this->option('force-refresh');

            // Map spotify_id -> local artist id
            $localArtists = DB::table('artists')
                ->whereIn('spotify_id', $spotifyIds)
                ->pluck('id', 'spotify_id');

            $pivotReady = Schema::hasTable('artist_top_tracks');

            $totalSongs = 0;
            $totalPivots = 0;
            $processed = 0;
            $skipped = 0;

            foreach ($spotifyIds as $sid) {
                $artistLocalId = $localArtists[$sid] ?? null;
                if (!$artistLocalId) {
                    $this->warn("Artist {$sid} not found in DB, skipping.");
                    $skipped++;
                    continue;
                }

                $this->info("Processing top tracks for artist {$sid} (ID: {$artistLocalId})...");

                // Skip jika sudah ada dan tidak force refresh
                if ($pivotReady && !$forceRefresh) {
                    $existing = DB::table('artist_top_tracks')
                        ->where('artist_id', $artistLocalId)
                        ->where('market', $market)
                        ->exists();
                    
                    if ($existing) {
                        $this->line("Top tracks already exist for {$sid} in market {$market}, skipping (use --force-refresh to update).");
                        $skipped++;
                        continue;
                    }
                }

                try {
                    // 1. Ambil top tracks dari Spotify (max 10)
                    $tracks = $spotify->getArtistTopTracks($sid, $market);

                    if (empty($tracks)) {
                        $this->warn("No top tracks for {$sid} in market {$market}.");
                        $skipped++;
                        continue;
                    }

                    // 2. Upsert tracks ke tabel songs (tanpa audio features karena deprecated)
                    $now = now();
                    $songRows = [];
                    
                    foreach ($tracks as $t) {
                        $album = $t['album'] ?? [];
                        $releaseRaw = $album['release_date'] ?? null;
                        $release = null;
                        if ($releaseRaw) {
                            $release = strlen($releaseRaw) === 4 ? $releaseRaw.'-01-01'
                                     : (strlen($releaseRaw) === 7 ? $releaseRaw.'-01' : $releaseRaw);
                        }

                        $primaryArtistSpotifyId = data_get($t, 'artists.0.id');
                        $trackArtistId = $primaryArtistSpotifyId ? ($localArtists[$primaryArtistSpotifyId] ?? $artistLocalId) : $artistLocalId;

                        // Genre: ambil dari pivot artist_genre jika tersedia
                        $genreId = null;
                        if (Schema::hasTable('artist_genre')) {
                            $pivot = DB::table('artist_genre')->where('artist_id', $trackArtistId)->first(['genre_id']);
                            $genreId = $pivot?->genre_id;
                        }

                        $songRows[] = [
                            'spotify_id'      => $t['id'] ?? null,
                            'title'           => $t['name'] ?? null,
                            'artist_id'       => $trackArtistId,
                            'genre_id'        => $genreId,
                            'album_name'      => $album['name'] ?? null,
                            'album_image_url' => collect($album['images'] ?? [])->sortByDesc('width')->value('url'),
                            'release_date'    => $release,
                            'duration_ms'     => (int)($t['duration_ms'] ?? 0),
                            'popularity'      => (int)($t['popularity'] ?? 0),
                            'preview_url'     => $t['preview_url'] ?? null,
                            'spotify_url'     => data_get($t, 'external_urls.spotify'),
                            'isrc'            => data_get($t, 'external_ids.isrc'),
                            
                            // Audio features deprecated - set default 0
                            'danceability'    => 0,
                            'energy'          => 0,
                            'speechiness'     => 0,
                            'acousticness'    => 0,
                            'instrumentalness'=> 0,
                            'liveness'        => 0,
                            'valence'         => 0,
                            'loudness'        => 0,
                            'tempo'           => 0,
                            'key'             => null,
                            'mode'            => null,
                            'time_signature'  => 4,
                            'key_signature'   => null,
                            'bpm'             => null,
                            'explicit'        => (int)($t['explicit'] ?? 0),
                            'last_synced_at'  => $now,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];
                    }

                    if (!empty($songRows)) {
                        DB::table('songs')->upsert(
                            $songRows,
                            ['spotify_id'],
                            [
                                'title','artist_id','genre_id','album_name','album_image_url','release_date',
                                'duration_ms','popularity','preview_url','spotify_url','isrc',
                                'explicit','last_synced_at','updated_at'
                            ]
                        );
                        $totalSongs += count($songRows);
                    }

                    // 3. Insert/update pivot artist_top_tracks
                    if ($pivotReady) {
                        // Hapus top tracks lama untuk artist+market ini jika force refresh
                        if ($forceRefresh) {
                            DB::table('artist_top_tracks')
                                ->where('artist_id', $artistLocalId)
                                ->where('market', $market)
                                ->delete();
                        }

                        // Ambil song IDs yang baru di-upsert
                        $trackSpotifyIds = array_values(array_filter(array_map(fn($t) => $t['id'] ?? null, $tracks)));
                        $songIds = DB::table('songs')
                            ->whereIn('spotify_id', $trackSpotifyIds)
                            ->pluck('id', 'spotify_id');

                        $pivotRows = [];
                        foreach ($tracks as $rank => $t) {
                            $tid = $t['id'] ?? null;
                            $songId = $tid ? ($songIds[$tid] ?? null) : null;
                            
                            if ($songId) {
                                $pivotRows[] = [
                                    'artist_id' => $artistLocalId,
                                    'song_id' => $songId,
                                    'rank' => $rank + 1, // 1-based ranking
                                    'market' => $market,
                                    'synced_at' => $now,
                                ];
                            }
                        }

                        if (!empty($pivotRows)) {
                            DB::table('artist_top_tracks')->insert($pivotRows);
                            $totalPivots += count($pivotRows);
                        }
                    }

                    $this->line("Done {$sid}: upserted ".count($songRows)." tracks, pivot: ".count($pivotRows ?? []));
                    $processed++;

                    usleep(300000); // 300ms delay

                } catch (Throwable $e) {
                    $this->error("Error processing {$sid}: " . $e->getMessage());
                    $skipped++;
                    continue;
                }
            }

            $this->info("=== Summary ===");
            $this->info("Processed: {$processed}");
            $this->info("Skipped: {$skipped}");
            $this->info("Total songs upserted: {$totalSongs}");
            if ($pivotReady) {
                $this->info("Pivot rows: {$totalPivots}");
            }

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    protected function resolveArtistSpotifyIds(): array
    {
        $idsLocal = $this->option('artist-ids');
        $idsSpotify = $this->option('spotify-ids');

        if ($idsSpotify) {
            return array_values(array_unique(array_filter(array_map('trim', explode(',', $idsSpotify)))));
        }

        if ($idsLocal) {
            $arr = array_filter(array_map('intval', explode(',', $idsLocal)));
            if (empty($arr)) return [];
            return DB::table('artists')->whereIn('id', $arr)->pluck('spotify_id')->filter()->unique()->values()->all();
        }

        return DB::table('artists')->pluck('spotify_id')->filter()->unique()->values()->all();
    }
}
