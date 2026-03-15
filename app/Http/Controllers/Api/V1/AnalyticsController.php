<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Drive;
use App\Models\Idle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private function getVehicleIds(Request $request): array
    {
        $query = $request->user()->vehicles();

        if ($request->filled('vehicle_id')) {
            $query->where('id', $request->input('vehicle_id'));
        }

        return $query->pluck('id')->all();
    }

    public function efficiency(Request $request): JsonResponse
    {
        $vehicleIds = $this->getVehicleIds($request);
        $days = min((int) $request->input('days', 90), 365);

        $data = Drive::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('efficiency')
            ->select(
                DB::raw("date(started_at) as date"),
                DB::raw('avg(efficiency) as avg_efficiency'),
                DB::raw('avg(outside_temp_avg) as avg_temp'),
                DB::raw('count(*) as drive_count'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function energy(Request $request): JsonResponse
    {
        $vehicleIds = $this->getVehicleIds($request);
        $days = min((int) $request->input('days', 30), 365);

        $used = Drive::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($days))
            ->select(
                DB::raw("date(started_at) as date"),
                DB::raw('sum(energy_used_kwh) as energy_used'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $added = Charge::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($days))
            ->select(
                DB::raw("date(started_at) as date"),
                DB::raw('sum(energy_added_kwh) as energy_added'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'energy_used' => $used,
            'energy_added' => $added,
        ]);
    }

    public function cost(Request $request): JsonResponse
    {
        $vehicleIds = $this->getVehicleIds($request);
        $days = min((int) $request->input('days', 90), 365);

        $byType = Charge::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('cost')
            ->select(
                'charge_type',
                DB::raw('sum(cost) as total_cost'),
                DB::raw('sum(energy_added_kwh) as total_energy'),
                DB::raw('count(*) as charge_count'),
            )
            ->groupBy('charge_type')
            ->get();

        $monthly = Charge::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('cost')
            ->select(
                DB::raw("strftime('%Y-%m', started_at) as month"),
                DB::raw('sum(cost) as total_cost'),
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'by_type' => $byType,
            'monthly' => $monthly,
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['sometimes', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $vehicleIds = $this->getVehicleIds($request);
        $month = $request->input('month', now()->format('Y-m'));

        $startOfMonth = $month . '-01';
        $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

        $drives = Drive::whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('started_at', [$startOfMonth, $endOfMonth . ' 23:59:59'])
            ->select(DB::raw("date(started_at) as date"), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $charges = Charge::whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('started_at', [$startOfMonth, $endOfMonth . ' 23:59:59'])
            ->select(DB::raw("date(started_at) as date"), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $idles = Idle::whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('started_at', [$startOfMonth, $endOfMonth . ' 23:59:59'])
            ->select(DB::raw("date(started_at) as date"), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        return response()->json([
            'month' => $month,
            'drives' => $drives,
            'charges' => $charges,
            'idles' => $idles,
        ]);
    }
}
