<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArtistsImportService
{
    public function mapArtist(array $a): array
    {
        return [
            'spotify_id'  => $a['id'] ?? null,
            'name'        => $a['name'] ?? null,
            'image_url'   => collect($a['images'] ?? [])->sortByDesc('width')->value('url'),
            'popularity'  => $a['popularity'] ?? 0,
            'followers'   => Arr::get($a, 'followers.total', 0),
            'genres'      => json_encode($a['genres'] ?? []),
            'spotify_url' => Arr::get($a, 'external_urls.spotify'),
            'created_at'  => now(),
            'updated_at'  => now(),
        ];
    }

    public function upsertArtists(array $artists): int
    {
        $rows = array_map(fn($a) => $this->mapArtist($a), $artists);
        if (empty($rows)) {
            return 0;
        }
        return DB::table('artists')->upsert(
            $rows,
            ['spotify_id'], // unique key
            ['name','image_url','popularity','followers','genres','spotify_url','updated_at']
        );
    }
}
