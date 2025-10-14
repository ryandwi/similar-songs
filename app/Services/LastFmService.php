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

    /**
     * Get similar artists from Last.fm
     * 
     * @param string $artistName
     * @param int $limit
     * @return array
     */
    public function getSimilarArtists(string $artistName, int $limit = 50): array
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
                    'autocorrect' => 1,
                ]);

            if (!$res->successful()) {
                Log::warning("Last.fm failed for '{$artistName}': " . $res->body());
                return [];
            }

            $data = $res->json();
            
            $artists = $data['similarartists']['artist'] ?? [];
            
            return collect($artists)->map(fn($a) => [
                'name' => $a['name'] ?? null,
                'match' => (float)($a['match'] ?? 0),
                'mbid' => $a['mbid'] ?? null,
            ])->filter(fn($a) => !empty($a['name']))
              ->sortByDesc('match')
              ->values()
              ->all();

        } catch (\Throwable $e) {
            Log::error("Exception Last.fm for '{$artistName}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get extended similar artists using recursive approach
     * Calls getSimilarArtists internally for each level
     * 
     * @param string $artistName Original artist name
     * @param int $targetCount Target jumlah artis unik
     * @param int $topExpand Jumlah top artists untuk di-expand di level 2
     * @param float $minScore Minimum match score threshold
     * @return array
     */
    public function getExtendedSimilarArtists(
        string $artistName, 
        int $targetCount = 150, 
        int $topExpand = 10,
        float $minScore = 0.03
    ): array {
        $allArtists = [];
        $processed = [strtolower($artistName)];
        
        // Level 1: Direct similar artists
        $directSimilar = $this->getSimilarArtists($artistName, 50);
        
        foreach ($directSimilar as $artist) {
            $nameLower = strtolower($artist['name']);
            
            if (!isset($allArtists[$nameLower]) && $artist['match'] >= $minScore) {
                $artist['level'] = 1;
                $artist['source'] = $artistName;
                $allArtists[$nameLower] = $artist;
            }
        }
        
        // Level 2: Similar dari top similar artists
        if (count($allArtists) < $targetCount) {
            $topSimilar = array_slice($directSimilar, 0, min($topExpand, count($directSimilar)));
            
            foreach ($topSimilar as $artist) {
                if (count($allArtists) >= $targetCount) {
                    break;
                }
                
                $nameLower = strtolower($artist['name']);
                
                if (in_array($nameLower, $processed)) {
                    continue;
                }
                
                $processed[] = $nameLower;
                sleep(1); // Rate limiting
                
                // Recursive call to getSimilarArtists
                $nestedSimilar = $this->getSimilarArtists($artist['name'], 30);
                
                foreach ($nestedSimilar as $nestedArtist) {
                    if (count($allArtists) >= $targetCount) {
                        break 2;
                    }
                    
                    $nestedNameLower = strtolower($nestedArtist['name']);
                    
                    if (isset($allArtists[$nestedNameLower]) || 
                        $nestedNameLower === strtolower($artistName)) {
                        continue;
                    }
                    
                    $adjustedScore = $nestedArtist['match'] * 0.6;
                    
                    if ($adjustedScore >= $minScore) {
                        $nestedArtist['match'] = $adjustedScore;
                        $nestedArtist['level'] = 2;
                        $nestedArtist['source'] = $artist['name'];
                        $allArtists[$nestedNameLower] = $nestedArtist;
                    }
                }
            }
        }
        
        return collect($allArtists)
            ->sortByDesc('match')
            ->values()
            ->take($targetCount)
            ->all();
    }
}
