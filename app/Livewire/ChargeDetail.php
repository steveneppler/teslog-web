<?php

namespace App\Livewire;

use App\Models\Charge;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ChargeDetail extends Component
{
    public Charge $charge;

    public function mount(Charge $charge)
    {
        abort_unless($charge->vehicle->user_id === Auth::id(), 403);

        $this->charge = $charge->load('vehicle', 'points', 'place');
    }

    public function getDurationProperty(): string
    {
        if (! $this->charge->started_at || ! $this->charge->ended_at) {
            return '—';
        }

        $diff = $this->charge->started_at->diff($this->charge->ended_at);

        if ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        }

        return $diff->i . 'm';
    }

    public function render()
    {
        return view('livewire.charge-detail', [
            'points' => $this->charge->points,
            'userTz' => Auth::user()->userTz(),
        ]);
    }
}
