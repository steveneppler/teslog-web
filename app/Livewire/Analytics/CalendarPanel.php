<?php

namespace App\Livewire\Analytics;

use App\Models\Charge;
use App\Models\Drive;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class CalendarPanel extends Component
{
    #[Reactive]
    public string $vehicleFilter = '';

    public string $calendarMonth = '';

    public function mount(): void
    {
        $tz = Auth::user()->userTz();
        $this->calendarMonth = now()->tz($tz)->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->addMonth()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $tz = Auth::user()->userTz();
        $this->calendarMonth = now()->tz($tz)->format('Y-m');
    }

    public function render()
    {
        $user = Auth::user();
        $vehicleIds = $this->vehicleFilter
            ? collect([(int) $this->vehicleFilter])
            : $user->vehicles()->pluck('id');

        $startOfMonth = $this->calendarMonth . '-01';
        $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

        $calDrives = Drive::whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('started_at', [$startOfMonth, $endOfMonth . ' 23:59:59'])
            ->select(DB::raw("date(started_at) as date"), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $calCharges = Charge::whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('started_at', [$startOfMonth, $endOfMonth . ' 23:59:59'])
            ->select(DB::raw("date(started_at) as date"), 'charge_type', DB::raw('count(*) as count'))
            ->groupBy('date', 'charge_type')
            ->get();

        $calAcCharges = $calCharges->where('charge_type', 'ac')->pluck('count', 'date');
        $calDcCharges = $calCharges->whereIn('charge_type', ['dc', 'supercharger'])
            ->groupBy('date')
            ->map(fn ($rows) => $rows->sum('count'));

        $tz = $user->userTz();
        $todayStr = now()->tz($tz)->format('Y-m-d');
        $monthStart = Carbon::parse($startOfMonth);
        $monthEnd = Carbon::parse($endOfMonth);
        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        $calendarWeeks = [];
        $current = $calendarStart->copy();
        while ($current->lte($calendarEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateStr = $current->format('Y-m-d');
                $week[] = [
                    'date' => $dateStr,
                    'day' => $current->day,
                    'inMonth' => $current->month === $monthStart->month,
                    'isToday' => $dateStr === $todayStr,
                    'drives' => $calDrives[$dateStr] ?? 0,
                    'acCharges' => $calAcCharges[$dateStr] ?? 0,
                    'dcCharges' => $calDcCharges[$dateStr] ?? 0,
                ];
                $current->addDay();
            }
            $calendarWeeks[] = $week;
        }

        $isCurrentMonth = $this->calendarMonth === now()->format('Y-m');
        $calendarLabel = Carbon::parse($startOfMonth)->format('F Y');

        return view('livewire.analytics.calendar-panel', [
            'calendarWeeks' => $calendarWeeks,
            'calendarLabel' => $calendarLabel,
            'isCurrentMonth' => $isCurrentMonth,
        ]);
    }
}
