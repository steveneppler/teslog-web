<?php

namespace App\Livewire;

use App\Models\TelemetryRaw;
use App\Models\VehicleState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

    private function vehicleIds(Collection $userVehicles): Collection
    {
        if ($this->vehicleFilter) {
            $requestedId = (int) $this->vehicleFilter;
            if ($userVehicles->contains('id', $requestedId)) {
                return collect([$requestedId]);
            }
        }

        return $userVehicles->pluck('id');
    }

    private function fromUtc(string $tz): ?\Carbon\Carbon
    {
        return $this->from ? \Carbon\Carbon::parse($this->from, $tz)->utc() : null;
    }

    private function toUtc(string $tz): ?\Carbon\Carbon
    {
        return $this->to ? \Carbon\Carbon::parse($this->to, $tz)->utc() : null;
    }

    private function processedQuery(Collection $vehicleIds, ?\Carbon\Carbon $fromUtc, ?\Carbon\Carbon $toUtc): Builder
    {
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

        return $query;
    }

    private function rawQuery(Collection $vehicleIds, ?\Carbon\Carbon $fromUtc, ?\Carbon\Carbon $toUtc): Builder
    {
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

        return $query;
    }

    public function render()
    {
        abort_unless(Auth::user()->debug_mode, 403);

        $user = Auth::user();
        $tz = $user->userTz();
        $vehicles = $user->vehicles()->get();

        $vehicleIds = $this->vehicleIds($vehicles);
        $fromUtc = $this->fromUtc($tz);
        $toUtc = $this->toUtc($tz);

        $records = null;
        $rawRecords = null;
        $expandedRaw = null;
        $states = collect();

        if ($this->tab === 'processed') {
            $states = collect(['driving', 'charging', 'idle', 'sleeping', 'offline'])->sort()->values();

            $records = $this->processedQuery($vehicleIds, $fromUtc, $toUtc)->paginate(50);

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
            $rawRecords = $this->rawQuery($vehicleIds, $fromUtc, $toUtc)->paginate(100);
        }

        $processedExportCount = $this->processedQuery($vehicleIds, $fromUtc, $toUtc)->count();
        $rawExportCount = $this->rawQuery($vehicleIds, $fromUtc, $toUtc)->count();

        return view('livewire.debug', [
            'records' => $records,
            'rawRecords' => $rawRecords,
            'expandedRaw' => $expandedRaw,
            'vehicles' => $vehicles,
            'states' => $states,
            'userTz' => $tz,
            'processedExportCount' => $processedExportCount,
            'rawExportCount' => $rawExportCount,
        ]);
    }
}
