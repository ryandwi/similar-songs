<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArtistController extends Controller
{
    public function show(string $slug, string $spotifyId)
    {
        // Ambil artis
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

        // Ambil albums: jika pivot album_artist tersedia, gunakan join; jika tidak, fallback berdasarkan albums yang menaut pada artist melalui pivot opsional
        $hasPivot = \Illuminate\Support\Facades\Schema::hasTable('album_artist');

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
                ->paginate(24);
        } else {
            // Fallback: jika pivot belum ada, bisa didefinisikan strategi lain (misal simpan album_id di songs dan derive dari sana).
            // Untuk sementara tampilkan kosong atau informasikan belum tersedia.
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
                ->limit(18) // 3 baris x 6 kolom
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
                ->where('artist_top_tracks.market', 'US') // atau dynamic berdasarkan user locale
                ->select('songs.*', 'artist_top_tracks.rank')
                ->orderBy('artist_top_tracks.rank')
                ->limit(10)
                ->get();
        }

        return view('artist.show', [
            'artist' => $artist,
            'albums' => $albums,
            'similar' => $similar,
            'topTracks' => $topTracks,
        ]);
    }
}
