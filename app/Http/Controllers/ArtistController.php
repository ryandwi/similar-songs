<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $genre = $request->get('genre', '');
        $sort = $request->get('sort', 'popularity');
        $perPage = $request->get('per_page', 24);

        $query = DB::table('artists');

        // Search
        if (!empty($search)) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Filter by genre
        if (!empty($genre)) {
            $query->whereRaw("genres LIKE ?", ["%\"{$genre}\"%"]);
        }

        // Sorting
        switch ($sort) {
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'followers':
                $query->orderByDesc('followers');
                break;
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'popularity':
            default:
                $query->orderByDesc('popularity');
                break;
        }

        // Pagination
        $artists = $query->paginate($perPage)->withQueryString();

        // Get all genres untuk dropdown
        $allArtists = DB::table('artists')
            ->whereNotNull('genres')
            ->select('genres')
            ->get();

        $allGenres = collect([]);
        foreach ($allArtists as $a) {
            $genres = is_string($a->genres) ? json_decode($a->genres, true) : [];
            if (is_array($genres)) {
                $allGenres = $allGenres->merge($genres);
            }
        }
        $allGenres = $allGenres->unique()->sort()->values();

        // Stats
        $totalArtists = DB::table('artists')->count();
        $totalFollowers = DB::table('artists')->sum('followers');

        return view('artist.index', [
            'artists' => $artists,
            'allGenres' => $allGenres,
            'search' => $search,
            'genre' => $genre,
            'sort' => $sort,
            'perPage' => $perPage,
            'totalArtists' => $totalArtists,
            'totalFollowers' => $totalFollowers,
        ]);
    }


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
        $topTrackText = $this->generateTopTrackText($artist->name, $topTracks, $similar);
        $albumText = $this->generateAlbumText($artist->name, $albums);

        return view('artist.show', [
            'artist' => $artist,
            'albums' => $albums,
            'similar' => $similar,
            'topTracks' => $topTracks,
            'socialLinks' => $socialLinks,
            'bornFormatted' => $bornFormatted,
            'seoText' => $seoText,
            'topTrackText' => $topTrackText,
            'albumText' => $albumText,
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

    private function generateTopTrackText(string $artistName, $topTracks, array $similar): string
    {
        if (empty($topTracks) || count($topTracks) < 2) {
            return '';
        }

        // Ambil 2-3 lagu teratas
        $featured = $topTracks->take(3)->pluck('title')->toArray();

        if (count($featured) === 0) {
            return '';
        }

        // Format nama lagu dengan quotes
        $trackList = array_map(fn($track) => "\"$track\"", $featured);

        // Buat kalimat untuk tracks
        if (count($trackList) === 1) {
            $tracks = $trackList[0];
        } elseif (count($trackList) === 2) {
            $tracks = $trackList[0] . ' and ' . $trackList[1];
        } else {
            $last = array_pop($trackList);
            $tracks = implode(', ', $trackList) . ', and ' . $last;
        }

        $text = "Explore {$artistName}'s top songs, featuring standout tracks like {$tracks}";

        // Tambahkan similar artists jika ada
        if (!empty($similar) && count($similar) > 0) {
            // Ambil 1-2 similar artists teratas berdasarkan popularity
            $similarArtists = array_slice($similar, 0, 2);
            $similarNames = array_column($similarArtists, 'name');

            if (count($similarNames) === 1) {
                $text .= " and collaborations with {$similarNames[0]}";
            } elseif (count($similarNames) === 2) {
                $text .= " and collaborations with {$similarNames[0]} and {$similarNames[1]}";
            }
        }

        return $text . '.';
    }


    private function generateAlbumText(string $artistName, $albums): string
    {
        if (empty($albums) || $albums->count() === 0) {
            return '';
        }

        // Ambil 2-3 album teratas (terbaru atau paling populer)
        $featured = $albums->take(3);

        $albumNames = $featured->map(function ($album) {
            return "\"{$album->name}\"";
        })->toArray();

        // Format list album
        if (count($albumNames) === 1) {
            $albumList = $albumNames[0];
        } elseif (count($albumNames) === 2) {
            $albumList = $albumNames[0] . ' and ' . $albumNames[1];
        } else {
            $last = array_pop($albumNames);
            $albumList = implode(', ', $albumNames) . ', and ' . $last;
        }

        // Hitung jumlah album dan single
        $totalAlbums = $albums->where('album_type', 'album')->count();
        $totalSingles = $albums->where('album_type', 'single')->count();

        // Buat collection info
        $collectionText = [];
        if ($totalAlbums > 0) {
            $collectionText[] = "{$totalAlbums} album" . ($totalAlbums > 1 ? 's' : '');
        }
        if ($totalSingles > 0) {
            $collectionText[] = "{$totalSingles} single" . ($totalSingles > 1 ? 's' : '');
        }
        $collection = implode(' and ', $collectionText);

        // Generate text dinamis
        $text = "Explore {$artistName}'s complete discography, featuring {$collection}. ";
        $text .= "Includes standout releases like {$albumList}, ";
        $text .= "perfect for fans and new listeners alike.";

        return $text;
    }
}
