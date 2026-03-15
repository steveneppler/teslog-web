<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Idle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');

        $query = Idle::whereIn('vehicle_id', $vehicleIds)
            ->with('vehicle', 'place')
            ->orderByDesc('started_at');

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        return response()->json($query->paginate(min((int) $request->input('per_page', 25), 100)));
    }

    public function show(Request $request, Idle $idle): JsonResponse
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');
        if (! $vehicleIds->contains($idle->vehicle_id)) {
            abort(403);
        }

        $idle->load('vehicle', 'place');

        return response()->json($idle);
    }
}
