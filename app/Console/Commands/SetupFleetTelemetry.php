<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\TeslaAuthService;
use Illuminate\Console\Command;

class SetupFleetTelemetry extends Command
{
    protected $signature = 'teslog:fleet-telemetry
        {--register-partner : Register as a Tesla partner (one-time setup)}
        {--vin= : Configure a specific vehicle VIN (default: all active vehicles)}
        {--no-location : Exclude GPS location fields (use if vehicle_location scope is missing)}';

    protected $description = 'Register partner and configure Fleet Telemetry for vehicles';

    public function handle(TeslaAuthService $teslaAuth): int
    {
        if ($this->option('register-partner')) {
            $this->info('Registering as Tesla partner...');
            try {
                $result = $teslaAuth->registerPartner();
                if (($result['status'] ?? '') === 'already_registered') {
                    $this->info('Already registered as a partner.');
                } else {
                    $this->info('Partner registration successful: ' . json_encode($result));
                }
            } catch (\Exception $e) {
                $this->error('Partner registration failed: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        // Find vehicles to configure
        $vin = $this->option('vin');
        if ($vin) {
            $vehicles = Vehicle::where('vin', $vin)->get();
        } else {
            $vehicles = Vehicle::where('is_active', true)
                ->whereNotNull('tesla_access_token')
                ->get();
        }

        if ($vehicles->isEmpty()) {
            $this->warn('No vehicles found to configure.');

            return self::SUCCESS;
        }

        $hostname = config('tesla.fleet_telemetry.hostname');
        $port = config('tesla.fleet_telemetry.port');
        $this->info("Configuring Fleet Telemetry → {$hostname}:{$port}");
        $this->newLine();

        foreach ($vehicles as $vehicle) {
            $this->info("Vehicle: {$vehicle->name} ({$vehicle->vin})");

            if (! $vehicle->tesla_access_token) {
                $this->warn('  Skipped — no access token.');

                continue;
            }

            try {
                // Refresh token if needed
                if ($vehicle->tesla_token_expires_at?->lt(now()->addMinutes(5))) {
                    $this->line('  Refreshing token...');
                    $tokens = $teslaAuth->refreshToken($vehicle->tesla_refresh_token);
                    $vehicle->update([
                        'tesla_access_token' => $tokens['access_token'],
                        'tesla_refresh_token' => $tokens['refresh_token'],
                        'tesla_token_expires_at' => now()->addSeconds($tokens['expires_in']),
                    ]);
                    $vehicle->refresh();
                }

                $includeLocation = ! $this->option('no-location');
                $result = $teslaAuth->configureFleetTelemetry(
                    $vehicle->tesla_access_token,
                    $vehicle->vin,
                    $includeLocation,
                );

                $this->info('  Fleet Telemetry configured successfully.');
                $this->line('  Response: ' . json_encode($result));
            } catch (\Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
