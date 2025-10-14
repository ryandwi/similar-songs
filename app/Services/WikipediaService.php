<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikipediaService
{
    private string $apiUrl = 'https://en.wikipedia.org/w/api.php';

    /**
     * Get artist description from Wikipedia
     *
     * @param string $artistName
     * @return string|null
     */
    public function getExtract(string $artistName): ?string
    {
        try {
            $response = Http::timeout(10)->get($this->apiUrl, [
                'action' => 'query',
                'prop' => 'extracts',
                'exintro' => 1,
                'explaintext' => 1,
                'titles' => $artistName,
                'format' => 'json',
                'redirects' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!isset($data['query']['pages'])) {
                    return null;
                }

                $pages = $data['query']['pages'];
                $page = reset($pages);

                if (isset($page['missing']) || !isset($page['extract'])) {
                    return null;
                }

                return $page['extract'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Wikipedia API Error: ' . $e->getMessage());
            return null;
        }
    }
}
