<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasPeriodNavigation;
use App\Livewire\Concerns\HasVehicleFilter;
use App\Models\Drive;
use App\Models\DrivePoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class DrivesList extends Component
{
    use HasPeriodNavigation, HasVehicleFilter;

    #[Url]
    public string $tagFilter = '';

    public bool $bulkMode = false;

    public array $selectedDrives = [];

    public string $bulkTag = '';

    public function mount()
    {
        $this->mountHasPeriodNavigation();
    }

    public function toggleBulkMode(): void
    {
        $this->bulkMode = ! $this->bulkMode;
        $this->selectedDrives = [];
        $this->bulkTag = '';
    }

    public function toggleDrive(int $id): void
    {
        if (in_array($id, $this->selectedDrives)) {
            $this->selectedDrives = array_values(array_diff($this->selectedDrives, [$id]));
        } else {
            $this->selectedDrives[] = $id;
        }
    }

    public function selectAll(): void
    {
        $vehicleIds = $this->getVehicleIds();
        $tz = $this->userTz();
        [$periodStart, $periodEnd] = $this->getDateRange($tz);

        $this->selectedDrives = $this->buildDriveQuery($vehicleIds, $periodStart, $periodEnd)
            ->pluck('id')
            ->all();
    }

    public function deselectAll(): void
    {
        $this->selectedDrives = [];
    }

    public function applyBulkTag(): void
    {
        if (empty($this->selectedDrives) || $this->bulkTag === '') {
            return;
        }

        $this->updateBulkTag($this->bulkTag);
        $this->bulkTag = '';
    }

    public function clearBulkTag(): void
    {
        if (empty($this->selectedDrives)) {
            return;
        }

        $this->updateBulkTag(null);
    }

    private function updateBulkTag(?string $tag): void
    {
        Drive::whereIn('id', $this->selectedDrives)
            ->whereIn('vehicle_id', $this->getVehicleIds())
            ->update(['tag' => $tag]);

        $this->selectedDrives = [];
    }

    private function buildDriveQuery($vehicleIds, $periodStart, $periodEnd)
    {
        $query = Drive::whereIn('vehicle_id', $vehicleIds)
            ->with('vehicle', 'startPlace', 'endPlace')
            ->orderByDesc('started_at');

        if ($periodStart && $periodEnd) {
            $query->whereBetween('started_at', [$periodStart->copy()->utc(), $periodEnd->copy()->utc()]);
        }

        if ($this->tagFilter) {
            $query->where('tag', $this->tagFilter);
        }

        return $query;
    }

    public function render()
    {
        $tz = $this->userTz();
        $user = Auth::user();
        $vehicles = $user->vehicles()->get();
        $vehicleIds = $this->getVehicleIds();

        [$periodStart, $periodEnd] = $this->getDateRange($tz);

        $query = $this->buildDriveQuery($vehicleIds, $periodStart, $periodEnd);

        $drives = $query->get();

        $driveIds = $drives->pluck('id');

        // Load sampled points to keep payload manageable
        $allPoints = $this->loadSampledPoints($driveIds, 10000);

        // Group by date in user's timezone
        $drivesByDate = $drives->groupBy(fn ($d) => $d->started_at->tz($tz)->format('Y-m-d'));

        $colors = ['#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

        $mapData = [];
        $summaryRoutes = [];
        $driveIndex = 0;
        foreach ($drivesByDate as $date => $dayDrives) {
            $routes = [];
            foreach ($dayDrives->values() as $i => $drive) {
                $pts = $allPoints[$drive->id] ?? collect();
                if ($pts->isNotEmpty()) {
                    $coords = $pts->map(fn ($p) => [$p->latitude, $p->longitude])->values()->all();
                    $color = $colors[$driveIndex % count($colors)];
                    $routes[] = [
                        'coords' => $coords,
                        'color' => $colors[$i % count($colors)],
                        'label' => $drive->started_at->tz($tz)->format('g:ia'),
                    ];
                    $summaryRoutes[] = [
                        'coords' => $coords,
                        'color' => $color,
                        'label' => $drive->started_at->tz($tz)->format('M j g:ia'),
                    ];
                    $driveIndex++;
                }
            }
            $mapData[$date] = $routes;
        }

        $isCurrent = $this->period === 'all' || match ($this->period) {
            'week' => Carbon::parse($this->week, $tz)->startOfWeek()->isSameWeek(now()->tz($tz)),
            'month' => $this->month === now()->tz($tz)->format('Y-m'),
            'year' => $this->year === now()->tz($tz)->format('Y'),
        };

        $periodLabel = $this->period !== 'all' && $periodStart
            ? $this->formatPeriodLabel($periodStart, $periodEnd)
            : 'All Time';

        $this->dispatch('maps-updated', mapData: $mapData);
        $this->dispatch('summary-map-updated', routes: $summaryRoutes);

        return view('livewire.drives-list', [
            'drivesByDate' => $drivesByDate,
            'mapData' => $mapData,
            'periodLabel' => $periodLabel,
            'isCurrent' => $isCurrent,
            'allDrives' => $drives,
            'vehicles' => $vehicles,
            'summaryRoutes' => $summaryRoutes,
            'userTz' => $tz,
        ]);
    }

    /**
     * Load drive points sampled to stay under $maxPoints total.
     * Always keeps first and last point per drive for accurate start/end.
     */
    private function loadSampledPoints($driveIds, int $maxPoints = 10000): \Illuminate\Support\Collection
    {
        if ($driveIds->isEmpty()) {
            return collect();
        }

        $totalPointCount = DrivePoint::whereIn('drive_id', $driveIds)->count();
        $nth = max(1, (int) ceil($totalPointCount / $maxPoints));

        if ($nth === 1) {
            // No sampling needed — load all
            return DrivePoint::whereIn('drive_id', $driveIds)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('timestamp')
                ->get(['drive_id', 'latitude', 'longitude'])
                ->groupBy('drive_id');
        }

        $placeholders = $driveIds->map(fn () => '?')->implode(',');
        $sampledPoints = DB::select("
            SELECT drive_id, latitude, longitude FROM (
                SELECT drive_id, latitude, longitude,
                    ROW_NUMBER() OVER (PARTITION BY drive_id ORDER BY timestamp) as rn,
                    COUNT(*) OVER (PARTITION BY drive_id) as total
                FROM drive_points
                WHERE drive_id IN ({$placeholders})
                AND latitude IS NOT NULL AND longitude IS NOT NULL
            ) sub
            WHERE rn = 1 OR rn = total OR rn % ? = 0
        ", [...$driveIds->all(), $nth]);

        return collect($sampledPoints)->groupBy('drive_id');
    }
}
