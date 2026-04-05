<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');

        $query = Charge::whereIn('vehicle_id', $vehicleIds)
            ->with('vehicle', 'place')
            ->orderByDesc('started_at');

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }
        if ($request->has('charge_type')) {
            $query->where('charge_type', $request->input('charge_type'));
        }
        if ($request->has('from')) {
            $query->where('started_at', '>=', Carbon::parse($request->input('from'))->utc());
        }
        if ($request->has('to')) {
            $query->where('started_at', '<=', Carbon::parse($request->input('to'))->utc());
        }

        return response()->json($query->paginate(min((int) $request->input('per_page', 25), 100)));
    }

    public function show(Request $request, Charge $charge): JsonResponse
    {
        $this->authorize($request, $charge);
        $charge->load('vehicle', 'place');

        return response()->json($charge);
    }

    public function update(Request $request, Charge $charge): JsonResponse
    {
        $this->authorize($request, $charge);

        $validated = $request->validate([
            'tag' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $charge->update($validated);

        return response()->json($charge);
    }

    public function points(Request $request, Charge $charge): JsonResponse
    {
        $this->authorize($request, $charge);

        return response()->json($charge->points);
    }

    private function authorize(Request $request, Charge $charge): void
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');
        if (! $vehicleIds->contains($charge->vehicle_id)) {
            abort(403);
        }
    }
}
