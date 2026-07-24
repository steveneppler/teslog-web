<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Resolves a $vehicleFilter property (however it is bound — #[Url] or
 * #[Reactive]) into vehicle IDs, restricted to vehicles the user owns.
 */
trait ResolvesVehicleFilter
{
    protected function getVehicleIds()
    {
        $owned = Auth::user()->vehicles()->pluck('id');

        return $this->vehicleFilter
            ? $owned->intersect([(int) $this->vehicleFilter])->values()
            : $owned;
    }
}
