<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Drive;
use App\Models\DrivePoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');

        $query = Drive::whereIn('vehicle_id', $vehicleIds)
            ->with('vehicle', 'startPlace', 'endPlace')
            ->orderByDesc('started_at');

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }
        if ($request->has('tag')) {
            $query->where('tag', $request->input('tag'));
        }
        if ($request->has('from')) {
            $query->where('started_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('started_at', '<=', $request->input('to'));
        }

        return response()->json($query->paginate(min((int) $request->input('per_page', 25), 100)));
    }

    public function show(Request $request, Drive $drive): JsonResponse
    {
        $this->authorize($request, $drive);
        $drive->load('vehicle', 'startPlace', 'endPlace');

        return response()->json($drive);
    }

    public function update(Request $request, Drive $drive): JsonResponse
    {
        $this->authorize($request, $drive);

        $validated = $request->validate([
            'tag' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $drive->update($validated);

        return response()->json($drive);
    }

    public function points(Request $request, Drive $drive): JsonResponse
    {
        $this->authorize($request, $drive);

        return response()->json($drive->points);
    }

    /**
     * Return sampled route coordinates for drives matching the given filters.
     * Response: array of { drive_id, coords: [[lat, lng], ...] }
     */
    public function routes(Request $request): JsonResponse
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');

        $query = Drive::whereIn('vehicle_id', $vehicleIds);

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }
        if ($request->has('from')) {
            $query->where('started_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('started_at', '<=', $request->input('to'));
        }

        $driveIds = $query->pluck('id');

        if ($driveIds->isEmpty()) {
            return response()->json([]);
        }

        $maxPoints = 10000;
        $totalPointCount = DrivePoint::whereIn('drive_id', $driveIds)->count();
        $nth = max(1, (int) ceil($totalPointCount / $maxPoints));

        if ($nth === 1) {
            $points = DrivePoint::whereIn('drive_id', $driveIds)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('timestamp')
                ->get(['drive_id', 'latitude', 'longitude'])
                ->groupBy('drive_id');
        } else {
            $placeholders = $driveIds->map(fn () => '?')->implode(',');
            $raw = DB::select("
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

            $points = collect($raw)->groupBy('drive_id');
        }

        $routes = $points->map(function ($pts, $driveId) {
            return [
                'drive_id' => (int) $driveId,
                'coords' => $pts->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])->values(),
            ];
        })->values();

        return response()->json($routes);
    }

    private function authorize(Request $request, Drive $drive): void
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');
        if (! $vehicleIds->contains($drive->vehicle_id)) {
            abort(403);
        }
    }
}
