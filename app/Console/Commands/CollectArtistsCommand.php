<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SpotifyService;
use App\Services\ArtistsImportService;

class CollectArtistsCommand extends Command
{
    protected $signature = 'collect:artists 
        {--ids= : Comma-separated Spotify artist IDs} 
        {--q= : Search query untuk artist} 
        {--limit=50 : Batas per halaman pencarian (max 50)} 
        {--pages=1 : Jumlah halaman untuk diambil saat search}';

    protected $description = 'Kumpulkan data artists dari Spotify API dan simpan ke tabel artists';

    public function handle(SpotifyService $spotify, ArtistsImportService $importer): int
    {
        try {
            $totalUpserted = 0;

            if ($ids = $this->option('ids')) {
                $idList = array_filter(array_map('trim', explode(',', $ids)));
                $artists = $spotify->getSeveralArtists($idList);
                $count = $importer->upsertArtists($artists);
                $this->info("Upserted {$count} artists from IDs.");
                $totalUpserted += $count;
            }

            if ($q = $this->option('q')) {
                $limit = (int)$this->option('limit');
                $pages = (int)$this->option('pages');
                $offset = 0;

                for ($p = 1; $p <= $pages; $p++) {
                    $result = $spotify->searchArtists($q, $limit, $offset);
                    $items = $result['items'] ?? [];
                    $count = $importer->upsertArtists($items);
                    $this->info("Page {$p}: upserted {$count} artists for query '{$q}'.");
                    $totalUpserted += $count;

                    $offset += $limit;
                    if (empty($items)) {
                        break;
                    }
                }
            }

            if (!$ids && !$q) {
                $this->warn('No --ids or --q provided. Nothing to do.');
            } else {
                $this->info("Done. Total upserted: {$totalUpserted}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
