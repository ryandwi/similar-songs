<?php
// app/Console/Commands/CollectGenresFromArtistsCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SpotifyService;
use App\Services\GenresImportService;

class CollectGenresFromArtistsCommand extends Command
{
    protected $signature = 'collect:genres:artists
        {--q=pop : Search query awal (mis. pop, rock, hip hop)}
        {--limit=50 : Per halaman (max 50)}
        {--pages=10 : Jumlah halaman untuk di-scan}';

    protected $description = 'Agregasi genres dari artists hasil pencarian Spotify dan simpan ke tabel genres';

    public function handle(SpotifyService $spotify, GenresImportService $importer): int
    {
        try {
            $q = (string)$this->option('q');
            $limit = (int)$this->option('limit');
            $pages = (int)$this->option('pages');
            $offset = 0;

            $total = 0;
            for ($p = 1; $p <= $pages; $p++) {
                $result = $spotify->searchArtists($q, $limit, $offset);
                $items = $result['items'] ?? [];
                if (empty($items)) {
                    $this->info("No more artists at page {$p}.");
                    break;
                }
                $count = $importer->upsertFromArtists($items);
                $this->info("Page {$p}: upserted/updated {$count} genres.");
                $total += $count;
                $offset += $limit;
            }

            $this->info("Done. Total processed genres (upsert ops): {$total}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
