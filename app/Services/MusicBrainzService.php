<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MusicBrainzService
{
    private const BASE_URL = 'https://musicbrainz.org/ws/2';
    private const USER_AGENT = 'YourAppName/1.0.0 ( your-email@example.com )';
    
    /**
     * Search artist by keyword and get artist data with social URLs
     *
     * @param string $keyword
     * @return array|null
     */
    public function getArtistDataByKeyword(string $keyword): ?array
    {
        try {
            // Step 1: Search artist by keyword
            $searchResult = $this->searchArtist($keyword);
            
            if (!$searchResult || empty($searchResult['artists'])) {
                return null;
            }
            
            // Get first result MBID
            $mbid = $searchResult['artists'][0]['id'];
            
            // Step 2: Get artist details with URL relationships
            $artistData = $this->getArtistDetails($mbid);
            
            if (!$artistData) {
                return null;
            }
            
            // Step 3: Parse and return formatted data
            return $this->parseArtistData($artistData);
            
        } catch (\Exception $e) {
            Log::error('MusicBrainz API Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search artist by keyword
     *
     * @param string $keyword
     * @return array|null
     */
    private function searchArtist(string $keyword): ?array
    {
        $url = self::BASE_URL . '/artist/';
        
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
        ])->get($url, [
            'query' => 'artist:' . $keyword,
            'fmt' => 'json',
            'limit' => 1
        ]);
        
        // Rate limiting: wait 1 second between requests
        sleep(1);
        
        if ($response->successful()) {
            return $response->json();
        }
        
        return null;
    }
    
    /**
     * Get artist details with URL relationships
     *
     * @param string $mbid
     * @return array|null
     */
    private function getArtistDetails(string $mbid): ?array
    {
        $url = self::BASE_URL . '/artist/' . $mbid;
        
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
        ])->get($url, [
            'inc' => 'url-rels',
            'fmt' => 'json'
        ]);
        
        // Rate limiting: wait 1 second between requests
        sleep(1);
        
        if ($response->successful()) {
            return $response->json();
        }
        
        return null;
    }
    
    /**
     * Parse artist data and extract required fields
     *
     * @param array $artistData
     * @return array
     */
    private function parseArtistData(array $artistData): array
    {
        $result = [
            'facebook_url' => null,
            'twitter_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
            'soundcloud_url' => null,
            'myspace_url' => null,
            'bandcamp_url' => null,
            'tiktok_url' => null,
            'discogs_url' => null,
            'born_date' => null,
            'born_in' => null,
            'gender' => null,
            'country' => null,
        ];
        
        // Extract social media URLs from relations
        if (isset($artistData['relations']) && is_array($artistData['relations'])) {
            foreach ($artistData['relations'] as $relation) {
                if ($relation['type'] === 'social network' || $relation['type'] === 'streaming' || 
                    $relation['type'] === 'discogs' || isset($relation['url']['resource'])) {
                    
                    $url = $relation['url']['resource'] ?? null;
                    
                    if ($url) {
                        // Facebook
                        if (str_contains($url, 'facebook.com')) {
                            $result['facebook_url'] = $url;
                        }
                        // Twitter/X
                        elseif (str_contains($url, 'twitter.com') || str_contains($url, 'x.com')) {
                            $result['twitter_url'] = $url;
                        }
                        // Instagram
                        elseif (str_contains($url, 'instagram.com')) {
                            $result['instagram_url'] = $url;
                        }
                        // YouTube
                        elseif (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
                            $result['youtube_url'] = $url;
                        }
                        // SoundCloud
                        elseif (str_contains($url, 'soundcloud.com')) {
                            $result['soundcloud_url'] = $url;
                        }
                        // MySpace
                        elseif (str_contains($url, 'myspace.com')) {
                            $result['myspace_url'] = $url;
                        }
                        // Bandcamp
                        elseif (str_contains($url, 'bandcamp.com')) {
                            $result['bandcamp_url'] = $url;
                        }
                        // TikTok
                        elseif (str_contains($url, 'tiktok.com')) {
                            $result['tiktok_url'] = $url;
                        }
                        // Discogs
                        elseif (str_contains($url, 'discogs.com')) {
                            $result['discogs_url'] = $url;
                        }
                    }
                }
            }
        }
        
        // Extract birth date
        // if (isset($artistData['life-span']['begin'])) {
        //     $result['born_date'] = $artistData['life-span']['begin'];
        // }
        
        // Extract birth place
        if (isset($artistData['begin-area']['name'])) {
            $result['born_in'] = $artistData['begin-area']['name'];
        } elseif (isset($artistData['area']['name'])) {
            $result['born_in'] = $artistData['area']['name'];
        }
        
        // Extract gender
        if (isset($artistData['gender'])) {
            $result['gender'] = ucfirst(strtolower($artistData['gender']));
        }
        
        // Extract country
        if (isset($artistData['country'])) {
            $result['country'] = $artistData['country'];
        } elseif (isset($artistData['area']['iso-3166-1-codes'][0])) {
            $result['country'] = $artistData['area']['iso-3166-1-codes'][0];
        }
        
        return $result;
    }
    
    /**
     * Calculate age from birth date
     *
     * @param string $birthDate
     * @return int|null
     */
    public function calculateAge(string $birthDate): ?int
    {
        try {
            return Carbon::parse($birthDate)->age;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Format birth date with age
     *
     * @param string $birthDate
     * @return string|null
     */
    public function formatBirthDateWithAge(string $birthDate): ?string
    {
        try {
            $date = Carbon::parse($birthDate);
            $age = $date->age;
            $yearsAgo = $age . ' years ago';
            
            return $date->format('Y-m-d') . ' (' . $yearsAgo . ')';
        } catch (\Exception $e) {
            return null;
        }
    }
}
