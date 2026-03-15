<?php

namespace App\Livewire;

use App\Models\FirmwareHistory as FirmwareHistoryModel;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FirmwareHistory extends Component
{
    public Vehicle $vehicle;

    public function mount(Vehicle $vehicle): void
    {
        abort_unless($vehicle->user_id === Auth::id(), 403);

        $this->vehicle = $vehicle;
    }

    public function render()
    {
        $firmwareRecords = FirmwareHistoryModel::where('vehicle_id', $this->vehicle->id)
            ->orderByDesc('detected_at')
            ->get();

        // For manual/inactive vehicles, cap the current version's end date to last activity
        $lastSeen = $this->vehicle->tesla_vehicle_id
            ? now()
            : (VehicleState::where('vehicle_id', $this->vehicle->id)
                ->orderByDesc('timestamp')
                ->value('timestamp') ?? now());

        // Calculate duration for each firmware version and group by major version
        $firmwareWithDuration = $firmwareRecords->values()->map(function ($fw, $i) use ($firmwareRecords, $lastSeen) {
            $nextUpdate = $i > 0 ? $firmwareRecords[$i - 1]->detected_at : $lastSeen;
            $days = max(1, (int) round($fw->detected_at->floatDiffInDays($nextUpdate)));

            return (object) [
                'version' => $fw->version,
                'detected_at' => $fw->detected_at,
                'previous_version' => $fw->previous_version,
                'days' => $days,
                'is_current' => $i === 0,
            ];
        });

        $maxDays = $firmwareWithDuration->max('days') ?: 1;

        // Group by major version (e.g., "2024.26" from "2024.26.3")
        $firmwareGroups = $firmwareWithDuration->groupBy(function ($fw) {
            $parts = explode('.', $fw->version);

            return count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : $fw->version;
        });

        return view('livewire.firmware-history', [
            'firmwareGroups' => $firmwareGroups,
            'maxDays' => $maxDays,
            'totalVersions' => $firmwareRecords->count(),
        ]);
    }
}
