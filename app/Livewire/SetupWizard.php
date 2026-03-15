<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Services\TeslaAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class SetupWizard extends Component
{
    public int $step = 1;

    public array $vehicles = [];

    public array $selectedVehicles = [];

    public array $updatedVehicleNames = [];

    public bool $linking = false;

    public ?string $error = null;

    public ?string $telemetryError = null;

    public function mount(): void
    {
        $tokens = session('tesla_tokens');

        if ($tokens) {
            $this->fetchVehicles($tokens['access_token']);

            // Update tokens on existing vehicles
            $existingVehicles = Vehicle::where('user_id', Auth::id())
                ->whereNotNull('tesla_vehicle_id')
                ->get();

            if ($existingVehicles->isNotEmpty()) {
                foreach ($existingVehicles as $vehicle) {
                    $vehicle->update([
                        'tesla_access_token' => $tokens['access_token'],
                        'tesla_refresh_token' => $tokens['refresh_token'],
                        'tesla_token_expires_at' => now()->addSeconds($tokens['expires_in']),
                    ]);
                }
                $this->updatedVehicleNames = $existingVehicles->pluck('name')->all();
            }

            // Filter out already-linked vehicles
            $existingTeslaIds = $existingVehicles->pluck('tesla_vehicle_id')->map(fn ($id) => (int) $id)->all();
            $this->vehicles = array_values(array_filter($this->vehicles, fn ($v) => ! in_array((int) $v['id'], $existingTeslaIds, true)));

            $this->step = 2;
        }
    }

    public function linkVehicles(): void
    {
        if (empty($this->selectedVehicles)) {
            $this->error = 'Please select at least one vehicle.';

            return;
        }

        $this->linking = true;
        $this->error = null;

        $tokens = session('tesla_tokens');

        if (! $tokens) {
            $this->error = 'Tesla session expired. Please reconnect your account.';
            $this->step = 1;
            $this->linking = false;

            return;
        }

        try {
            $teslaAuth = app(TeslaAuthService::class);

            foreach ($this->selectedVehicles as $vehicleId) {
                $teslaVehicle = collect($this->vehicles)->firstWhere('id', (int) $vehicleId);

                if (! $teslaVehicle) {
                    continue;
                }

                $vehicle = Vehicle::updateOrCreate(
                    [
                        'user_id' => Auth::id(),
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

                try {
                    $teslaAuth->configureFleetTelemetry(
                        $tokens['access_token'],
                        $teslaVehicle['vin'],
                    );
                } catch (\RuntimeException $e) {
                    Log::warning('Fleet telemetry config failed for VIN ' . $teslaVehicle['vin'], [
                        'error' => $e->getMessage(),
                    ]);
                    $this->telemetryError = $e->getMessage();
                }
            }

            session()->forget('tesla_tokens');
            $this->step = 3;
        } catch (\Exception $e) {
            Log::error('Failed to link vehicles', ['error' => $e->getMessage()]);
            $this->error = 'An error occurred while linking vehicles. Please try again.';
        } finally {
            $this->linking = false;
        }
    }

    public function render()
    {
        return view('livewire.setup-wizard');
    }

    protected function fetchVehicles(string $accessToken): void
    {
        try {
            $teslaAuth = app(TeslaAuthService::class);

            // Register as partner (required once per region, idempotent)
            try {
                $teslaAuth->registerPartner();
            } catch (\RuntimeException $e) {
                Log::warning('Partner registration failed (may already be registered)', [
                    'error' => $e->getMessage(),
                ]);
            }

            $this->vehicles = $teslaAuth->getVehicles($accessToken);
        } catch (\Exception $e) {
            Log::error('Failed to fetch vehicles from Tesla', ['error' => $e->getMessage()]);
            $this->error = 'Failed to fetch vehicles from Tesla. Please try again.';
        }
    }
}
