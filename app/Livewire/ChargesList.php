<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasPeriodNavigation;
use App\Livewire\Concerns\HasVehicleFilter;
use App\Models\Charge;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class ChargesList extends Component
{
    use HasPeriodNavigation, HasVehicleFilter;

    #[Url]
    public string $typeFilter = '';

    public function mount()
    {
        $this->mountHasPeriodNavigation();
    }

    public function render()
    {
        $tz = $this->userTz();
        $user = Auth::user();
        $vehicles = $user->vehicles()->get();
        $vehicleIds = $this->getVehicleIds();

        [$periodStart, $periodEnd] = $this->getDateRange($tz);

        $query = Charge::whereIn('vehicle_id', $vehicleIds)
            ->with('vehicle', 'place')
            ->orderByDesc('started_at');

        if ($periodStart && $periodEnd) {
            $query->whereBetween('started_at', [$periodStart->copy()->utc(), $periodEnd->copy()->utc()]);
        }

        if ($this->typeFilter) {
            $query->where('charge_type', $this->typeFilter);
        }

        $charges = $query->get();

        $chargesByDate = $charges->groupBy(fn ($c) => $c->started_at->tz($tz)->format('Y-m-d'));

        $isCurrent = $this->period === 'all' || match ($this->period) {
            'week' => Carbon::parse($this->week, $tz)->startOfWeek()->isSameWeek(now()->tz($tz)),
            'month' => $this->month === now()->tz($tz)->format('Y-m'),
            'year' => $this->year === now()->tz($tz)->format('Y'),
        };

        $periodLabel = $this->period !== 'all' && $periodStart
            ? $this->formatPeriodLabel($periodStart, $periodEnd)
            : 'All Time';

        // Map markers for charges with coordinates
        $mapMarkers = $charges
            ->filter(fn ($c) => $c->latitude && $c->longitude)
            ->groupBy(fn ($c) => round($c->latitude, 4) . ',' . round($c->longitude, 4))
            ->map(function ($group) use ($tz) {
                $first = $group->first();
                $totalEnergy = $group->sum('energy_added_kwh');
                $label = $first->place?->name ?? $first->address ?? 'Unknown';

                return [
                    'lat' => $first->latitude,
                    'lng' => $first->longitude,
                    'label' => $label,
                    'count' => $group->count(),
                    'energy' => round($totalEnergy, 1),
                    'type' => $first->charge_type?->value,
                ];
            })
            ->values()
            ->all();

        $this->dispatch('charge-markers', markers: $mapMarkers);

        return view('livewire.charges-list', [
            'chargesByDate' => $chargesByDate,
            'periodLabel' => $periodLabel,
            'isCurrent' => $isCurrent,
            'allCharges' => $charges,
            'vehicles' => $vehicles,
            'mapMarkers' => $mapMarkers,
            'userTz' => $tz,
        ]);
    }
}
