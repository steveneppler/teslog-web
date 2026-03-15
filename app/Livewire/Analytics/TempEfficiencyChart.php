<?php

namespace App\Livewire\Analytics;

use App\Models\Drive;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class TempEfficiencyChart extends Component
{
    #[Reactive]
    public string $vehicleFilter = '';

    public function render()
    {
        $user = Auth::user();
        $vehicleIds = $this->vehicleFilter
            ? collect([(int) $this->vehicleFilter])
            : $user->vehicles()->pluck('id');

        $tempEffData = Drive::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays(365))
            ->whereNotNull('efficiency')
            ->whereNotNull('outside_temp_avg')
            ->selectRaw('ROUND(outside_temp_avg) as temp_c, AVG(efficiency) as avg_efficiency, COUNT(*) as cnt')
            ->groupByRaw('ROUND(outside_temp_avg)')
            ->having('cnt', '>=', 3)
            ->orderBy('temp_c')
            ->get()
            ->map(fn ($row) => [
                'temp' => round($user->convertTemp($row->temp_c), 0),
                'efficiency' => round($user->convertEfficiency($row->avg_efficiency), 0),
            ]);

        $effUnit = $user->efficiencyUnit();
        $tempUnit = $user->tempUnit();

        $this->dispatch('temp-eff-chart-data', data: $tempEffData->values()->all(), effUnit: $effUnit, tempUnit: $tempUnit);

        return view('livewire.analytics.temp-efficiency-chart', [
            'hasTempEffData' => $tempEffData->count() > 2,
        ]);
    }
}
