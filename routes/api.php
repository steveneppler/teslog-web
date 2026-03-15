<?php

use App\Http\Controllers\Api\TelemetryIngestController;
use App\Http\Controllers\Api\V1\TeslaSetupController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BatteryController;
use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\CommandController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DriveController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\FirmwareController;
use App\Http\Controllers\Api\V1\IdleController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

// Internal telemetry endpoint (fleet-telemetry → app)
Route::post('telemetry/ingest', [TelemetryIngestController::class, 'ingest'])
    ->middleware('telemetry-auth');

// Public endpoints
Route::prefix('v1')->group(function () {
    Route::get('system/status', [SystemController::class, 'status']);
    Route::get('system/setup', [SystemController::class, 'setupStatus']);

    // Auth
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('first-run-only');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Protected endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/user', [AuthController::class, 'user']);
        Route::get('auth/tokens', [AuthController::class, 'tokens']);
        Route::delete('auth/tokens/{tokenId}', [AuthController::class, 'deleteToken']);

        // Vehicles
        Route::apiResource('vehicles', VehicleController::class);
        Route::get('vehicles/{vehicle}/status', [VehicleController::class, 'currentStatus']);
        Route::get('vehicles/{vehicle}/timeline', [VehicleController::class, 'timeline']);

        // Drives
        Route::get('drives/routes', [DriveController::class, 'routes']);
        Route::apiResource('drives', DriveController::class)->only(['index', 'show', 'update']);
        Route::get('drives/{drive}/points', [DriveController::class, 'points']);

        // Charges
        Route::apiResource('charges', ChargeController::class)->only(['index', 'show', 'update']);
        Route::get('charges/{charge}/points', [ChargeController::class, 'points']);

        // Idles
        Route::apiResource('idles', IdleController::class)->only(['index', 'show']);

        // Places
        Route::apiResource('places', PlaceController::class);

        // Battery & Firmware
        Route::get('vehicles/{vehicle}/battery-health', [BatteryController::class, 'index']);
        Route::get('vehicles/{vehicle}/firmware-history', [FirmwareController::class, 'index']);

        // Commands
        Route::post('vehicles/{vehicle}/commands/{command}', [CommandController::class, 'execute'])
            ->middleware('throttle:vehicle-commands');

        // Dashboard & Analytics
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('analytics/efficiency', [AnalyticsController::class, 'efficiency']);
        Route::get('analytics/energy', [AnalyticsController::class, 'energy']);
        Route::get('analytics/cost', [AnalyticsController::class, 'cost']);
        Route::get('analytics/calendar', [AnalyticsController::class, 'calendar']);

        // Export
        Route::get('export/drives', [ExportController::class, 'drives']);
        Route::get('export/charges', [ExportController::class, 'charges']);

        // Tesla Setup
        Route::get('tesla/vehicles', [TeslaSetupController::class, 'vehicles']);
        Route::post('tesla/link-vehicle', [TeslaSetupController::class, 'linkVehicle']);
        Route::post('vehicles/{vehicle}/tesla/refresh-tokens', [TeslaSetupController::class, 'refreshTokens']);
        Route::delete('vehicles/{vehicle}/tesla/unlink', [TeslaSetupController::class, 'unlinkVehicle']);
    });
});
