<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use App\Services\LastFmService;
use App\Services\SpotifyService;

class CollectSimilarArtistsLastFmCommand extends Command
{
    protected $signature = 'collect:similar-artists-lastfm
        {--artist-ids= : Comma-separated local artist IDs}
        {--spotify-ids= : Comma-separated Spotify artist IDs}
        {--limit= : Batasi jumlah artis sumber}
        {--similar-per-artist=20 : Jumlah similar dari Last.fm per artis}';

    protected $description = 'Ambil similar artists dari Last.fm, match ke Spotify, upsert artists dan simpan pivot';

    public function handle(LastFmService $lastfm, SpotifyService $spotify): int
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

            $similarLimit = (int)$this->option('similar-per-artist');

            $localArtists = DB::table('artists')
                ->whereIn('spotify_id', $spotifyIds)
                ->pluck('id', 'spotify_id');

            $pivotReady = Schema::hasTable('artist_related');

            $totalUpserted = 0;
            $totalPivots = 0;
            $processed = 0;
            $skipped = 0;

            foreach ($spotifyIds as $sid) {
                // Ambil nama artis dari DB
                $artist = DB::table('artists')->where('spotify_id', $sid)->first(['name','spotify_id']);
                if (!$artist) {
                    $this->warn("Artist {$sid} not found in DB.");
                    $skipped++;
                    continue;
                }

                $this->info("Processing similar for '{$artist->name}' ({$sid})...");

                try {
                    // 1. Ambil similar dari Last.fm by name
                    $lastfmSimilar = $lastfm->getSimilarArtists($artist->name, $similarLimit);

                    if (empty($lastfmSimilar)) {
                        $this->warn("No similar from Last.fm for '{$artist->name}'.");
                        $skipped++;
                        continue;
                    }

                    $this->line("Found ".count($lastfmSimilar)." similar from Last.fm.");

                    // 2. Match ke Spotify dan ambil detail lengkap
                    $spotifySimilar = $spotify->matchLastFmToSpotify($lastfmSimilar);

                    if (empty($spotifySimilar)) {
                        $this->warn("No Spotify matches for '{$artist->name}'.");
                        $skipped++;
                        continue;
                    }

                    $this->line("Matched ".count($spotifySimilar)." to Spotify.");

                    // 3. Upsert similar artists ke tabel artists
                    $now = now();
                    $rows = [];
                    foreach ($spotifySimilar as $a) {
                        $rows[] = [
                            'spotify_id'  => $a['id'] ?? null,
                            'name'        => $a['name'] ?? null,
                            'image_url'   => collect($a['images'] ?? [])->sortByDesc('width')->value('url'),
                            'popularity'  => $a['popularity'] ?? 0,
                            'followers'   => data_get($a, 'followers.total', 0),
                            'genres'      => json_encode($a['genres'] ?? []),
                            'spotify_url' => data_get($a, 'external_urls.spotify'),
                            'created_at'  => $now,
                            'updated_at'  => $now,
                        ];
                    }

                    if (!empty($rows)) {
                        DB::table('artists')->upsert(
                            $rows,
                            ['spotify_id'],
                            ['name','image_url','popularity','followers','genres','spotify_url','updated_at']
                        );
                        $totalUpserted += count($rows);
                    }

                    // 4. Isi pivot artist_related
                    if ($pivotReady) {
                        $sourceLocalId = $localArtists[$sid] ?? null;
                        if ($sourceLocalId) {
                            $similarSpotifyIds = array_values(array_filter(array_map(fn($a) => $a['id'] ?? null, $spotifySimilar)));
                            if (!empty($similarSpotifyIds)) {
                                $similarLocal = DB::table('artists')
                                    ->whereIn('spotify_id', $similarSpotifyIds)
                                    ->pluck('id', 'spotify_id');

                                $pivotRows = [];
                                foreach ($similarSpotifyIds as $rid) {
                                    $relLocalId = $similarLocal[$rid] ?? null;
                                    if ($relLocalId && $relLocalId !== $sourceLocalId) {
                                        $pivotRows[] = [
                                            'artist_id' => $sourceLocalId,
                                            'related_artist_id' => $relLocalId,
                                        ];
                                    }
                                }

                                if (!empty($pivotRows)) {
                                    foreach (array_chunk($pivotRows, 1000) as $chunk) {
                                        DB::table('artist_related')->insertOrIgnore($chunk);
                                        $totalPivots += count($chunk);
                                    }
                                }
                            }
                        }
                    }

                    $this->line("Done '{$artist->name}': ".count($spotifySimilar)." similar artists.");
                    $processed++;

                    // Delay antar artis untuk menghindari rate limit
                    sleep(1); // 1 detik (Last.fm + Spotify calls)

                } catch (Throwable $e) {
                    $this->error("Error for '{$artist->name}': " . $e->getMessage());
                    $skipped++;
                    continue;
                }
            }

            $this->info("=== Summary ===");
            $this->info("Processed: {$processed}");
            $this->info("Skipped: {$skipped}");
            $this->info("Artists upserted: {$totalUpserted}");
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
