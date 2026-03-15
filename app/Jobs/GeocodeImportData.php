<?php

namespace App\Jobs;

use App\Models\Charge;
use App\Models\Drive;
use App\Services\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class GeocodeImportData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1;

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function __construct(
        public int $vehicleId,
    ) {}

    public function handle(GeocodingService $geocoding): void
    {
        set_time_limit(0);

        $drivesCount = Drive::where('vehicle_id', $this->vehicleId)
            ->whereNotNull('start_latitude')->whereNull('start_address')->count();
        $chargesCount = Charge::where('vehicle_id', $this->vehicleId)
            ->whereNotNull('latitude')->whereNull('address')->count();
        $total = $drivesCount + $chargesCount;
        $done = 0;

        if ($total === 0) {
            return;
        }

        $this->updateProgress($done, $total);

        Drive::where('vehicle_id', $this->vehicleId)
            ->whereNotNull('start_latitude')
            ->whereNull('start_address')
            ->chunkById(50, function ($drives) use (&$done, $total, $geocoding) {
                foreach ($drives as $drive) {
                    if ($drive->start_latitude && $drive->start_longitude && ! $drive->start_address) {
                        $drive->start_address = $this->rateLimitedGeocode($geocoding, $drive->start_latitude, $drive->start_longitude);
                    }
                    if ($drive->end_latitude && $drive->end_longitude && ! $drive->end_address) {
                        $drive->end_address = $this->rateLimitedGeocode($geocoding, $drive->end_latitude, $drive->end_longitude);
                    }
                    $drive->save();
                    $done++;

                    if ($done % 5 === 0) {
                        $this->updateProgress($done, $total);
                    }
                }
            });

        Charge::where('vehicle_id', $this->vehicleId)
            ->whereNotNull('latitude')
            ->whereNull('address')
            ->chunkById(50, function ($charges) use (&$done, $total, $geocoding) {
                foreach ($charges as $charge) {
                    $charge->address = $this->rateLimitedGeocode($geocoding, $charge->latitude, $charge->longitude);
                    $charge->save();
                    $done++;

                    if ($done % 5 === 0) {
                        $this->updateProgress($done, $total);
                    }
                }
            });

        Cache::forget("geocode-progress-{$this->vehicleId}");
    }

    private function updateProgress(int $done, int $total): void
    {
        Cache::put("geocode-progress-{$this->vehicleId}", [
            'done' => $done,
            'total' => $total,
        ], 7200);
    }

    /**
     * Rate-limit API calls to respect Nominatim's 1 req/sec policy.
     * Skips the delay when GeocodingService returns a cached result.
     */
    private function rateLimitedGeocode(GeocodingService $geocoding, float $lat, float $lng): ?string
    {
        $cacheKey = 'geocode:' . round($lat, 4) . ':' . round($lng, 4);
        if (! Cache::has($cacheKey)) {
            usleep(1100000);
        }

        return $geocoding->reverse($lat, $lng);
    }
}
