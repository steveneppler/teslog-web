<?php

namespace App\Livewire;

use App\Models\CommandLog;
use App\Models\Vehicle;
use App\Services\TeslaCommandService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class VehicleCommands extends Component
{
    public Vehicle $vehicle;

    public ?string $executingCommand = null;

    public ?string $lastResultMessage = null;

    public ?bool $lastResultSuccess = null;

    public float $driverTemp = 68;

    public float $passengerTemp = 68;

    public int $chargeLimit = 80;

    public bool $usesF = false;

    private const ALLOWED_COMMANDS = [
        'lock', 'unlock', 'honk_horn', 'flash_lights',
        'climate_on', 'climate_off', 'set_temps',
        'charge_start', 'charge_stop', 'charge_port_open', 'charge_port_close', 'set_charge_limit',
        'sentry_on', 'sentry_off', 'vent_windows', 'close_windows',
    ];

    public function mount(Vehicle $vehicle): void
    {
        abort_unless($vehicle->user_id === Auth::id(), 403);

        $this->vehicle = $vehicle;
        $this->usesF = Auth::user()->temperature_unit === 'F';

        // Initialize charge limit from vehicle state
        $vehicle->load('latestState');
        if ($vehicle->latestState?->charge_limit_soc) {
            $this->chargeLimit = (int) $vehicle->latestState->charge_limit_soc;
        }

        // Initialize temps in user's preferred unit
        $defaultC = 20;
        $this->driverTemp = $this->usesF ? round($defaultC * 9 / 5 + 32, 0) : $defaultC;
        $this->passengerTemp = $this->driverTemp;
    }

    public function executeCommand(string $command): void
    {
        if (! in_array($command, self::ALLOWED_COMMANDS)) {
            $this->lastResultSuccess = false;
            $this->lastResultMessage = 'Unknown command.';

            return;
        }

        if (! $this->vehicle->tesla_vehicle_id) {
            $this->lastResultSuccess = false;
            $this->lastResultMessage = 'This vehicle is not connected to Tesla.';

            return;
        }

        // Rate limit: 5 seconds between commands per vehicle
        $cacheKey = "vehicle-cmd:{$this->vehicle->id}";
        if (! Cache::add($cacheKey, true, 5)) {
            $this->lastResultSuccess = false;
            $this->lastResultMessage = 'Please wait a few seconds before sending another command.';

            return;
        }

        $this->executingCommand = $command;

        $params = [];
        if ($command === 'set_temps') {
            // Tesla API always expects Celsius
            $driverC = $this->usesF ? round(($this->driverTemp - 32) * 5 / 9, 1) : $this->driverTemp;
            $passengerC = $this->usesF ? round(($this->passengerTemp - 32) * 5 / 9, 1) : $this->passengerTemp;
            $params = [
                'driver_temp' => $driverC,
                'passenger_temp' => $passengerC,
            ];
        } elseif ($command === 'set_charge_limit') {
            $params = ['percent' => $this->chargeLimit];
        }

        $service = app(TeslaCommandService::class);
        $result = $service->execute($this->vehicle, $command, $params, Auth::id());

        $this->executingCommand = null;
        $this->lastResultSuccess = $result['success'];
        $this->lastResultMessage = $result['success']
            ? 'Command sent successfully.'
            : ($result['error'] ?? 'Command failed.');

        // Optimistically update vehicle state
        if ($result['success'] && $this->vehicle->latestState) {
            $updates = match ($command) {
                'lock' => ['locked' => true],
                'unlock' => ['locked' => false],
                'climate_on' => ['climate_on' => true],
                'climate_off' => ['climate_on' => false],
                'sentry_on' => ['sentry_mode' => true],
                'sentry_off' => ['sentry_mode' => false],
                default => [],
            };

            if ($updates) {
                $this->vehicle->latestState->update($updates);
            }
        }
    }

    public function render()
    {
        $this->vehicle->load('latestState');

        $commandHistory = CommandLog::where('vehicle_id', $this->vehicle->id)
            ->orderByDesc('executed_at')
            ->take(20)
            ->get();

        return view('livewire.vehicle-commands', [
            'commandHistory' => $commandHistory,
            'canExecute' => (bool) $this->vehicle->tesla_vehicle_id,
        ]);
    }
}
