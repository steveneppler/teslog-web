<?php

namespace App\Services;

use App\Models\CommandLog;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeslaCommandService
{
    public function execute(Vehicle $vehicle, string $command, array $params = [], ?int $userId = null): array
    {
        $token = $vehicle->tesla_access_token;
        if (! $token) {
            return ['success' => false, 'error' => 'No Tesla token configured'];
        }

        // Auto-refresh expired token
        if ($vehicle->tesla_token_expires_at?->lt(now()->addMinutes(5))) {
            try {
                $token = $this->refreshVehicleToken($vehicle);
            } catch (\Exception $e) {
                Log::warning('Token refresh failed before command', [
                    'vehicle_id' => $vehicle->id,
                    'error' => $e->getMessage(),
                ]);

                return ['success' => false, 'error' => 'Token expired and refresh failed'];
            }
        }

        $endpoint = $this->getEndpoint($command);
        if (! $endpoint) {
            return ['success' => false, 'error' => 'Unknown command'];
        }

        try {
            $proxyUrl = config('tesla.command_proxy_url', 'https://localhost:4430');

            // Wake up the vehicle before sending commands
            $this->wakeUp($vehicle, $token, $proxyUrl);

            $url = $proxyUrl . '/api/1/vehicles/' . $vehicle->vin . $endpoint['path'];

            $body = array_merge($endpoint['default_params'] ?? [], $params);

            $response = Http::withToken($token)
                ->withOptions(['verify' => false])
                ->timeout(30)
                ->asJson()
                ->post($url, (object) $body);

            if ($response->successful()) {
                $data = $response->json();

                $result = [
                    'success' => true,
                    'result' => $data['response'] ?? $data,
                ];
                $this->logCommand($vehicle, $command, $params, $result, $userId);

                return $result;
            }

            Log::warning('Tesla command failed', [
                'vehicle_id' => $vehicle->id,
                'command' => $command,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $result = [
                'success' => false,
                'error' => 'Command failed',
                'status' => $response->status(),
            ];
            $this->logCommand($vehicle, $command, $params, $result, $userId);

            return $result;
        } catch (\Exception $e) {
            Log::error('Tesla command exception', [
                'vehicle_id' => $vehicle->id,
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            $result = ['success' => false, 'error' => 'Connection error'];
            $this->logCommand($vehicle, $command, $params, $result, $userId);

            return $result;
        }
    }

    private function logCommand(Vehicle $vehicle, string $command, array $params, array $result, ?int $userId): void
    {
        if (! $userId) {
            return;
        }

        CommandLog::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $userId,
            'command' => $command,
            'parameters' => ! empty($params) ? $params : null,
            'success' => $result['success'],
            'error_message' => $result['error'] ?? null,
            'executed_at' => now(),
        ]);
    }

    /**
     * Refresh the vehicle's Tesla access token.
     */
    public function refreshVehicleToken(Vehicle $vehicle): string
    {
        $teslaAuth = app(TeslaAuthService::class);
        $tokens = $teslaAuth->refreshToken($vehicle->tesla_refresh_token);

        $vehicle->update([
            'tesla_access_token' => $tokens['access_token'],
            'tesla_refresh_token' => $tokens['refresh_token'],
            'tesla_token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);

        return $tokens['access_token'];
    }

    private function wakeUp(Vehicle $vehicle, string $token, string $proxyUrl): void
    {
        // Skip wake-up if the car is already online based on recent telemetry
        $latestState = $vehicle->latestState;
        if ($latestState
            && $latestState->timestamp->gt(now()->subMinutes(2))
            && ! in_array($latestState->state, ['sleeping', 'offline'])) {
            return;
        }

        $apiUrl = config('tesla.api_url', 'https://fleet-api.prd.na.vn.cloud.tesla.com');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post("{$apiUrl}/api/1/vehicles/{$vehicle->vin}/wake_up");

            $state = $response->json('response.state');
            if ($state === 'online') {
                return;
            }

            sleep(2);
        }
    }

    private function getEndpoint(string $command): ?array
    {
        $endpoints = [
            'lock' => ['path' => '/command/door_lock'],
            'unlock' => ['path' => '/command/door_unlock'],
            'honk_horn' => ['path' => '/command/honk_horn'],
            'flash_lights' => ['path' => '/command/flash_lights'],
            'climate_on' => ['path' => '/command/auto_conditioning_start'],
            'climate_off' => ['path' => '/command/auto_conditioning_stop'],
            'set_temps' => ['path' => '/command/set_temps'],
            'charge_start' => ['path' => '/command/charge_start'],
            'charge_stop' => ['path' => '/command/charge_stop'],
            'charge_port_open' => ['path' => '/command/charge_port_door_open'],
            'charge_port_close' => ['path' => '/command/charge_port_door_close'],
            'set_charge_limit' => ['path' => '/command/set_charge_limit'],
            'sentry_on' => ['path' => '/command/set_sentry_mode', 'default_params' => ['on' => true]],
            'sentry_off' => ['path' => '/command/set_sentry_mode', 'default_params' => ['on' => false]],
            'vent_windows' => ['path' => '/command/window_control', 'default_params' => ['command' => 'vent']],
            'close_windows' => ['path' => '/command/window_control', 'default_params' => ['command' => 'close']],
        ];

        return $endpoints[$command] ?? null;
    }
}
