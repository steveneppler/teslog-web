<?php

namespace App\Livewire\Concerns;

use App\Models\DrivePoint;
use Illuminate\Support\Facades\DB;

trait SamplesDrivePoints
{
    /**
     * Load drive points sampled to stay under $maxPoints total, keyed by drive_id.
     * Always keeps first and last point per drive for accurate start/end.
     */
    private function loadSampledPoints($driveIds, int $maxPoints = 10000): \Illuminate\Support\Collection
    {
        if ($driveIds->isEmpty()) {
            return collect();
        }

        $totalPointCount = DrivePoint::whereIn('drive_id', $driveIds)->count();
        $nth = max(1, (int) ceil($totalPointCount / $maxPoints));

        if ($nth === 1) {
            // No sampling needed — load all
            return DrivePoint::whereIn('drive_id', $driveIds)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('timestamp')
                ->get(['drive_id', 'latitude', 'longitude'])
                ->groupBy('drive_id');
        }

        $placeholders = $driveIds->map(fn () => '?')->implode(',');
        $sampledPoints = DB::select("
            SELECT drive_id, latitude, longitude FROM (
                SELECT drive_id, latitude, longitude,
                    ROW_NUMBER() OVER (PARTITION BY drive_id ORDER BY timestamp) as rn,
                    COUNT(*) OVER (PARTITION BY drive_id) as total
                FROM drive_points
                WHERE drive_id IN ({$placeholders})
                AND latitude IS NOT NULL AND longitude IS NOT NULL
            ) sub
            WHERE rn = 1 OR rn = total OR rn % ? = 0
        ", [...$driveIds->all(), $nth]);

        return collect($sampledPoints)->groupBy('drive_id');
    }
}
