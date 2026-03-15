<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatteryController extends Controller
{
    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        if ($vehicle->user_id !== $request->user()->id) {
            abort(403);
        }

        $health = $vehicle->batteryHealth()
            ->orderByDesc('recorded_at')
            ->limit(min((int) $request->input('limit', 365), 1000))
            ->get();

        return response()->json($health);
    }
}
