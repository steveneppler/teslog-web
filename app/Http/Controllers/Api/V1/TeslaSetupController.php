<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\TeslaAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeslaSetupController extends Controller
{
    public function __construct(
        protected TeslaAuthService $teslaAuth,
    ) {}

    /**
     * List vehicles from the user's Tesla account using session tokens.
     */
    public function vehicles(Request $request): JsonResponse
    {
        $tokens = $request->session()->get('tesla_tokens');

        if (! $tokens || empty($tokens['access_token'])) {
            return response()->json([
                'message' => 'No Tesla tokens found. Please complete the OAuth flow first.',
            ], 400);
        }

        try {
            $vehicles = $this->teslaAuth->getVehicles($tokens['access_token']);

            return response()->json([
                'data' => $vehicles,
            ]);
        } catch (\RuntimeException $e) {
            Log::error('Failed to fetch vehicles from Tesla', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to fetch vehicles from Tesla.',
            ], 502);
        }
    }

    /**
     * Link a Tesla vehicle to the user's account.
     */
    public function linkVehicle(Request $request): JsonResponse
    {
        $request->validate([
            'tesla_vehicle_id' => 'required|integer',
        ]);

        $tokens = $request->session()->get('tesla_tokens');

        if (! $tokens || empty($tokens['access_token'])) {
            return response()->json([
                'message' => 'No Tesla tokens found. Please complete the OAuth flow first.',
            ], 400);
        }

        try {
            // Fetch vehicles to find the selected one
            $vehicles = $this->teslaAuth->getVehicles($tokens['access_token']);

            $teslaVehicle = collect($vehicles)->firstWhere('id', $request->input('tesla_vehicle_id'));

            if (! $teslaVehicle) {
                return response()->json([
                    'message' => 'Vehicle not found in your Tesla account.',
                ], 404);
            }

            // Create or update the vehicle record
            $vehicle = Vehicle::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'tesla_vehicle_id' => $teslaVehicle['id'],
                ],
                [
                    'vin' => $teslaVehicle['vin'],
                    'name' => $teslaVehicle['display_name'] ?? $teslaVehicle['vin'],
                    'tesla_access_token' => $tokens['access_token'],
                    'tesla_refresh_token' => $tokens['refresh_token'],
                    'tesla_token_expires_at' => now()->addSeconds($tokens['expires_in']),
                    'is_active' => true,
                ],
            );

            // Configure fleet telemetry for the vehicle
            try {
                $this->teslaAuth->configureFleetTelemetry(
                    $tokens['access_token'],
                    $teslaVehicle['vin'],
                );
            } catch (\RuntimeException $e) {
                Log::warning('Fleet telemetry configuration failed for VIN ' . $teslaVehicle['vin'], [
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'Vehicle linked successfully.',
                'data' => $vehicle->only(['id', 'tesla_vehicle_id', 'vin', 'name', 'is_active']),
            ], 201);
        } catch (\RuntimeException $e) {
            Log::error('Failed to link vehicle', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to link vehicle.',
            ], 502);
        }
    }

    /**
     * Refresh Tesla tokens for a vehicle.
     */
    public function refreshTokens(Request $request, Vehicle $vehicle): JsonResponse
    {
        // Ensure the vehicle belongs to the authenticated user
        if ($vehicle->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $vehicle->tesla_refresh_token) {
            return response()->json([
                'message' => 'No refresh token available for this vehicle.',
            ], 400);
        }

        try {
            $tokens = $this->teslaAuth->refreshToken($vehicle->tesla_refresh_token);

            $vehicle->update([
                'tesla_access_token' => $tokens['access_token'],
                'tesla_refresh_token' => $tokens['refresh_token'],
                'tesla_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            ]);

            return response()->json([
                'message' => 'Tokens refreshed successfully.',
                'data' => [
                    'expires_at' => $vehicle->fresh()->tesla_token_expires_at->toIso8601String(),
                ],
            ]);
        } catch (\RuntimeException $e) {
            Log::error('Failed to refresh Tesla tokens', ['vehicle_id' => $vehicle->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to refresh tokens.',
            ], 502);
        }
    }

    /**
     * Unlink a Tesla vehicle (remove tokens and disable telemetry).
     */
    public function unlinkVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        // Ensure the vehicle belongs to the authenticated user
        if ($vehicle->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Attempt to disable fleet telemetry if we have valid tokens
        if ($vehicle->tesla_access_token && $vehicle->vin) {
            try {
                $this->teslaAuth->disableFleetTelemetry(
                    $vehicle->tesla_access_token,
                    $vehicle->vin,
                );
            } catch (\RuntimeException $e) {
                Log::warning('Failed to disable fleet telemetry for VIN ' . $vehicle->vin, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $vehicle->update([
            'tesla_access_token' => null,
            'tesla_refresh_token' => null,
            'tesla_token_expires_at' => null,
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Vehicle unlinked successfully.',
        ]);
    }
}
