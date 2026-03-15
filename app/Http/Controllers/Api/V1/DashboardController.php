<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Drive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');
        $thirtyDaysAgo = now()->subDays(30);

        $vehicles = $request->user()->vehicles()
            ->where('is_active', true)
            ->with('latestState')
            ->get();

        $recentDrives = Drive::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', $thirtyDaysAgo)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        $recentCharges = Charge::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', $thirtyDaysAgo)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        $stats = [
            'total_drives_30d' => Drive::whereIn('vehicle_id', $vehicleIds)->where('started_at', '>=', $thirtyDaysAgo)->count(),
            'total_distance_30d' => Drive::whereIn('vehicle_id', $vehicleIds)->where('started_at', '>=', $thirtyDaysAgo)->sum('distance'),
            'total_energy_used_30d' => Drive::whereIn('vehicle_id', $vehicleIds)->where('started_at', '>=', $thirtyDaysAgo)->sum('energy_used_kwh'),
            'total_energy_added_30d' => Charge::whereIn('vehicle_id', $vehicleIds)->where('started_at', '>=', $thirtyDaysAgo)->sum('energy_added_kwh'),
            'total_charge_cost_30d' => Charge::whereIn('vehicle_id', $vehicleIds)->where('started_at', '>=', $thirtyDaysAgo)->sum('cost'),
        ];

        return response()->json([
            'vehicles' => $vehicles,
            'recent_drives' => $recentDrives,
            'recent_charges' => $recentCharges,
            'stats' => $stats,
        ]);
    }
}
