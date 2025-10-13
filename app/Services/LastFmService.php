<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LastFmService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.lastfm.api_key', env('LASTFM_API_KEY'));
        $this->baseUrl = config('services.lastfm.base_url', 'https://ws.audioscrobbler.com/2.0/');
    }

    public function getSimilarArtists(string $artistName, int $limit = 30): array
    {
        try {
            $res = Http::timeout(15)
                ->retry(2, 500)
                ->get($this->baseUrl, [
                    'method' => 'artist.getSimilar',
                    'artist' => $artistName,
                    'api_key' => $this->apiKey,
                    'format' => 'json',
                    'limit' => $limit,
                    'autocorrect' => 1, // Auto-correct typo nama artis
                ]);

            if (!$res->successful()) {
                Log::warning("Last.fm failed for '{$artistName}': " . $res->body());
                return [];
            }

            $data = $res->json();
            
            // Last.fm mengembalikan struktur: similarartists.artist[]
            $artists = $data['similarartists']['artist'] ?? [];
            
            // Normalisasi struktur: ambil name dan match score
            return collect($artists)->map(fn($a) => [
                'name' => $a['name'] ?? null,
                'match' => (float)($a['match'] ?? 0), // similarity score 0-1
                'mbid' => $a['mbid'] ?? null, // MusicBrainz ID (opsional)
            ])->filter(fn($a) => !empty($a['name']))->all();

        } catch (\Throwable $e) {
            Log::error("Exception Last.fm for '{$artistName}': " . $e->getMessage());
            return [];
        }
    }
}
