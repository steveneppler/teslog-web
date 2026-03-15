<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\TeslaCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommandController extends Controller
{
    private const ALLOWED_COMMANDS = [
        'lock',
        'unlock',
        'honk_horn',
        'flash_lights',
        'climate_on',
        'climate_off',
        'set_temps',
        'charge_start',
        'charge_stop',
        'charge_port_open',
        'charge_port_close',
        'set_charge_limit',
        'sentry_on',
        'sentry_off',
        'vent_windows',
        'close_windows',
    ];

    public function execute(Request $request, Vehicle $vehicle, string $command, TeslaCommandService $commandService): JsonResponse
    {
        if ($vehicle->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! in_array($command, self::ALLOWED_COMMANDS)) {
            return response()->json(['error' => 'Unknown command'], 400);
        }

        $params = $request->input('params', []);

        // Audit log
        Log::info('Vehicle command executed', [
            'user_id' => $request->user()->id,
            'vehicle_id' => $vehicle->id,
            'command' => $command,
            'params' => $params,
        ]);

        $result = $commandService->execute($vehicle, $command, $params, $request->user()->id);

        return response()->json($result);
    }
}
