<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicles = $request->user()->vehicles()
            ->with('latestState')
            ->get();

        return response()->json($vehicles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'vin' => ['nullable', 'string', 'max:17'],
            'tesla_vehicle_id' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'trim' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
        ]);

        $vehicle = $request->user()->vehicles()->create($validated);

        return response()->json($vehicle, 201);
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicle($request, $vehicle);

        $vehicle->load('latestState');

        return response()->json($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicle($request, $vehicle);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $vehicle->update($validated);

        return response()->json($vehicle);
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicle($request, $vehicle);

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted']);
    }

    public function currentStatus(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicle($request, $vehicle);

        $state = $vehicle->latestState;

        return response()->json($state);
    }

    public function timeline(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeVehicle($request, $vehicle);

        $states = $vehicle->states()
            ->orderByDesc('timestamp')
            ->limit(min((int) $request->input('limit', 100), 1000))
            ->get();

        return response()->json($states);
    }

    private function authorizeVehicle(Request $request, Vehicle $vehicle): void
    {
        if ($vehicle->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }
}
