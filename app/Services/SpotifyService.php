<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SpotifyService
{
    protected string $apiBase;
    protected string $authUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->apiBase = config('services.spotify.api_base', env('SPOTIFY_API_BASE', 'https://api.spotify.com/v1'));
        $this->authUrl = config('services.spotify.auth_url', env('SPOTIFY_AUTH_URL', 'https://accounts.spotify.com/api/token'));
        $this->clientId = config('services.spotify.client_id', env('SPOTIFY_CLIENT_ID'));
        $this->clientSecret = config('services.spotify.client_secret', env('SPOTIFY_CLIENT_SECRET'));
    }

    public function accessToken(): string
    {
        return Cache::remember('spotify.client_credentials.token', now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post($this->authUrl, [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Failed to get Spotify token: ' . $response->body());
            }

            $data = $response->json();
            $ttl = max(1, (int)($data['expires_in'] ?? 3600) - 120); // buffer 2 menit
            Cache::put('spotify.client_credentials.token', $data['access_token'], now()->addSeconds($ttl));

            return $data['access_token'];
        });
    }

    protected function client()
    {
        return Http::withToken($this->accessToken())
            ->baseUrl($this->apiBase)
            ->acceptJson()
            ->timeout(20);
    }

    public function getArtist(string $id): array
    {
        $res = $this->client()->get("/artists/{$id}");
        if (!$res->successful()) {
            throw new \RuntimeException("Failed get artist {$id}: " . $res->body());
        }
        return $res->json();
    }

    public function getSeveralArtists(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        $chunks = array_chunk($ids, 50); // limit Spotify 50
        $artists = [];
        foreach ($chunks as $chunk) {
            $res = $this->client()->get('/artists', ['ids' => implode(',', $chunk)]);
            if (!$res->successful()) {
                throw new \RuntimeException('Failed get several artists: ' . $res->body());
            }
            $payload = $res->json();
            $artists = array_merge($artists, $payload['artists'] ?? []);
        }
        return $artists;
    }

    public function searchArtists(string $query, int $limit = 20, int $offset = 0): array
    {
        $res = $this->client()->get('/search', [
            'q' => $query,
            'type' => 'artist',
            'limit' => min($limit, 50),
            'offset' => $offset,
        ]);
        if (!$res->successful()) {
            throw new \RuntimeException('Failed search artists: ' . $res->body());
        }
        return $res->json()['artists'] ?? [];
    }


    // Tambahan di App\Services\SpotifyService (lanjutan dari sebelumnya)
    public function getSeveralTracks(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids)));
        $all = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $res = $this->client()->get('/tracks', ['ids' => implode(',', $chunk)]);
            if (!$res->successful()) {
                throw new \RuntimeException('Failed get several tracks: ' . $res->body());
            }
            $payload = $res->json();
            $all = array_merge($all, $payload['tracks'] ?? []);
        }
        return $all;
    }

    public function getAudioFeaturesForTracks(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids), fn($v) => is_string($v) && $v !== ''));
        if (empty($ids)) {
            return [];
        }

        $features = [];
        foreach (array_chunk($ids, 100) as $chunk) {
            $res = $this->client()
                ->retry(3, 300, throw: false)
                ->get('/audio-features', ['ids' => implode(',', $chunk)]);

            if ($res->status() === 401) {
                // Paksa refresh token lalu ulang sekali
                Cache::forget('spotify.client_credentials.token');
                $res = $this->client()->get('/audio-features', ['ids' => implode(',', $chunk)]);
            }

            if (!$res->successful()) {
                // Jika 403, pecah chunk jadi lebih kecil untuk menemukan id bermasalah
                if ($res->status() === 403 && count($chunk) > 1) {
                    foreach (array_chunk($chunk, 10) as $micro) {
                        $r2 = $this->client()->get('/audio-features', ['ids' => implode(',', $micro)]);
                        if ($r2->successful()) {
                            foreach (($r2->json()['audio_features'] ?? []) as $f) {
                                if ($f && isset($f['id'])) $features[$f['id']] = $f;
                            }
                        }
                        // Jika tetap gagal, log micro-batch lalu lanjut
                    }
                    continue;
                }
                throw new \RuntimeException('Failed get audio features: ' . $res->body());
            }

            foreach (($res->json()['audio_features'] ?? []) as $f) {
                if ($f && isset($f['id'])) {
                    $features[$f['id']] = $f;
                }
            }
        }
        return $features;
    }


    public function searchTracks(string $q, int $limit = 50, int $offset = 0): array
    {
        $res = $this->client()->get('/search', [
            'q' => $q,
            'type' => 'track',
            'limit' => min($limit, 50),
            'offset' => $offset
        ]);
        if (!$res->successful()) {
            throw new \RuntimeException('Failed search tracks: ' . $res->body());
        }
        return $res->json()['tracks'] ?? [];
    }

    public function getAlbumTracks(string $albumId, int $limit = 50, int $offset = 0): array
    {
        $res = $this->client()->get("/albums/{$albumId}/tracks", [
            'limit' => min($limit, 50),
            'offset' => $offset
        ]);
        if (!$res->successful()) {
            throw new \RuntimeException("Failed album tracks {$albumId}: " . $res->body());
        }
        return $res->json();
    }

    public function getArtistTopTracks(string $artistId, string $market = 'US'): array
    {
        $res = $this->client()->get("/artists/{${'artistId'}}/top-tracks", ['market' => $market]); // atau "/artists/{$artistId}/top-tracks"
        if (!$res->successful()) {
            throw new \RuntimeException("Failed artist top-tracks {$artistId}: " . $res->body());
        }
        return $res->json()['tracks'] ?? [];
    }

    // Di dalam class App\Services\SpotifyService

    public function getArtistAlbums(
        string $artistId,
        int $limit = 50,
        int $offset = 0,
        string $market = 'US',
        string $includeGroups = 'album,single,appears_on,compilation'
    ): array {
        // limit Spotify maks 50
        $limit = min($limit, 50);

        $res = $this->client()->get("/artists/{$artistId}/albums", [
            'include_groups' => $includeGroups, // contoh: album,single,appears_on,compilation
            'market' => $market,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        if (!$res->successful()) {
            throw new \RuntimeException("Failed get artist albums {$artistId}: " . $res->body());
        }

        return $res->json(); // berisi items[], next, total, dll.
    }

    public function getSeveralAlbums(array $ids): array
    {
        // Spotify membatasi 20 id per panggilan
        $ids = array_values(array_filter(array_unique($ids), fn($v) => is_string($v) && $v !== ''));
        if (empty($ids)) {
            return [];
        }

        $albums = [];
        foreach (array_chunk($ids, 20) as $chunk) {
            $res = $this->client()->get('/albums', [
                'ids' => implode(',', $chunk),
            ]);

            if (!$res->successful()) {
                throw new \RuntimeException('Failed get several albums: ' . $res->body());
            }

            $payload = $res->json();
            foreach (($payload['albums'] ?? []) as $al) {
                if ($al && isset($al['id'])) {
                    $albums[] = $al;
                }
            }
        }

        return $albums;
    }

    // App/Services/SpotifyService.php
    public function getRecommendationsByArtist(string $artistId, int $limit = 100): array
    {
        $limit = min($limit, 100);

        try {
            $res = $this->client()
                ->retry(2, 500, throw: false) // retry 2x dengan delay 500ms
                ->get('/recommendations', [
                    'seed_artists' => $artistId,
                    'limit' => $limit,
                ]);

            if (!$res->successful()) {
                // Log detail error untuk debugging
                \Log::warning("Recommendations failed for {$artistId}: status {$res->status()}, body: {$res->body()}");
                return [];
            }

            return $res->json();
        } catch (\Throwable $e) {
            \Log::error("Exception getting recommendations for {$artistId}: " . $e->getMessage());
            return [];
        }
    }

    public function getSimilarArtistsViaRecommendations(string $artistId): array
    {
        $recs = $this->getRecommendationsByArtist($artistId, 100);

        if (empty($recs) || empty($recs['tracks'])) {
            return [];
        }

        $tracks = $recs['tracks'];

        $artistMap = [];
        foreach ($tracks as $t) {
            foreach (($t['artists'] ?? []) as $a) {
                $aid = $a['id'] ?? null;
                if ($aid && $aid !== $artistId && !isset($artistMap[$aid])) {
                    $artistMap[$aid] = true;
                }
            }
        }

        $artistIds = array_keys($artistMap);
        if (empty($artistIds)) {
            return [];
        }

        try {
            return $this->getSeveralArtists($artistIds);
        } catch (\Throwable $e) {
            \Log::error("Failed enriching artists for {$artistId}: " . $e->getMessage());
            return [];
        }
    }

    // App/Services/SpotifyService.php
    public function getSimilarArtistsByGenre(string $artistId, int $limit = 20): array
    {
        try {
            // Ambil detail artis untuk mendapat genres
            $artist = $this->getArtist($artistId);

            dd($artist);
            $genres = $artist['genres'] ?? [];

            if (empty($genres)) {
                return [];
            }

            // Ambil maksimal 3 genre pertama untuk query
            $topGenres = array_slice($genres, 0, 3);

            // Build search query: genre:"pop" OR genre:"rock"
            $query = implode(' OR ', array_map(fn($g) => "genre:\"{$g}\"", $topGenres));

            $res = $this->client()->get('/search', [
                'q' => $query,
                'type' => 'artist',
                'limit' => min($limit, 50),
            ]);

            if (!$res->successful()) {
                \Log::warning("Search by genre failed for {$artistId}: " . $res->body());
                return [];
            }

            $items = $res->json()['artists']['items'] ?? [];

            // Filter artis sumber dan sort by popularity
            return collect($items)
                ->filter(fn($a) => ($a['id'] ?? null) !== $artistId)
                ->sortByDesc('popularity')
                ->take($limit)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            \Log::error("Exception getting similar artists for {$artistId}: " . $e->getMessage());
            return [];
        }
    }

    public function searchArtistByName(string $name, int $limit = 1): array
    {
        try {
            // Query exact match dengan quotes
            $res = $this->client()->get('/search', [
                'q' => "artist:\"{$name}\"",
                'type' => 'artist',
                'limit' => $limit,
            ]);

            if (!$res->successful()) {
                return [];
            }

            $items = $res->json()['artists']['items'] ?? [];
            return $items;
        } catch (\Throwable $e) {
            \Log::error("Search artist by name failed for '{$name}': " . $e->getMessage());
            return [];
        }
    }

    public function matchLastFmToSpotify(array $lastfmArtists): array
    {
        $matched = [];

        foreach ($lastfmArtists as $lfm) {
            $name = $lfm['name'] ?? null;
            if (!$name) continue;

            // Search di Spotify by exact name
            $results = $this->searchArtistByName($name, 1);

            if (!empty($results)) {
                $spotifyArtist = $results[0];
                // Tambahkan match score dari Last.fm
                $spotifyArtist['lastfm_match'] = $lfm['match'] ?? 0;
                $matched[] = $spotifyArtist;
            }

            // Delay kecil agar tidak spam API
            usleep(100000); // 100ms
        }

        return $matched;
    }
}
