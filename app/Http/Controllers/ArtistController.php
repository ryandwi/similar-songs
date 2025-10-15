<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ArtistController extends Controller
{
    public function show(string $slug, string $spotifyId)
    {
        // Ambil artis dengan semua field termasuk social & bio
        $artist = DB::table('artists')
            ->where('spotify_id', $spotifyId)
            ->first();

        abort_unless($artist, 404);

        // Redirect 301 jika slug tidak cocok
        $expectedSlug = Str::slug($artist->name, '-');
        if ($expectedSlug !== $slug) {
            return redirect()->route('artist.show', [
                'slug' => $expectedSlug,
                'spotifyId' => $spotifyId
            ], 301);
        }

        // Format born date dengan age
        $bornFormatted = null;
        $age = null;
        if (!empty($artist->born_date)) {
            try {
                $date = Carbon::parse($artist->born_date);
                $age = $date->age;
                $bornFormatted = $date->format('F j, Y') . ' (' . $age . ' years old)';
            } catch (\Exception $e) {
                // Ignore parsing errors
            }
        }

        // Collect social media links
        $socialLinks = $this->getSocialLinks($artist);

        // Ambil albums
        $hasPivot = Schema::hasTable('album_artist');

        if ($hasPivot) {
            $albums = DB::table('albums')
                ->join('album_artist', 'album_artist.album_id', '=', 'albums.id')
                ->join('artists', 'artists.id', '=', 'album_artist.artist_id')
                ->where('artists.spotify_id', $spotifyId)
                ->select([
                    'albums.id',
                    'albums.spotify_id',
                    'albums.name',
                    'albums.album_type',
                    'albums.release_date',
                    'albums.image_url',
                    'albums.total_tracks',
                    'albums.spotify_url',
                ])
                ->orderByDesc('albums.release_date')
                ->orderBy('albums.name')
                ->paginate(100);
        } else {
            $albums = collect([]);
        }

        $similar = [];
        if (Schema::hasTable('artist_related')) {
            $similar = DB::table('artists as similar')
                ->join('artist_related', 'artist_related.related_artist_id', '=', 'similar.id')
                ->join('artists as source', 'source.id', '=', 'artist_related.artist_id')
                ->where('source.spotify_id', $spotifyId)
                ->select(
                    'similar.id',
                    'similar.spotify_id',
                    'similar.name',
                    'similar.image_url',
                    'similar.popularity',
                    'similar.followers',
                    'similar.genres',
                    'similar.spotify_url'
                )
                ->orderByDesc('similar.popularity')
                // ->limit(18)
                ->get()
                ->map(function ($a) {
                    $genres = is_string($a->genres) ? json_decode($a->genres, true) : ($a->genres ?? []);
                    return [
                        'id' => $a->id,
                        'spotify_id' => $a->spotify_id,
                        'name' => $a->name,
                        'image_url' => $a->image_url,
                        'popularity' => $a->popularity,
                        'followers' => $a->followers,
                        'genres' => $genres,
                        'spotify_url' => $a->spotify_url,
                        'slug' => Str::slug($a->name, '-'),
                    ];
                })
                ->all();
        }

        $topTracks = [];
        if (Schema::hasTable('artist_top_tracks')) {
            $topTracks = DB::table('songs')
                ->join('artist_top_tracks', 'artist_top_tracks.song_id', '=', 'songs.id')
                ->where('artist_top_tracks.artist_id', $artist->id)
                ->where('artist_top_tracks.market', 'US')
                ->select('songs.*', 'artist_top_tracks.rank')
                ->orderBy('artist_top_tracks.rank')
                // ->limit(10)
                ->get();
        }

        $seoText = $this->generateSeoText($artist->name, $similar);

        return view('artist.show', [
            'artist' => $artist,
            'albums' => $albums,
            'similar' => $similar,
            'topTracks' => $topTracks,
            'socialLinks' => $socialLinks,
            'bornFormatted' => $bornFormatted,
            'seoText' => $seoText,
            'age' => $age,
        ]);
    }

    private function getSocialLinks($artist): array
    {
        $links = [];

        $platforms = [
            'facebook' => ['icon' => 'bi-facebook', 'name' => 'Facebook', 'color' => 'primary'],
            'twitter' => ['icon' => 'bi-twitter-x', 'name' => 'Twitter', 'color' => 'dark'],
            'instagram' => ['icon' => 'bi-instagram', 'name' => 'Instagram', 'color' => 'danger'],
            'youtube' => ['icon' => 'bi-youtube', 'name' => 'YouTube', 'color' => 'danger'],
            'soundcloud' => ['icon' => 'bi-cloud', 'name' => 'SoundCloud', 'color' => 'warning'],
            'tiktok' => ['icon' => 'bi-tiktok', 'name' => 'TikTok', 'color' => 'dark'],
            'bandcamp' => ['icon' => 'bi-music-note-beamed', 'name' => 'Bandcamp', 'color' => 'info'],
            'myspace' => ['icon' => 'bi-at', 'name' => 'MySpace', 'color' => 'secondary'],
            'discogs' => ['icon' => 'bi-vinyl', 'name' => 'Discogs', 'color' => 'secondary'],
        ];

        foreach ($platforms as $platform => $meta) {
            $urlField = $platform . '_url';
            if (!empty($artist->{$urlField})) {
                $links[] = [
                    'url' => $artist->{$urlField},
                    'icon' => $meta['icon'],
                    'name' => $meta['name'],
                    'color' => $meta['color'],
                ];
            }
        }

        return $links;
    }


    private function generateSeoText(string $artistName, array $similar): string
    {
        $namesList = collect($similar)->pluck('name')->take(5)->toArray();

        if (count($namesList) > 1) {
            $last = array_pop($namesList);
            $names = implode(', ', $namesList) . ' and ' . $last;
        } else {
            $names = $namesList[0] ?? '';
        }

        return "Based on shared genres, audio features, and fan overlap, here are the top 20 artists like {$artistName}. Discover singers such as {$names}—each offering a sound and style {$artistName} fans will love.";
    }
}
