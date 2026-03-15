<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevationService
{
    /**
     * Fetch elevations for an array of [latitude, longitude] pairs.
     * Uses the Open-Meteo Elevation API (free, no API key, max 100 per request).
     * Results are cached by rounded coordinates (4 decimal places ≈ 11m precision).
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates
     * @return array<int, float|null>
     */
    public function lookup(array $coordinates): array
    {
        if (empty($coordinates)) {
            return [];
        }

        $results = [];
        $uncached = [];

        // Check cache first for each coordinate
        foreach ($coordinates as $key => $coord) {
            $cacheKey = $this->cacheKey($coord[0], $coord[1]);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$key] = $cached;
            } else {
                $uncached[$key] = $coord;
            }
        }

        if (empty($uncached)) {
            return $results;
        }

        // Fetch uncached coordinates from API in batches of 100
        foreach (array_chunk($uncached, 100, true) as $chunk) {
            $lats = implode(',', array_column($chunk, 0));
            $lngs = implode(',', array_column($chunk, 1));

            try {
                $response = Http::timeout(10)
                    ->get('https://api.open-meteo.com/v1/elevation', [
                        'latitude' => $lats,
                        'longitude' => $lngs,
                    ]);

                if ($response->successful()) {
                    $elevations = $response->json('elevation', []);
                    $keys = array_keys($chunk);

                    foreach ($keys as $i => $key) {
                        $elevation = $elevations[$i] ?? null;
                        $results[$key] = $elevation;

                        if ($elevation !== null) {
                            Cache::put(
                                $this->cacheKey($chunk[$key][0], $chunk[$key][1]),
                                $elevation,
                                now()->addDays(90)
                            );
                        }
                    }
                } else {
                    Log::warning('Elevation API returned non-success', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    foreach (array_keys($chunk) as $key) {
                        $results[$key] = null;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Elevation API failed', ['error' => $e->getMessage()]);
                foreach (array_keys($chunk) as $key) {
                    $results[$key] = null;
                }
            }
        }

        return $results;
    }

    private function cacheKey(float $lat, float $lng): string
    {
        return 'elevation:' . round($lat, 4) . ':' . round($lng, 4);
    }
}
