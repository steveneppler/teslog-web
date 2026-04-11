<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleState;

class StateDetectionService
{
    public function detectState(array $snapshot, string $previousState): string
    {
        // Driving: speed > 0 or gear is D/R
        $speed = $snapshot['speed'] ?? 0;
        $gear = $snapshot['gear'] ?? '';
        if ((is_numeric($speed) && $speed > 0) || in_array($gear, ['D', 'R'])) {
            return 'driving';
        }

        // Charging detection. Tesla Fleet Telemetry sometimes reports
        // charge_state='Idle' for the bulk of an active Supercharger session while
        // charger_power is >100 kW, so treat any meaningful charger_power as a
        // definitive charging signal. The >1 kW threshold matches the batch
        // reprocessor in ProcessVehicleStates and is above spurious ~0.04 kW
        // idle-bus readings.
        $chargerPower = $snapshot['charger_power'] ?? 0;
        if (is_numeric($chargerPower) && $chargerPower > 1) {
            return 'charging';
        }

        // Fall back to charge_state-based charging detection. Tesla uses many
        // values (Charging, Enable, Startup, QualifyLineConfig, etc.) and may
        // add more, so we blacklist known "not charging" values instead of
        // allowlisting charging states.
        $chargeState = $snapshot['charge_state'] ?? '';
        $notChargingStates = ['', 'Idle', 'Disconnected', 'Complete', 'NoPower', 'Shutdown', 'Stopped'];
        if ($chargeState && ! in_array($chargeState, $notChargingStates)) {
            return 'charging';
        }

        // If we were driving and now stopped, transition to idle
        if ($previousState === 'driving') {
            return 'idle';
        }

        // If we were charging and charger power dropped to 0
        if ($previousState === 'charging') {
            return 'idle';
        }

        // If we have no recent data, consider offline
        // (handled elsewhere by timeout)

        // Receiving telemetry means the car is awake — transition from sleeping to idle
        if ($previousState === 'sleeping') {
            return 'idle';
        }

        // Default: maintain current state, or idle
        if ($previousState === 'idle') {
            return 'idle';
        }

        return 'idle';
    }

    public function handleTransition(Vehicle $vehicle, string $from, string $to, VehicleState $state): void
    {
        // Driving ended -> create drive
        if ($from === 'driving' && $to !== 'driving') {
            \App\Jobs\ProcessDriveJob::dispatch($vehicle->id, $state->timestamp)->delay(now()->addMinute());
        }

        // Charging ended -> create charge
        if ($from === 'charging' && $to !== 'charging') {
            \App\Jobs\ProcessChargeJob::dispatch($vehicle->id, $state->timestamp);
        }

        // Idle ended -> create idle session
        if ($from === 'idle' && $to !== 'idle') {
            \App\Jobs\ProcessIdleJob::dispatch($vehicle->id, $state->timestamp);
        }
    }
}
