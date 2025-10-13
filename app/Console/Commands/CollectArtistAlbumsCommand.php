<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;

class CollectArtistAlbumsCommand extends Command
{
    protected $signature = 'collect:artist-albums
        {--artist-ids= : Comma-separated local artist IDs (tabel artists.id)}
        {--spotify-ids= : Comma-separated Spotify artist IDs}
        {--limit=50 : Per halaman untuk /artists/{id}/albums (max 50)}
        {--pages=10 : Maksimum halaman per artist}
        {--market=US : Kode market ISO2 untuk filter ketersediaan}
        {--include-groups=album,single,appears_on,compilation : Jenis album yang diambil}';

    protected $description = 'Ambil semua albums per artist dari Spotify berdasarkan data artists di DB dan simpan ke tabel albums beserta pivot album_artist';

    // Inject dependency via service container di framework sebenarnya.
    // Di sini diasumsikan ada SpotifyService dengan metode:
    // - getArtistAlbums(artistSpotifyId, $limit, $offset, $market, $includeGroups): array
    // - getSeveralAlbums(array $ids): array
    protected $spotify;

    public function __construct(\App\Services\SpotifyService $spotify)
    {
        parent::__construct();
        $this->spotify = $spotify;
    }

    public function handle(): int
    {
        try {
            $limit = (int)$this->option('limit');
            $pages = (int)$this->option('pages');
            $market = (string)$this->option('market');
            $include = (string)$this->option('include-groups');

            // 1) Tentukan daftar artist Spotify IDs
            $spotifyIds = $this->resolveArtistSpotifyIds();
            if (empty($spotifyIds)) {
                $this->warn('No artists to process.');
                return self::SUCCESS;
            }

            // Map spotify_id -> local artist id
            $localArtists = DB::table('artists')
                ->whereIn('spotify_id', $spotifyIds)
                ->pluck('id', 'spotify_id');

            $totalAlbumsUpserted = 0;
            $pivotPrepared = Schema::hasTable('album_artist');

            foreach ($spotifyIds as $artistSpotifyId) {
                $this->info("Processing artist: {$artistSpotifyId}");

                $offset = 0;
                $page = 1;
                $allAlbumIds = [];

                // 2) Paginate artist albums
                while ($page <= $pages) {
                    $payload = $this->spotify->getArtistAlbums($artistSpotifyId, $limit, $offset, $market, $include);
                    $items = $payload['items'] ?? [];
                    if (empty($items)) {
                        $this->line("No more albums at page {$page}.");
                        break;
                    }

                    // Kumpulkan spotify album ids
                    $albumIds = array_values(array_filter(array_map(fn($a) => $a['id'] ?? null, $items)));
                    if (!empty($albumIds)) {
                        $allAlbumIds = array_merge($allAlbumIds, $albumIds);
                    }

                    $offset += $limit;
                    $page++;

                    if (empty($payload['next'])) {
                        break;
                    }
                }

                $allAlbumIds = array_values(array_unique($allAlbumIds));
                if (empty($allAlbumIds)) {
                    $this->line("No albums found for artist {$artistSpotifyId}.");
                    continue;
                }

                // 3) Enrich detail albums agar field lengkap (images, total_tracks, label, markets, dll.)
                $albums = $this->spotify->getSeveralAlbums($allAlbumIds);

                // 4) Upsert albums
                $now = now();
                $rows = [];
                foreach ($albums as $al) {
                    $images = collect($al['images'] ?? [])->sortByDesc('width');
                    $rows[] = [
                        'spotify_id'               => $al['id'] ?? null,
                        'name'                     => $al['name'] ?? null,
                        'album_type'               => $al['album_type'] ?? null,
                        'release_date'             => $al['release_date'] ?? null, // raw precision
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

                $totalAlbumsUpserted += count($rows);

                // 5) Isi pivot album_artist (opsional)
                if ($pivotPrepared) {
                    $albumLocal = DB::table('albums')
                        ->whereIn('spotify_id', $allAlbumIds)
                        ->pluck('id', 'spotify_id');

                    $artistLocalId = $localArtists[$artistSpotifyId] ?? null;
                    if ($artistLocalId) {
                        $pivotRows = [];
                        foreach ($allAlbumIds as $aid) {
                            $albumId = $albumLocal[$aid] ?? null;
                            if ($albumId) {
                                $pivotRows[] = ['album_id' => $albumId, 'artist_id' => $artistLocalId];
                            }
                        }
                        foreach (array_chunk($pivotRows, 1000) as $chunk) {
                            DB::table('album_artist')->insertOrIgnore($chunk);
                        }
                    }
                }

                $this->info("Done artist {$artistSpotifyId}: upserted " . count($rows) . " albums");
            }

            $this->info("All done. Total albums upserted: {$totalAlbumsUpserted}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    protected function resolveArtistSpotifyIds(): array
    {
        $idsLocal = $this->option('artist-ids');
        $idsSpotify = $this->option('spotify-ids');

        if ($idsSpotify) {
            $arr = array_filter(array_map('trim', explode(',', $idsSpotify)));
            return array_values(array_unique($arr));
        }

        if ($idsLocal) {
            $arr = array_filter(array_map('intval', explode(',', $idsLocal)));
            if (empty($arr)) return [];
            return DB::table('artists')->whereIn('id', $arr)->pluck('spotify_id')->filter()->unique()->values()->all();
        }

        // default: semua artist
        return DB::table('artists')->pluck('spotify_id')->filter()->unique()->values()->all();
    }
}
