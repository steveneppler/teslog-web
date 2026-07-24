<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

trait HasVehicleFilter
{
    use ResolvesVehicleFilter;

    #[Url]
    public string $vehicleFilter = '';
}
