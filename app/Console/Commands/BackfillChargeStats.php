<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\VehicleState;
use Illuminate\Console\Command;

class BackfillChargeStats extends Command
{
    protected $signature = 'teslog:backfill-charge-stats
        {--vehicle= : Specific vehicle ID to backfill}
        {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Backfill charge stats: energy_used_kwh, charging_efficiency, avg/max voltage/current, odometer, and decimal battery levels.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $query = Charge::query();
        if ($vehicleId = $this->option('vehicle')) {
            $query->where('vehicle_id', $vehicleId);
        }

        $totalUpdated = 0;
        $totalSkipped = 0;

        $query->chunkById(100, function ($charges) use ($dryRun, &$totalUpdated, &$totalSkipped) {
            foreach ($charges as $charge) {
                $stats = $this->computeStats($charge);

                if (! $stats) {
                    $totalSkipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  Charge {$charge->id} ({$charge->started_at}): " .
                        "battery {$stats['start_battery_level']}→{$stats['end_battery_level']}%, " .
                        "used {$stats['energy_used_kwh']} kWh, " .
                        "eff {$stats['charging_efficiency']}%, " .
                        "avg {$stats['avg_voltage']}V/{$stats['avg_current']}A");
                } else {
                    $charge->update($stats);
                }

                $totalUpdated++;
            }
        });

        $label = $dryRun ? 'Would update' : 'Updated';
        $this->info("Done. {$label} {$totalUpdated} charge(s), skipped {$totalSkipped}.");

        return self::SUCCESS;
    }

    private function computeStats(Charge $charge): ?array
    {
        // Get charging states for this session
        $chargingStates = VehicleState::where('vehicle_id', $charge->vehicle_id)
            ->where('state', 'charging')
            ->where('timestamp', '>=', $charge->started_at)
            ->where('timestamp', '<=', $charge->ended_at)
            ->orderBy('timestamp')
            ->get();

        if ($chargingStates->isEmpty()) {
            return null;
        }

        $first = $chargingStates->first();
        $last = $chargingStates->last();

        // Transition state for accurate end battery level
        $transitionState = VehicleState::where('vehicle_id', $charge->vehicle_id)
            ->where('timestamp', '>', $last->timestamp)
            ->where('timestamp', '<=', $last->timestamp->copy()->addMinutes(5))
            ->where('state', '!=', 'charging')
            ->orderBy('timestamp')
            ->first();

        $endBatteryLevel = $transitionState?->battery_level ?? $last->battery_level;
        $endRatedRange = $transitionState?->rated_range ?? $last->rated_range;
        $endEnergyRemaining = $transitionState?->energy_remaining ?? $last->energy_remaining;

        // Energy added (battery-side)
        $energyAdded = null;
        if ($first->energy_remaining !== null && $endEnergyRemaining !== null) {
            $energyAdded = $endEnergyRemaining - $first->energy_remaining;
        }

        // Energy used (wall-side, trapezoidal integration)
        $energyUsed = null;
        if ($chargingStates->count() >= 2) {
            $totalKwh = 0;
            $previous = null;
            foreach ($chargingStates as $state) {
                if ($previous && $state->charger_power && $state->charger_power > 0) {
                    $intervalHours = $previous->timestamp->diffInSeconds($state->timestamp) / 3600;
                    $avgPower = (($previous->charger_power ?? 0) + $state->charger_power) / 2;
                    $totalKwh += $avgPower * $intervalHours;
                }
                $previous = $state;
            }
            $energyUsed = $totalKwh > 0 ? round($totalKwh, 2) : null;
        }

        // Charging efficiency
        $chargingEfficiency = ($energyAdded && $energyUsed && $energyUsed > 0)
            ? round($energyAdded / $energyUsed * 100, 1)
            : null;

        // Voltage and current stats
        $voltages = $chargingStates->pluck('charger_voltage')->filter()->values();
        $currents = $chargingStates->pluck('charger_current')->filter()->values();

        // Odometer
        $odometer = $chargingStates->pluck('odometer')->filter()->first();

        return [
            'start_battery_level' => $first->battery_level,
            'end_battery_level' => $endBatteryLevel,
            'start_rated_range' => $first->rated_range,
            'end_rated_range' => $endRatedRange,
            'energy_added_kwh' => $energyAdded ?? $charge->energy_added_kwh,
            'energy_used_kwh' => $energyUsed,
            'charging_efficiency' => $chargingEfficiency,
            'avg_voltage' => $voltages->isNotEmpty() ? round($voltages->avg(), 2) : null,
            'max_voltage' => $voltages->isNotEmpty() ? round($voltages->max(), 2) : null,
            'avg_current' => $currents->isNotEmpty() ? round($currents->avg(), 2) : null,
            'max_current' => $currents->isNotEmpty() ? round($currents->max(), 2) : null,
            'odometer' => $odometer,
        ];
    }
}
