<?php
// App/Services/GenresImportService.php (tambahkan metode)
namespace App\Services;

use Illuminate\Support\Facades\DB;

class GenresImportService
{
    // ... metode upsertSeedGenres tetap ada

    public function upsertFromArtists(array $artists): int
    {
        $set = [];
        foreach ($artists as $a) {
            foreach (($a['genres'] ?? []) as $g) {
                $set[$g] = true;
            }
        }

        $genres = array_keys($set);
        if (empty($genres)) return 0;

        $now = now();
        $rows = array_map(fn($g) => [
            'name' => $g,
            'slug' => str($g)->slug('-'),
            'created_at' => $now,
            'updated_at' => $now,
        ], $genres);

        return DB::table('genres')->upsert(
            $rows,
            ['slug'],
            ['name','updated_at']
        );
    }
}
