<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Services\TeslaAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTelemetryHealth extends Command
{
    protected $signature = 'teslog:check-health';

    protected $description = 'Check telemetry pipeline health and auto-fix issues';

    public function handle(TeslaAuthService $teslaAuth): int
    {
        $warnings = [];

        $vehicles = Vehicle::where('is_active', true)
            ->whereNotNull('tesla_access_token')
            ->get();

        foreach ($vehicles as $vehicle) {
            $vehicleWarnings = [];

            // Check token expiry
            if ($vehicle->tesla_token_expires_at && $vehicle->tesla_token_expires_at->isPast()) {
                $vehicleWarnings[] = [
                    'type' => 'token_expired',
                    'severity' => 'critical',
                    'message' => 'Tesla API token has expired. Token refresh may have failed.',
                ];
            } elseif ($vehicle->tesla_token_expires_at && $vehicle->tesla_token_expires_at->lt(now()->addMinutes(30))) {
                $vehicleWarnings[] = [
                    'type' => 'token_expiring',
                    'severity' => 'warning',
                    'message' => 'Tesla API token expires in ' . $vehicle->tesla_token_expires_at->diffForHumans() . '.',
                ];
            }

            // Check telemetry data freshness
            $latestState = VehicleState::where('vehicle_id', $vehicle->id)
                ->orderByDesc('timestamp')
                ->first();

            if (! $latestState) {
                $vehicleWarnings[] = [
                    'type' => 'no_data',
                    'severity' => 'critical',
                    'message' => 'No telemetry data has ever been received.',
                ];
            } elseif ($latestState->timestamp->lt(now()->subHours(6))
                && ! in_array($latestState->state, ['sleeping', 'idle'])) {
                $hoursAgo = round($latestState->timestamp->diffInHours(now()));
                $vehicleWarnings[] = [
                    'type' => 'stale_data',
                    'severity' => $hoursAgo > 12 ? 'critical' : 'warning',
                    'message' => "No telemetry data received in {$hoursAgo} hours.",
                    'last_seen' => $latestState->timestamp->toIso8601String(),
                ];
            }

            // Check fleet telemetry config on Tesla's side
            if ($vehicle->tesla_access_token && (! $vehicle->tesla_token_expires_at || ! $vehicle->tesla_token_expires_at->isPast())) {
                try {
                    $apiUrl = config('tesla.api_url', 'https://fleet-api.prd.na.vn.cloud.tesla.com');
                    $response = Http::withToken($vehicle->tesla_access_token)
                        ->timeout(10)
                        ->get("{$apiUrl}/api/1/vehicles/{$vehicle->vin}/fleet_telemetry_config");

                    if ($response->successful()) {
                        $config = $response->json('response.config');
                        if (! $config) {
                            $vehicleWarnings[] = [
                                'type' => 'telemetry_not_configured',
                                'severity' => 'critical',
                                'message' => 'Fleet Telemetry config was cleared by Tesla. Attempting to re-push...',
                            ];

                            // Auto-fix: re-push the config
                            try {
                                $teslaAuth->configureFleetTelemetry(
                                    $vehicle->tesla_access_token,
                                    $vehicle->vin,
                                );
                                $this->info("Re-pushed fleet telemetry config for {$vehicle->name}");
                                // Update the warning to show it was fixed
                                $lastWarning = &$vehicleWarnings[array_key_last($vehicleWarnings)];
                                $lastWarning['message'] = 'Fleet Telemetry config was cleared by Tesla. Auto-reconfigured successfully.';
                                $lastWarning['severity'] = 'info';
                            } catch (\Exception $e) {
                                Log::error("Failed to re-push fleet telemetry config for {$vehicle->name}: {$e->getMessage()}");
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Don't warn on transient API failures
                    Log::debug("Fleet telemetry config check failed for {$vehicle->name}: {$e->getMessage()}");
                }
            }

            if (! empty($vehicleWarnings)) {
                $warnings[$vehicle->id] = [
                    'vehicle_name' => $vehicle->name ?: $vehicle->vin,
                    'warnings' => $vehicleWarnings,
                ];
            }
        }

        // Detect idle → sleeping transitions
        // Fleet Telemetry stops sending data when the car sleeps, so we detect it by timeout
        foreach ($vehicles as $vehicle) {
            $latestState = VehicleState::where('vehicle_id', $vehicle->id)
                ->orderByDesc('timestamp')
                ->first();

            if ($latestState
                && $latestState->state === 'idle'
                && $latestState->timestamp->lt(now()->subMinutes(15))) {
                $sleepState = VehicleState::create([
                    'vehicle_id' => $vehicle->id,
                    'timestamp' => now(),
                    'state' => 'sleeping',
                    'battery_level' => $latestState->battery_level,
                    'rated_range' => $latestState->rated_range,
                    'latitude' => $latestState->latitude,
                    'longitude' => $latestState->longitude,
                    'odometer' => $latestState->odometer,
                    'inside_temp' => $latestState->inside_temp,
                    'outside_temp' => $latestState->outside_temp,
                    'locked' => $latestState->locked,
                    'sentry_mode' => $latestState->sentry_mode,
                    'software_version' => $latestState->software_version,
                ]);
                $vehicle->updateQuietly(['latest_state_id' => $sleepState->id]);
                $this->info("Marked {$vehicle->name} as sleeping (idle for " . (int) $latestState->timestamp->diffInMinutes(now()) . " min)");
            }
        }

        // Check system health
        $systemWarnings = [];

        // Check fleet telemetry pipeline
        $isDocker = file_exists('/.dockerenv');

        if ($isDocker) {
            // In Docker, fleet-telemetry runs in a separate container
            $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
            $fp = @stream_socket_client('ssl://fleet-telemetry:4443', $errno, $errstr, 3, STREAM_CLIENT_CONNECT, $ctx);
            if ($fp) {
                fclose($fp);
            } else {
                $systemWarnings[] = [
                    'type' => 'fleet_telemetry_down',
                    'severity' => 'critical',
                    'message' => 'Fleet Telemetry container is not reachable on port 4443.',
                ];
            }
        } else {
            // Local dev — check for local processes
            $fleetTelemetryRunning = ! empty(trim(shell_exec('pgrep -f fleet-telemetry 2>/dev/null') ?? ''));
            if (! $fleetTelemetryRunning) {
                $systemWarnings[] = [
                    'type' => 'fleet_telemetry_down',
                    'severity' => 'critical',
                    'message' => 'Fleet Telemetry process is not running. No vehicle data will be received. Run: bin/start-telemetry.sh',
                ];
            }

            $ingestRunning = ! empty(trim(shell_exec('pgrep -f ingest-telemetry 2>/dev/null') ?? ''));
            if (! $ingestRunning) {
                $systemWarnings[] = [
                    'type' => 'ingest_down',
                    'severity' => 'critical',
                    'message' => 'Telemetry ingest process is not running. Run: bin/start-telemetry.sh',
                ];
            }
        }

        // Check if queue workers are running (look for recent processed jobs)
        $queueTableExists = \Schema::hasTable('jobs');
        if ($queueTableExists) {
            $pendingJobs = \DB::table('jobs')->count();
            $oldestJob = \DB::table('jobs')->min('created_at');
            if ($oldestJob && now()->diffInMinutes(\Carbon\Carbon::parse($oldestJob)) > 10) {
                $systemWarnings[] = [
                    'type' => 'queue_stalled',
                    'severity' => 'critical',
                    'message' => "Queue has {$pendingJobs} pending jobs. Oldest is " . now()->diffForHumans(\Carbon\Carbon::parse($oldestJob), true) . ' old. Queue workers may not be running.',
                ];
            }
        }

        // Check if scheduler is running (we'll track last run in cache)
        Cache::put('teslog:scheduler_last_run', now()->toIso8601String(), 3600);

        // Store health status in cache for dashboard
        Cache::put('teslog:health', [
            'checked_at' => now()->toIso8601String(),
            'vehicles' => $warnings,
            'system' => $systemWarnings,
        ], 900); // 15 min TTL

        // Output
        $totalWarnings = collect($warnings)->sum(fn ($v) => count($v['warnings'])) + count($systemWarnings);
        if ($totalWarnings === 0) {
            $this->info('All systems healthy.');
        } else {
            foreach ($warnings as $vehicleData) {
                foreach ($vehicleData['warnings'] as $w) {
                    $method = $w['severity'] === 'critical' ? 'error' : 'warn';
                    $this->$method("[{$vehicleData['vehicle_name']}] {$w['message']}");
                }
            }
            foreach ($systemWarnings as $w) {
                $method = $w['severity'] === 'critical' ? 'error' : 'warn';
                $this->$method("[System] {$w['message']}");
            }
        }

        return self::SUCCESS;
    }
}
