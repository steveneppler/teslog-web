<?php

namespace App\Livewire\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

trait HasPeriodNavigation
{
    #[Url]
    public string $period = 'month';

    #[Url]
    public string $week = '';

    #[Url]
    public string $month = '';

    #[Url]
    public string $year = '';

    public function mountHasPeriodNavigation(): void
    {
        $tz = $this->userTz();
        if (! $this->week) {
            $this->week = now()->tz($tz)->startOfWeek()->format('Y-m-d');
        }
        if (! $this->month) {
            $this->month = now()->tz($tz)->format('Y-m');
        }
        if (! $this->year) {
            $this->year = now()->tz($tz)->format('Y');
        }
    }

    public function previous(): void
    {
        match ($this->period) {
            'week' => $this->week = Carbon::parse($this->week)->subWeek()->format('Y-m-d'),
            'month' => $this->month = Carbon::parse($this->month . '-01')->subMonth()->format('Y-m'),
            'year' => $this->year = (string) ((int) $this->year - 1),
        };
    }

    public function next(): void
    {
        match ($this->period) {
            'week' => $this->week = Carbon::parse($this->week)->addWeek()->format('Y-m-d'),
            'month' => $this->month = Carbon::parse($this->month . '-01')->addMonth()->format('Y-m'),
            'year' => $this->year = (string) ((int) $this->year + 1),
        };
    }

    public function current(): void
    {
        $tz = $this->userTz();
        $this->week = now()->tz($tz)->startOfWeek()->format('Y-m-d');
        $this->month = now()->tz($tz)->format('Y-m');
        $this->year = now()->tz($tz)->format('Y');
    }

    public function jumpTo(string $value): void
    {
        $tz = $this->userTz();
        match ($this->period) {
            'week' => $this->week = Carbon::parse($value, $tz)->startOfWeek()->format('Y-m-d'),
            'month' => $this->month = $value,
            'year' => $this->year = $value,
            default => null,
        };
    }

    protected function getDateRange(string $tz): array
    {
        return match ($this->period) {
            'week' => [
                Carbon::parse($this->week, $tz)->startOfWeek(),
                Carbon::parse($this->week, $tz)->endOfWeek(),
            ],
            'month' => [
                Carbon::parse($this->month . '-01', $tz)->startOfMonth(),
                Carbon::parse($this->month . '-01', $tz)->endOfMonth(),
            ],
            'year' => [
                Carbon::parse($this->year . '-01-01', $tz)->startOfYear(),
                Carbon::parse($this->year . '-01-01', $tz)->endOfYear(),
            ],
            'all' => [null, null],
        };
    }

    protected function formatPeriodLabel(Carbon $start, Carbon $end): string
    {
        return match ($this->period) {
            'week' => $start->format('M j') . ' — ' . $end->format('M j, Y'),
            'month' => $start->format('F Y'),
            'year' => $start->format('Y'),
            default => '',
        };
    }

    private function userTz(): string
    {
        return Auth::user()->userTz();
    }
}
