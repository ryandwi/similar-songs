<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ArtistsSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'artists';
        $now = Carbon::now();

        $rows = [
            [
                'spotify_id'  => '1Xyo4u8uXC1ZmMpatF05PJ',
                'name'        => 'The Weeknd',
                'image_url'   => 'https://i.scdn.co/image/ab6761610000e5ebc88f4a9d1d1b1a5a3b2f9a7c',
                'popularity'  => 95,
                'followers'   => 97500000,
                'genres'      => json_encode(['canadian contemporary r&b', 'pop', 'r&b']),
                'spotify_url' => 'https://open.spotify.com/artist/1Xyo4u8uXC1ZmMpatF05PJ',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'spotify_id'  => '66CXWjxzNUsdJxJ2JdwvnR',
                'name'        => 'Ariana Grande',
                'image_url'   => 'https://i.scdn.co/image/ab6761610000e5eb5f5b2f6e65c3a1a2b3c4d5e6',
                'popularity'  => 92,
                'followers'   => 92000000,
                'genres'      => json_encode(['dance pop', 'pop', 'post-teen pop']),
                'spotify_url' => 'https://open.spotify.com/artist/66CXWjxzNUsdJxJ2JdwvnR',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'spotify_id'  => '06HL4z0CvFAxyc27GXpf02',
                'name'        => 'Taylor Swift',
                'image_url'   => 'https://i.scdn.co/image/ab6761610000e5ebc3f5f0b8e980a1b2c3d4e5f6',
                'popularity'  => 100,
                'followers'   => 112000000,
                'genres'      => json_encode(['pop', 'post-teen pop', 'country']),
                'spotify_url' => 'https://open.spotify.com/artist/06HL4z0CvFAxyc27GXpf02',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        for ($i = 0; $i < 7; $i++) {
            $spotifyId = Str::random(22);
            $rows[] = [
                'spotify_id'  => $spotifyId,
                'name'        => 'Artist ' . Str::upper(Str::random(5)),
                'image_url'   => 'https://picsum.photos/seed/' . $spotifyId . '/640/640',
                'popularity'  => rand(10, 100),
                'followers'   => rand(1_000, 5_000_000),
                'genres'      => json_encode(
                    collect(['pop','rock','r&b','hip hop','edm','indie','jazz','k-pop'])->random(rand(2,3))->values()->all()
                ),
                'spotify_url' => 'https://open.spotify.com/artist/' . $spotifyId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        DB::table($table)->insert($rows);
    }
}
