<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    public function reverse(?float $lat, ?float $lng): ?string
    {
        if (! $lat || ! $lng) {
            return null;
        }

        $cacheKey = 'geocode:' . round($lat, 4) . ':' . round($lng, 4);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('teslog.geocoding.user_agent', 'Teslog/1.0'),
            ])
                ->timeout(5)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $address = $data['address'] ?? [];

                // Build a concise address
                $parts = array_filter([
                    $address['house_number'] ?? null,
                    $address['road'] ?? $address['pedestrian'] ?? null,
                    $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
                    $address['state'] ?? null,
                ]);

                $result = implode(', ', $parts) ?: ($data['display_name'] ?? null);

                if ($result) {
                    Cache::put($cacheKey, $result, now()->addDays(30));
                }

                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('Geocoding failed', ['lat' => $lat, 'lng' => $lng, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
