<?php

namespace App\Livewire;

use App\Models\Drive;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DriveDetail extends Component
{
    public Drive $drive;

    public function mount(Drive $drive)
    {
        abort_unless($drive->vehicle->user_id === Auth::id(), 403);

        $this->drive = $drive->load('vehicle', 'points', 'startPlace', 'endPlace');
    }

    public function getDurationProperty(): string
    {
        if (! $this->drive->started_at || ! $this->drive->ended_at) {
            return '—';
        }

        $diff = $this->drive->started_at->diff($this->drive->ended_at);

        if ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        }

        return $diff->i . 'm';
    }

    public function getStartDisplayNameProperty(): string
    {
        if ($this->drive->startPlace) {
            return $this->drive->startPlace->name;
        }

        return $this->drive->start_address ?? 'Unknown location';
    }

    public function getEndDisplayNameProperty(): string
    {
        if ($this->drive->endPlace) {
            return $this->drive->endPlace->name;
        }

        return $this->drive->end_address ?? 'Unknown location';
    }

    public function render()
    {
        $userTz = Auth::user()->userTz();
        $points = $this->drive->points;

        $mapPoints = $points
            ->filter(function ($p) { return $p->latitude && $p->longitude; })
            ->map(function ($p) use ($userTz) {
                return [
                    'lat' => $p->latitude,
                    'lng' => $p->longitude,
                    'speed' => $p->speed,
                    'timestamp' => $p->timestamp->tz($userTz)->format('g:ia'),
                ];
            })
            ->values();

        return view('livewire.drive-detail', [
            'points' => $points,
            'mapPoints' => $mapPoints,
            'userTz' => $userTz,
        ]);
    }
}
