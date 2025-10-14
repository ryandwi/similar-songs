<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Throwable;
use App\Services\LastFmService;
use App\Services\SpotifyService;
use App\Services\WikipediaService;
use App\Services\MusicBrainzService;

class CollectSimilarArtistsLastFmCommand extends Command
{
    protected $signature = 'collect:similar-artists-lastfm
        {--artist-ids= : Comma-separated local artist IDs}
        {--spotify-ids= : Comma-separated Spotify artist IDs}
        {--limit=50 : Batasi jumlah artis sumber}
        {--similar-per-artist=20 : Jumlah similar dari Last.fm per artis}
        {--unscraped : Hanya ambil artis dengan similar_artist_scraped = 0}
        {--album-limit=50 : Limit per halaman untuk albums}
        {--album-pages=10 : Maksimum halaman per artist untuk albums}
        {--market=US : Kode market ISO2 untuk filter ketersediaan albums}
        {--skip-musicbrainz : Skip MusicBrainz data fetching}';

    protected $description = 'Ambil similar artists dari Last.fm, match ke Spotify, upsert artists dan simpan pivot, kemudian scrape albums & singles dari artist sumber';

    protected $spotify;

    public function __construct(SpotifyService $spotify)
    {
        parent::__construct();
        $this->spotify = $spotify;
    }

    public function handle(LastFmService $lastfm, WikipediaService $wikipedia, MusicBrainzService $musicbrainz): int
    {
        try {
            $spotifyIds = $this->resolveArtistSpotifyIds();
            if (empty($spotifyIds)) {
                $this->warn('No artists to process.');
                return self::SUCCESS;
            }

            // Randomize array
            shuffle($spotifyIds);

            $limitOpt = $this->option('limit');
            if ($limitOpt !== null) {
                $spotifyIds = array_slice($spotifyIds, 0, (int)$limitOpt);
            }

            $similarLimit = (int)$this->option('similar-per-artist');
            $albumLimit = (int)$this->option('album-limit');
            $albumPages = (int)$this->option('album-pages');
            $market = (string)$this->option('market');
            $fetchDescription = true;
            $skipMusicBrainz = $this->option('skip-musicbrainz');

            $localArtists = DB::table('artists')
                ->whereIn('spotify_id', $spotifyIds)
                ->pluck('id', 'spotify_id');

            $pivotReady = Schema::hasTable('artist_related');
            $albumPivotReady = Schema::hasTable('album_artist');

            $totalUpserted = 0;
            $totalPivots = 0;
            $totalTopTracks = 0;
            $totalAlbumsUpserted = 0;
            $totalAlbumPivots = 0;
            $totalDescriptions = 0;
            $totalMusicBrainzUpdates = 0;
            $processed = 0;
            $skipped = 0;

            $this->info("╔════════════════════════════════════════════════════════════════╗");
            $this->info("║  Similar Artists + Albums Scraper                             ║");
            $this->info("╚════════════════════════════════════════════════════════════════╝");
            $this->info("Total artists to process: " . count($spotifyIds));
            $this->newLine();

            foreach ($spotifyIds as $index => $sid) {
                $current = $index + 1;
                $total = count($spotifyIds);

                $this->info("╔════════════════════════════════════════════════════════════════╗");
                $this->info("║  [{$current}/{$total}] Processing Artist                                  ║");
                $this->info("╚════════════════════════════════════════════════════════════════╝");

                $artist = DB::table('artists')->where('spotify_id', $sid)->first([
                    'id',
                    'name',
                    'spotify_id',
                    'description',
                    'facebook_url',
                    'twitter_url',
                    'instagram_url',
                    'youtube_url',
                    'soundcloud_url',
                    'myspace_url',
                    'bandcamp_url',
                    'tiktok_url',
                    'discogs_url',
                    'born_date',
                    'born_in',
                    'gender',
                    'country'
                ]);

                if (!$artist) {
                    $this->warn("⚠️  Artist not found in DB");
                    $this->warn("   Spotify ID: {$sid}");
                    $this->newLine();
                    $skipped++;
                    continue;
                }

                $this->info("🎵 Artist: {$artist->name}");
                $this->info("   Spotify ID: {$sid}");
                $this->newLine();

                try {
                    $stepNum = 1;

                    // Step 3: Fetch similar artists from Last.fm
                    $this->line("📡 <fg=cyan>Step {$stepNum}:</> Fetching similar artists from Last.fm...");
                    // $lastfmSimilar = $lastfm->getSimilarArtists($artist->name, $similarLimit);
                    $lastfmSimilar = $lastfm->getExtendedSimilarArtists($artist->name, 50, 10, 0.03);

                    if (empty($lastfmSimilar)) {
                        $this->warn("   ⚠️  No similar artists found");
                        $this->newLine();
                        $skipped++;
                        continue;
                    }

                    $this->line("   <fg=green>✓</> Found " . count($lastfmSimilar) . " similar artists");
                    $this->newLine();
                    $stepNum++;

                    // Step 4: Match to Spotify
                    $this->line("🔍 <fg=cyan>Step {$stepNum}:</> Matching to Spotify...");
                    $spotifySimilar = $this->spotify->matchLastFmToSpotify($lastfmSimilar);

                    if (empty($spotifySimilar)) {
                        $this->warn("   ⚠️  No Spotify matches found");
                        $this->newLine();
                        $skipped++;
                        continue;
                    }

                    $this->line("   <fg=green>✓</> Matched " . count($spotifySimilar) . " artists");
                    $this->newLine();
                    $stepNum++;

                    // Step 5: Upsert similar artists
                    $this->line("💾 <fg=cyan>Step {$stepNum}:</> Upserting similar artists to database...");
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
                            ['name', 'image_url', 'popularity', 'followers', 'genres', 'spotify_url', 'updated_at']
                        );
                        $totalUpserted += count($rows);
                        $this->line("   <fg=green>✓</> Upserted " . count($rows) . " artists");
                    }
                    $this->newLine();
                    $stepNum++;

                    // Step 6: Create artist relationships
                    if ($pivotReady) {
                        $this->line("🔗 <fg=cyan>Step {$stepNum}:</> Creating artist relationships...");
                        $sourceLocalId = $localArtists[$sid] ?? null;
                        if ($sourceLocalId) {
                            $similarSpotifyIds = array_values(array_filter(array_map(fn($a) => $a['id'] ?? null, $spotifySimilar)));
                            if (!empty($similarSpotifyIds)) {
                                sleep(1);
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
                                    $this->line("   <fg=green>✓</> Created " . count($pivotRows) . " relationships");
                                }
                            }
                        }
                        $this->newLine();
                        $stepNum++;
                    }

                    // Step 7: Update scraped flag
                    DB::table('artists')
                        ->where('spotify_id', $sid)
                        ->update(['similar_artist_scraped' => 1]);

                    // Step 8: Fetch top tracks
                    $this->line("🎵 <fg=cyan>Step {$stepNum}:</> Fetching top tracks for source artist...");
                    try {
                        $tracks = $this->spotify->getArtistTopTracks($sid, $market);

                        if (!empty($tracks)) {
                            $now = now();
                            $songRows = [];

                            foreach ($tracks as $t) {
                                $album = $t['album'] ?? [];
                                $releaseRaw = $album['release_date'] ?? null;
                                $release = null;
                                if ($releaseRaw) {
                                    $release = strlen($releaseRaw) === 4 ? $releaseRaw . '-01-01'
                                        : (strlen($releaseRaw) === 7 ? $releaseRaw . '-01' : $releaseRaw);
                                }

                                $primaryArtistSpotifyId = data_get($t, 'artists.0.id');
                                $trackArtistId = $primaryArtistSpotifyId ? ($localArtists[$primaryArtistSpotifyId] ?? $localArtists[$sid]) : $localArtists[$sid];

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
                                    'danceability'    => 0,
                                    'energy'          => 0,
                                    'speechiness'     => 0,
                                    'acousticness'    => 0,
                                    'instrumentalness' => 0,
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
                                        'title',
                                        'artist_id',
                                        'genre_id',
                                        'album_name',
                                        'album_image_url',
                                        'release_date',
                                        'duration_ms',
                                        'popularity',
                                        'preview_url',
                                        'spotify_url',
                                        'isrc',
                                        'explicit',
                                        'last_synced_at',
                                        'updated_at'
                                    ]
                                );
                                $totalTopTracks += count($songRows);
                                $this->line("   <fg=green>✓</> Upserted " . count($songRows) . " top tracks");
                            }

                            // Insert pivot artist_top_tracks
                            if (Schema::hasTable('artist_top_tracks')) {
                                $trackSpotifyIds = array_values(array_filter(array_map(fn($t) => $t['id'] ?? null, $tracks)));
                                sleep(1);
                                $songIds = DB::table('songs')
                                    ->whereIn('spotify_id', $trackSpotifyIds)
                                    ->pluck('id', 'spotify_id');

                                $pivotRows = [];
                                $artistLocalId = $localArtists[$sid];

                                foreach ($tracks as $rank => $t) {
                                    $tid = $t['id'] ?? null;
                                    $songId = $tid ? ($songIds[$tid] ?? null) : null;

                                    if ($songId) {
                                        $pivotRows[] = [
                                            'artist_id' => $artistLocalId,
                                            'song_id' => $songId,
                                            'rank' => $rank + 1,
                                            'market' => $market,
                                            'synced_at' => $now,
                                        ];
                                    }
                                }

                                if (!empty($pivotRows)) {
                                    DB::table('artist_top_tracks')
                                        ->where('artist_id', $artistLocalId)
                                        ->where('market', $market)
                                        ->delete();

                                    DB::table('artist_top_tracks')->insert($pivotRows);
                                    $this->line("   <fg=green>✓</> Created " . count($pivotRows) . " top track pivots");
                                }
                            }
                        } else {
                            $this->line("   → No top tracks found");
                        }
                        $this->newLine();
                        $stepNum++;
                    } catch (Throwable $e) {
                        $this->warn("   ⚠️  Failed to fetch top tracks: " . $e->getMessage());
                        $this->newLine();
                        $stepNum++;
                    }

                    // Step 9: Scrape albums
                    $this->line("💿 <fg=cyan>Step {$stepNum}:</> Scraping albums for source artist...");
                    $albumsScraped = $this->scrapeArtistAlbums(
                        [$sid],
                        $albumLimit,
                        $albumPages,
                        $market,
                        $albumPivotReady,
                        $totalAlbumPivots
                    );
                    $totalAlbumsUpserted += $albumsScraped;

                    $this->line("   <fg=green>✓</> Scraped {$albumsScraped} albums");
                    $this->newLine();

                    $this->info("✅ Successfully processed: {$artist->name}");
                    $processed++;

                    $this->newLine();
                    $this->line("────────────────────────────────────────────────────────────────");
                    $this->newLine();

                    sleep(1);
                } catch (Throwable $e) {
                    $this->error("❌ Error processing: {$artist->name}");
                    $this->error("   Message: " . $e->getMessage());
                    $this->newLine();
                    $skipped++;
                    continue;
                }
            }

            // Summary
            $this->newLine();
            $this->info("╔════════════════════════════════════════════════════════════════╗");
            $this->info("║  Summary                                                       ║");
            $this->info("╚════════════════════════════════════════════════════════════════╝");

            $summaryData = [
                ['Artists Processed', $processed],
                ['Artists Skipped', $skipped],
                ['Similar Artists Upserted', $totalUpserted],
                ['Artist Relations Created', $pivotReady ? $totalPivots : 'N/A'],
                ['Top Tracks Upserted', $totalTopTracks],
                ['Albums Upserted', $totalAlbumsUpserted],
                ['Album-Artist Pivots', $albumPivotReady ? $totalAlbumPivots : 'N/A'],
            ];

            if ($fetchDescription) {
                $summaryData[] = ['Descriptions Fetched', $totalDescriptions];
            }

            if (!$skipMusicBrainz) {
                $summaryData[] = ['MusicBrainz Updates', $totalMusicBrainzUpdates];
            }

            $this->table(['Metric', 'Count'], $summaryData);

            // Database verification
            $this->newLine();
            $this->info("╔════════════════════════════════════════════════════════════════╗");
            $this->info("║  Database Verification                                        ║");
            $this->info("╚════════════════════════════════════════════════════════════════╝");

            $albumCount = DB::table('albums')->count();
            $pivotCount = DB::table('album_artist')->count();
            $songCount = DB::table('songs')->count();
            $descriptionCount = DB::table('artists')->whereNotNull('description')->count();
            $socialCount = DB::table('artists')->whereNotNull('facebook_url')->count();

            $this->info("📊 Total albums in DB: {$albumCount}");
            $this->info("🎵 Total songs in DB: {$songCount}");
            $this->info("🔗 Total album_artist pivots: {$pivotCount}");
            $this->info("📖 Total artists with descriptions: {$descriptionCount}");
            $this->info("🌐 Total artists with social URLs: {$socialCount}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    protected function scrapeArtistAlbums(
        array $artistSpotifyIds,
        int $limit,
        int $maxPages,
        string $market,
        bool $pivotReady,
        int &$totalPivots
    ): int {
        $totalAlbums = 0;
        $includeGroups = 'album,single';

        $localArtists = DB::table('artists')
            ->whereIn('spotify_id', $artistSpotifyIds)
            ->pluck('id', 'spotify_id');

        if ($localArtists->count() === 0) {
            $this->warn("   ⚠️  No artists found in DB");
            return 0;
        }

        foreach ($artistSpotifyIds as $artistSpotifyId) {
            try {
                $artistName = DB::table('artists')
                    ->where('spotify_id', $artistSpotifyId)
                    ->value('name') ?? $artistSpotifyId;

                $this->line("   → Artist: {$artistName}");
                $this->line("   → Spotify ID: {$artistSpotifyId}");

                $offset = 0;
                $page = 1;
                $allAlbumIds = [];

                while ($page <= $maxPages) {
                    $payload = $this->spotify->getArtistAlbums($artistSpotifyId, $limit, $offset, $market, $includeGroups);
                    $items = $payload['items'] ?? [];

                    if (empty($items)) break;

                    $albumIds = array_values(array_filter(array_map(fn($a) => $a['id'] ?? null, $items)));
                    if (!empty($albumIds)) {
                        $allAlbumIds = array_merge($allAlbumIds, $albumIds);
                    }

                    $offset += $limit;
                    $page++;

                    if (empty($payload['next'])) break;
                }

                $allAlbumIds = array_values(array_unique($allAlbumIds));

                if (empty($allAlbumIds)) {
                    $this->line("   → No albums found");
                    continue;
                }

                $this->line("   → Found " . count($allAlbumIds) . " album IDs");

                $albums = $this->spotify->getSeveralAlbums($allAlbumIds);

                if (empty($albums)) {
                    $this->warn("   ⚠️  Failed to enrich albums");
                    continue;
                }

                $now = now();
                $rows = [];
                foreach ($albums as $al) {
                    $images = collect($al['images'] ?? [])->sortByDesc('width');
                    $rows[] = [
                        'spotify_id'               => $al['id'] ?? null,
                        'name'                     => $al['name'] ?? null,
                        'album_type'               => $al['album_type'] ?? null,
                        'release_date'             => $al['release_date'] ?? null,
                        'release_date_precision'   => $al['release_date_precision'] ?? null,
                        'total_tracks'             => (int)($al['total_tracks'] ?? 0),
                        'label'                    => $al['label'] ?? null,
                        'image_url'                => $images->value('url'),
                        'images'                   => !empty($al['images']) ? json_encode($al['images']) : null,
                        'spotify_url'              => Arr::get($al, 'external_urls.spotify'),
                        'uri'                      => $al['uri'] ?? null,
                        'available_markets'        => !empty($al['available_markets']) ? json_encode($al['available_markets']) : null,
                        'created_at'               => $now,
                        'updated_at'               => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('albums')->upsert(
                        $rows,
                        ['spotify_id'],
                        [
                            'name',
                            'album_type',
                            'release_date',
                            'release_date_precision',
                            'total_tracks',
                            'label',
                            'image_url',
                            'images',
                            'spotify_url',
                            'uri',
                            'available_markets',
                            'updated_at'
                        ]
                    );
                    $totalAlbums += count($rows);
                    $this->line("   → Upserted " . count($rows) . " albums");
                }

                if ($pivotReady) {
                    sleep(1);

                    $albumLocal = DB::table('albums')
                        ->whereIn('spotify_id', $allAlbumIds)
                        ->pluck('id', 'spotify_id');

                    $artistLocalId = $localArtists[$artistSpotifyId] ?? null;

                    if (!$artistLocalId) {
                        $this->warn("   ⚠️  Artist local ID not found");
                        continue;
                    }

                    $pivotRows = [];
                    foreach ($allAlbumIds as $aid) {
                        $albumId = $albumLocal[$aid] ?? null;
                        if ($albumId) {
                            $pivotRows[] = [
                                'album_id' => $albumId,
                                'artist_id' => $artistLocalId
                            ];
                        }
                    }

                    if (!empty($pivotRows)) {
                        foreach (array_chunk($pivotRows, 1000) as $chunk) {
                            DB::table('album_artist')->insertOrIgnore($chunk);
                        }
                        $totalPivots += count($pivotRows);
                        $this->line("   → Created " . count($pivotRows) . " album-artist pivots");
                    }
                }
            } catch (Throwable $e) {
                $this->error("   ✗ Error: " . $e->getMessage());
                continue;
            }
        }

        return $totalAlbums;
    }

    protected function resolveArtistSpotifyIds(): array
    {
        $idsLocal = $this->option('artist-ids');
        $idsSpotify = $this->option('spotify-ids');
        $unscrapedOnly = $this->option('unscraped');

        if ($idsSpotify) {
            return array_values(array_unique(array_filter(array_map('trim', explode(',', $idsSpotify)))));
        }

        if ($idsLocal) {
            $arr = array_filter(array_map('intval', explode(',', $idsLocal)));
            if (empty($arr)) return [];

            $query = DB::table('artists')->whereIn('id', $arr);

            if ($unscrapedOnly) {
                $query->where('similar_artist_scraped', 0);
            }

            return $query->pluck('spotify_id')->filter()->unique()->values()->all();
        }

        $query = DB::table('artists');

        if ($unscrapedOnly) {
            $query->where('similar_artist_scraped', 0);
        }

        return $query->pluck('spotify_id')->filter()->unique()->values()->all();
    }
}
