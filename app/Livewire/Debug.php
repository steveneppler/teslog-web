<?php

namespace App\Livewire;

use App\Models\TelemetryRaw;
use App\Models\VehicleState;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Debug extends Component
{
    use WithPagination;

    #[Url]
    public string $vehicleFilter = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $stateFilter = '';

    #[Url]
    public string $fieldFilter = '';

    #[Url]
    public string $tab = 'processed';

    public ?int $showRawFor = null;

    public function mount()
    {
        $tz = Auth::user()->userTz();

        if (! $this->from) {
            $this->from = now()->tz($tz)->subHour()->format('Y-m-d\TH:i');
        }
        if (! $this->to) {
            $this->to = now()->tz($tz)->format('Y-m-d\TH:i');
        }
    }

    public function setTab(string $tab)
    {
        $this->tab = $tab;
        $this->showRawFor = null;
        $this->resetPage();
    }

    public function toggleRawFor(int $stateId)
    {
        $this->showRawFor = $this->showRawFor === $stateId ? null : $stateId;
    }

    public function resetFilters()
    {
        $tz = Auth::user()->userTz();
        $this->vehicleFilter = '';
        $this->stateFilter = '';
        $this->fieldFilter = '';
        $this->from = now()->tz($tz)->subHour()->format('Y-m-d\TH:i');
        $this->to = now()->tz($tz)->format('Y-m-d\TH:i');
        $this->showRawFor = null;
        $this->resetPage();
    }

    public function updatedVehicleFilter()
    {
        $this->resetPage();
    }

    public function updatedStateFilter()
    {
        $this->resetPage();
    }

    public function updatedFieldFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(Auth::user()->debug_mode, 403);

        $user = Auth::user();
        $tz = $user->userTz();
        $vehicles = $user->vehicles()->get();

        $vehicleIds = $this->vehicleFilter
            ? collect([(int) $this->vehicleFilter])
            : $vehicles->pluck('id');

        $fromUtc = $this->from ? \Carbon\Carbon::parse($this->from, $tz)->utc() : null;
        $toUtc = $this->to ? \Carbon\Carbon::parse($this->to, $tz)->utc() : null;

        $records = null;
        $rawRecords = null;
        $expandedRaw = null;
        $states = collect();

        if ($this->tab === 'processed') {
            $query = VehicleState::whereIn('vehicle_id', $vehicleIds)
                ->orderByDesc('timestamp');

            if ($fromUtc) {
                $query->where('timestamp', '>=', $fromUtc);
            }
            if ($toUtc) {
                $query->where('timestamp', '<=', $toUtc);
            }
            if ($this->stateFilter) {
                $query->where('state', $this->stateFilter);
            }
            if ($this->fieldFilter) {
                $search = $this->fieldFilter;
                $query->where(function ($q) use ($search) {
                    $q->where('charge_state', 'like', "%{$search}%")
                      ->orWhere('state', 'like', "%{$search}%")
                      ->orWhere('gear', 'like', "%{$search}%")
                      ->orWhere('software_version', 'like', "%{$search}%");
                });
            }

            $states = collect(['driving', 'charging', 'idle', 'sleeping', 'offline'])->sort()->values();

            $records = $query->paginate(50);

            // Load raw telemetry for expanded row
            if ($this->showRawFor) {
                $state = VehicleState::find($this->showRawFor);
                if ($state && $vehicleIds->contains($state->vehicle_id)) {
                    $expandedRaw = TelemetryRaw::where('vehicle_id', $state->vehicle_id)
                        ->whereRaw("strftime('%Y-%m-%d %H:%M:%S', timestamp) >= ?", [$state->timestamp->copy()->subSeconds(30)->format('Y-m-d H:i:s')])
                        ->whereRaw("strftime('%Y-%m-%d %H:%M:%S', timestamp) <= ?", [$state->timestamp->copy()->addSeconds(5)->format('Y-m-d H:i:s')])
                        ->orderBy('timestamp')
                        ->orderBy('field_name')
                        ->get();
                }
            }
        } else {
            $query = TelemetryRaw::whereIn('vehicle_id', $vehicleIds)
                ->orderByDesc('timestamp');

            // telemetry_raw timestamps may be stored as ISO 8601 (with T and Z),
            // so use strftime to normalize for comparison in SQLite
            if ($fromUtc) {
                $query->whereRaw("strftime('%Y-%m-%d %H:%M:%S', timestamp) >= ?", [$fromUtc->format('Y-m-d H:i:s')]);
            }
            if ($toUtc) {
                $query->whereRaw("strftime('%Y-%m-%d %H:%M:%S', timestamp) <= ?", [$toUtc->format('Y-m-d H:i:s')]);
            }
            if ($this->fieldFilter) {
                $query->where('field_name', 'like', "%{$this->fieldFilter}%");
            }

            $rawRecords = $query->paginate(100);
        }

        return view('livewire.debug', [
            'records' => $records,
            'rawRecords' => $rawRecords,
            'expandedRaw' => $expandedRaw,
            'vehicles' => $vehicles,
            'states' => $states,
            'userTz' => $tz,
        ]);
    }
}
