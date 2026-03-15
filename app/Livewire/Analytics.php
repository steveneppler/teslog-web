<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasVehicleFilter;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Analytics extends Component
{
    use HasVehicleFilter;

    // Export properties
    public string $exportFrom = '';

    public string $exportTo = '';

    public string $exportTag = '';

    public string $exportType = 'drives';

    public string $rawExportVehicle = '';

    public string $rawExportMonth = '';

    public function mount(): void
    {
        $this->rawExportMonth = now()->format('Y-m');
    }

    public function downloadExport(): mixed
    {
        $params = ['from' => $this->exportFrom, 'to' => $this->exportTo];
        if ($this->exportTag) {
            $params['tag'] = $this->exportTag;
        }

        $route = $this->exportType === 'charges' ? 'web.export.charges' : 'web.export.drives';

        return $this->redirect(route($route, $params));
    }

    public function downloadRawExport(): mixed
    {
        if (! $this->rawExportVehicle || ! $this->rawExportMonth) {
            return null;
        }

        return $this->redirect(route('web.export.raw', [
            'vehicle_id' => $this->rawExportVehicle,
            'month' => $this->rawExportMonth,
        ]));
    }

    public function render()
    {
        $vehicles = Auth::user()->vehicles()->get();

        return view('livewire.analytics', [
            'vehicles' => $vehicles,
        ]);
    }
}
