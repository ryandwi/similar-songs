<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SongsImportService
{
    protected function mapKeySignature(?int $key, ?int $mode): ?string
    {
        $keys = ['C','C#','D','Eb','E','F','F#','G','Ab','A','Bb','B'];
        if ($key === null || $mode === null) return null;
        if (!isset($keys[$key])) return null;
        $suffix = $mode === 1 ? 'maj' : 'min';
        return $keys[$key] . ' ' . $suffix;
    }

    protected function pickAlbumImage(?array $album): ?string
    {
        $images = collect($album['images'] ?? [])->sortByDesc('width');
        return $images->value('url');
    }

    protected function resolvePrimaryArtistId(array $track): ?int
    {
        // Ambil artist Spotify ID pertama, lalu cari di tabel artists (by spotify_id) untuk dapatkan artist_id lokal
        $firstArtistSpotifyId = Arr::get($track, 'artists.0.id');
        if (!$firstArtistSpotifyId) return null;

        $artist = DB::table('artists')->where('spotify_id', $firstArtistSpotifyId)->first(['id']);
        return $artist?->id;
    }

    protected function resolveGenreIdFromArtist(?int $artistId): ?int
    {
        if (!$artistId) return null;
        // Jika punya pivot/relasi genre, pilih salah satu; jika tidak ada pivot, lewati (NULL)
        // Contoh: asumsi ada tabel artist_genre(artist_id, genre_id)
        $pivot = DB::table('artist_genre')->where('artist_id', $artistId)->first(['genre_id']);
        return $pivot?->genre_id;
    }

    public function mapSong(array $track, ?array $features = null): array
    {
        $album = $track['album'] ?? [];
        $releaseRaw = $album['release_date'] ?? null;
        // Spotify release_date_precision bisa 'day'/'month'/'year' — amankan ke date
        $release = null;
        if ($releaseRaw) {
            if (strlen($releaseRaw) === 4) {
                $release = $releaseRaw . '-01-01';
            } elseif (strlen($releaseRaw) === 7) {
                $release = $releaseRaw . '-01';
            } else {
                $release = $releaseRaw;
            }
        }

        $artistId = $this->resolvePrimaryArtistId($track);
        $genreId = $this->resolveGenreIdFromArtist($artistId);

        $key = $features['key'] ?? null;
        $mode = $features['mode'] ?? null;

        return [
            'spotify_id'      => $track['id'] ?? null,
            'title'           => $track['name'] ?? null,
            'artist_id'       => $artistId ?? 0, // tabel mengharuskan NOT NULL; pastikan seed artis dulu
            'genre_id'        => $genreId,
            'album_name'      => $album['name'] ?? null,
            'album_image_url' => $this->pickAlbumImage($album),
            'release_date'    => $release,
            'duration_ms'     => (int)($track['duration_ms'] ?? 0),
            'popularity'      => (int)($track['popularity'] ?? 0),
            'preview_url'     => $track['preview_url'] ?? null,
            'spotify_url'     => Arr::get($track, 'external_urls.spotify'),
            'isrc'            => Arr::get($track, 'external_ids.isrc'),

            // audio features (default 0 jika tidak tersedia)
            'danceability'    => isset($features['danceability']) ? (float)$features['danceability'] : 0,
            'energy'          => isset($features['energy']) ? (float)$features['energy'] : 0,
            'speechiness'     => isset($features['speechiness']) ? (float)$features['speechiness'] : 0,
            'acousticness'    => isset($features['acousticness']) ? (float)$features['acousticness'] : 0,
            'instrumentalness'=> isset($features['instrumentalness']) ? (float)$features['instrumentalness'] : 0,
            'liveness'        => isset($features['liveness']) ? (float)$features['liveness'] : 0,
            'valence'         => isset($features['valence']) ? (float)$features['valence'] : 0,
            'loudness'        => isset($features['loudness']) ? (float)$features['loudness'] : 0,
            'tempo'           => isset($features['tempo']) ? (float)$features['tempo'] : 0,
            'key'             => is_int($key) ? $key : null,
            'mode'            => is_int($mode) ? $mode : null,
            'time_signature'  => isset($features['time_signature']) ? (int)$features['time_signature'] : 4,
            'key_signature'   => $this->mapKeySignature($key, $mode),

            'explicit'        => (int)($track['explicit'] ?? 0),
            'last_synced_at'  => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ];
    }

    public function upsertSongs(array $mappedRows): int
    {
        if (empty($mappedRows)) return 0;

        return DB::table('songs')->upsert(
            $mappedRows,
            ['spotify_id'],
            [
                'title','artist_id','genre_id','album_name','album_image_url','release_date',
                'duration_ms','popularity','preview_url','spotify_url','isrc',
                'danceability','energy','speechiness','acousticness','instrumentalness',
                'liveness','valence','loudness','tempo','key','mode','time_signature','key_signature',
                'explicit','last_synced_at','updated_at'
            ]
        );
    }
}
