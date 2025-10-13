<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SpotifyService;
use App\Services\SongsImportService;

class CollectSongsCommand extends Command
{
    protected $signature = 'collect:songs
        {--ids= : Comma-separated track IDs}
        {--q= : Search query}
        {--album= : Album ID untuk mengambil seluruh tracks}
        {--artist= : Artist ID untuk mengambil top-tracks}
        {--limit=50 : Limit per page untuk search/album}
        {--pages=2 : Jumlah halaman search/album yang diproses}';

    protected $description = 'Kumpulkan data songs dari Spotify dan simpan ke tabel songs';

    public function handle(SpotifyService $spotify, SongsImportService $importer): int
    {
        try {
            $total = 0;

            // Mode 1: by IDs
            if ($idsOpt = $this->option('ids')) {
                $ids = array_filter(array_map('trim', explode(',', $idsOpt)));
                $tracks = $spotify->getSeveralTracks($ids);
                $features = $spotify->getAudioFeaturesForTracks(array_column($tracks, 'id'));

                $mapped = array_map(function ($t) use ($importer, $features) {
                    $f = $features[$t['id']] ?? null;
                    return $importer->mapSong($t, $f);
                }, $tracks);

                $count = $importer->upsertSongs($mapped);
                $this->info("Upserted {$count} songs from IDs.");
                $total += $count;
            }

            // Mode 2: by search query (paginate)
            if ($q = $this->option('q')) {
                $limit = (int)$this->option('limit');
                $pages = (int)$this->option('pages');
                $offset = 0;

                for ($p = 1; $p <= $pages; $p++) {
                    $res = $spotify->searchTracks($q, $limit, $offset);
                    $items = $res['items'] ?? [];
                    if (empty($items)) {
                        $this->info("No more tracks at page {$p}.");
                        break;
                    }
                    $ids = array_column($items, 'id');
                    $features = $spotify->getAudioFeaturesForTracks($ids);

                    $mapped = array_map(function ($t) use ($importer, $features) {
                        $f = $features[$t['id']] ?? null;
                        return $importer->mapSong($t, $f);
                    }, $items);

                    $count = $importer->upsertSongs($mapped);
                    $this->info("Page {$p}: upserted {$count} songs for query '{$q}'.");
                    $total += $count;
                    $offset += $limit;
                }
            }

            // Mode 3: by album
            if ($albumId = $this->option('album')) {
                $limit = (int)$this->option('limit');
                $offset = 0;
                $page = 1;
                do {
                    $res = $spotify->getAlbumTracks($albumId, $limit, $offset);
                    $items = $res['items'] ?? [];
                    if (empty($items)) break;

                    // Items dari album/tracks tidak selalu punya semua field track-detail (popularity, preview_url, external_ids).
                    // Jika perlu, ambil detail track per 50:
                    $trackIds = array_column($items, 'id');
                    $tracks = $spotify->getSeveralTracks($trackIds);
                    $features = $spotify->getAudioFeaturesForTracks($trackIds);

                    $mapped = array_map(function ($t) use ($importer, $features) {
                        $f = $features[$t['id']] ?? null;
                        return $importer->mapSong($t, $f);
                    }, $tracks);

                    $count = $importer->upsertSongs($mapped);
                    $this->info("Album page {$page}: upserted {$count} songs.");
                    $total += $count;

                    $offset += $limit;
                    $page++;
                } while (!empty($res['next']));
            }

            // Mode 4: artist top-tracks
            if ($artistId = $this->option('artist')) {
                $tracks = $spotify->getArtistTopTracks($artistId, 'US');
                $ids = array_column($tracks, 'id');
                $features = $spotify->getAudioFeaturesForTracks($ids);

                $mapped = array_map(function ($t) use ($importer, $features) {
                    $f = $features[$t['id']] ?? null;
                    return $importer->mapSong($t, $f);
                }, $tracks);

                $count = $importer->upsertSongs($mapped);
                $this->info("Upserted {$count} top-tracks for artist {$artistId}.");
                $total += $count;
            }

            if (!$this->option('ids') && !$this->option('q') && !$this->option('album') && !$this->option('artist')) {
                $this->warn('No --ids, --q, --album, or --artist provided.');
            } else {
                $this->info("Done. Total upserted: {$total}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
