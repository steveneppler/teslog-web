<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $places = $request->user()->places()->with('touRates')->get();

        return response()->json($places);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['integer', 'min:10', 'max:5000'],
            'electricity_cost_per_kwh' => ['nullable', 'numeric', 'min:0'],
            'auto_tag' => ['nullable', 'string', 'max:100'],
        ]);

        $place = $request->user()->places()->create($validated);

        return response()->json($place, 201);
    }

    public function show(Request $request, Place $place): JsonResponse
    {
        $this->authorize($request, $place);
        $place->load('touRates');

        return response()->json($place);
    }

    public function update(Request $request, Place $place): JsonResponse
    {
        $this->authorize($request, $place);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'integer', 'min:10', 'max:5000'],
            'electricity_cost_per_kwh' => ['nullable', 'numeric', 'min:0'],
            'auto_tag' => ['nullable', 'string', 'max:100'],
        ]);

        $place->update($validated);

        return response()->json($place);
    }

    public function destroy(Request $request, Place $place): JsonResponse
    {
        $this->authorize($request, $place);
        $place->delete();

        return response()->json(['message' => 'Place deleted']);
    }

    private function authorize(Request $request, Place $place): void
    {
        if ($place->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
