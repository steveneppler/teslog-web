<?php

namespace App\Livewire\Analytics;

use App\Models\Charge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class CostCharts extends Component
{
    #[Reactive]
    public string $vehicleFilter = '';

    public int $costDays = 90;

    public function updateCostDays(int $days): void
    {
        $this->costDays = $days;
    }

    public function render()
    {
        $user = Auth::user();
        $vehicleIds = $this->vehicleFilter
            ? collect([(int) $this->vehicleFilter])
            : $user->vehicles()->pluck('id');

        $costByType = Charge::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($this->costDays))
            ->whereNotNull('cost')
            ->select(
                'charge_type',
                DB::raw('sum(cost) as total_cost'),
                DB::raw('sum(energy_added_kwh) as total_energy'),
                DB::raw('count(*) as charge_count'),
            )
            ->groupBy('charge_type')
            ->get();

        $costMonthly = Charge::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($this->costDays))
            ->whereNotNull('cost')
            ->select(
                DB::raw("strftime('%Y-%m', started_at) as month"),
                DB::raw('sum(cost) as total_cost'),
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $currency = $user->currency ?? 'USD';

        $this->dispatch('cost-chart-data', byType: $costByType->toArray(), monthly: $costMonthly->toArray(), currency: $currency);

        return view('livewire.analytics.cost-charts', [
            'hasCostByType' => $costByType->count() > 0,
            'hasCostMonthly' => $costMonthly->count() > 0,
            'currency' => $currency,
        ]);
    }
}
