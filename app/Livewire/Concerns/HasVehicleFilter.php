<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

trait HasVehicleFilter
{
    #[Url]
    public string $vehicleFilter = '';

    protected function getVehicleIds()
    {
        $user = Auth::user();

        return $this->vehicleFilter
            ? collect([(int) $this->vehicleFilter])
            : $user->vehicles()->pluck('id');
    }
}
