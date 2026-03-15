<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Drive;
use App\Models\Place;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeAddresses extends Command
{
    protected $signature = 'teslog:geocode
        {--vehicle= : Vehicle ID (processes all if omitted)}
        {--force : Re-geocode even if address already set}';

    protected $description = 'Reverse geocode drive and charge locations using Nominatim (OpenStreetMap)';

    /** @var array<string, string|null> In-memory cache keyed by rounded coordinates */
    private array $geocodeCache = [];

    private int $apiCalls = 0;

    private int $cacheHits = 0;

    private int $placeHits = 0;

    /** @var \Illuminate\Database\Eloquent\Collection<Place> */
    private $places;

    public function handle(): int
    {
        $vehicleId = $this->option('vehicle');
        $force = $this->option('force');

        $this->places = Place::all();
        if ($this->places->isNotEmpty()) {
            $this->info("Loaded {$this->places->count()} saved places for matching.");
        }

        // Drives — exclude (0,0) which is a bogus GPS fix
        $drivesQuery = Drive::whereNotNull('start_latitude')->whereNotNull('start_longitude')
            ->where(function ($q) {
                $q->where('start_latitude', '!=', 0)->orWhere('start_longitude', '!=', 0);
            });
        if (! $force) {
            $drivesQuery->whereNull('start_address');
        }
        if ($vehicleId) {
            $drivesQuery->where('vehicle_id', $vehicleId);
        }
        $driveCount = $drivesQuery->count();
        $this->info("Geocoding {$driveCount} drive start/end locations...");

        $bar = $this->output->createProgressBar($driveCount);
        $bar->start();

        $drivesQuery->chunkById(50, function ($drives) use ($force, $bar) {
            foreach ($drives as $drive) {
                if ($drive->start_latitude && $drive->start_longitude) {
                    if ($force || ! $drive->start_address) {
                        $drive->start_address = $this->resolve($drive->start_latitude, $drive->start_longitude);
                    }
                }

                if ($drive->end_latitude && $drive->end_longitude) {
                    if ($force || ! $drive->end_address) {
                        $drive->end_address = $this->resolve($drive->end_latitude, $drive->end_longitude);
                    }
                }

                $drive->save();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        // Charges — exclude (0,0) which is a bogus GPS fix
        $chargesQuery = Charge::whereNotNull('latitude')->whereNotNull('longitude')
            ->where(function ($q) {
                $q->where('latitude', '!=', 0)->orWhere('longitude', '!=', 0);
            });
        if (! $force) {
            $chargesQuery->whereNull('address');
        }
        if ($vehicleId) {
            $chargesQuery->where('vehicle_id', $vehicleId);
        }

        $chargeCount = $chargesQuery->count();
        $this->info("Geocoding {$chargeCount} charge locations...");

        $bar = $this->output->createProgressBar($chargeCount);
        $bar->start();

        $chargesQuery->chunkById(50, function ($charges) use ($bar) {
            foreach ($charges as $charge) {
                $charge->address = $this->resolve($charge->latitude, $charge->longitude);
                $charge->save();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done! API calls: {$this->apiCalls}, cache hits: {$this->cacheHits}, place matches: {$this->placeHits}");

        return self::SUCCESS;
    }

    /**
     * Resolve an address: try Place match first, then coordinate cache, then Nominatim.
     */
    private function resolve(float $lat, float $lng): ?string
    {
        // 1. Check saved Places
        $place = $this->findMatchingPlace($lat, $lng);
        if ($place) {
            $this->placeHits++;

            return $place->name;
        }

        // 2. Check coordinate cache (~50m grid)
        $cacheKey = $this->cacheKey($lat, $lng);
        if (array_key_exists($cacheKey, $this->geocodeCache)) {
            $this->cacheHits++;

            return $this->geocodeCache[$cacheKey];
        }

        // 3. Call Nominatim
        $address = $this->reverseGeocode($lat, $lng);
        $this->geocodeCache[$cacheKey] = $address;

        return $address;
    }

    /**
     * Round coordinates to ~50m precision for cache grouping.
     * 0.00045° latitude ≈ 50m; longitude scaled by cos(lat).
     */
    private function cacheKey(float $lat, float $lng): string
    {
        $latStep = 0.00045;
        $lngStep = 0.00045 / max(cos(deg2rad($lat)), 0.01);

        $roundedLat = round($lat / $latStep) * $latStep;
        $roundedLng = round($lng / $lngStep) * $lngStep;

        return sprintf('%.5f,%.5f', $roundedLat, $roundedLng);
    }

    private function findMatchingPlace(float $lat, float $lng): ?Place
    {
        $best = null;
        $bestDist = PHP_FLOAT_MAX;

        foreach ($this->places as $place) {
            $dist = $this->haversineMeters($lat, $lng, $place->latitude, $place->longitude);
            if ($dist <= $place->radius_meters && $dist < $bestDist) {
                $best = $place;
                $bestDist = $dist;
            }
        }

        return $best;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function reverseGeocode(float $lat, float $lng): ?string
    {
        // Nominatim requires a 1 second delay between requests
        usleep(1100000);
        $this->apiCalls++;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Teslog/1.0 (self-hosted Tesla logger)',
            ])->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'jsonv2',
                'zoom' => 18,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->formatAddress($data);
            }
        } catch (\Exception $e) {
            $this->warn("  Geocoding failed for {$lat},{$lng}: {$e->getMessage()}");
        }

        return null;
    }

    private function formatAddress(array $data): ?string
    {
        if (! isset($data['address'])) {
            return $data['display_name'] ?? null;
        }

        $addr = $data['address'];

        // Build a concise address
        $parts = [];

        // Street-level
        $street = $addr['road'] ?? $addr['pedestrian'] ?? $addr['footway'] ?? null;
        $number = $addr['house_number'] ?? null;
        if ($street) {
            $parts[] = $number ? "{$number} {$street}" : $street;
        }

        // City/town
        $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['hamlet'] ?? null;
        if ($city) {
            $parts[] = $city;
        }

        // State
        $state = $addr['state'] ?? null;
        if ($state) {
            $parts[] = $state;
        }

        if (empty($parts)) {
            return $data['display_name'] ?? null;
        }

        return implode(', ', $parts);
    }
}
