<?php

namespace App\Livewire;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class VehicleList extends Component
{
    public string $newName = '';
    public string $newVin = '';
    public bool $showAddForm = false;

    public ?int $editingVehicleId = null;
    public string $editName = '';
    public string $editModel = '';
    public string $editTrim = '';
    public string $editBatteryCapacity = '';

    // Tesla models with usable battery capacities (kWh)
    public static function modelOptions(): array
    {
        return [
            '' => ['label' => 'Select model...', 'trims' => []],
            'Model 3' => [
                'label' => 'Model 3',
                'trims' => [
                    'Standard Range Plus' => 50,
                    'Standard Range' => 57.5,
                    'Long Range' => 75,
                    'Long Range (2024+)' => 75,
                    'Performance' => 75,
                    'Performance (2024+)' => 75,
                ],
            ],
            'Model Y' => [
                'label' => 'Model Y',
                'trims' => [
                    'Standard Range' => 57.5,
                    'Long Range' => 75,
                    'Long Range (2024+)' => 75,
                    'Performance' => 75,
                    'Performance (2024+)' => 75,
                ],
            ],
            'Model S' => [
                'label' => 'Model S',
                'trims' => [
                    'Standard Range' => 75,
                    'Long Range' => 95,
                    'Long Range (2021+)' => 95,
                    'Plaid' => 95,
                    'Performance (Pre-2021)' => 95,
                    'P100D' => 95,
                    'P90D' => 85,
                    '85D' => 77.5,
                    '70D' => 65,
                    '60' => 57.5,
                ],
            ],
            'Model X' => [
                'label' => 'Model X',
                'trims' => [
                    'Standard Range' => 75,
                    'Long Range' => 95,
                    'Long Range (2021+)' => 95,
                    'Plaid' => 95,
                    'Performance (Pre-2021)' => 95,
                    'P100D' => 95,
                    'P90D' => 85,
                    '75D' => 72.5,
                ],
            ],
            'Cybertruck' => [
                'label' => 'Cybertruck',
                'trims' => [
                    'All-Wheel Drive' => 100,
                    'Cyberbeast' => 120,
                    'Foundation' => 120,
                ],
            ],
        ];
    }

    public function editVehicle(int $vehicleId)
    {
        $vehicle = Auth::user()->vehicles()->findOrFail($vehicleId);
        $this->editingVehicleId = $vehicleId;
        $this->editName = $vehicle->name ?? '';
        $this->editModel = $vehicle->model ?? '';
        $this->editTrim = $vehicle->trim ?? '';
        $this->editBatteryCapacity = $vehicle->battery_capacity_kwh ? (string) $vehicle->battery_capacity_kwh : '';
    }

    public function cancelEdit()
    {
        $this->editingVehicleId = null;
    }

    public function updatedEditModel()
    {
        // Auto-clear trim when model changes
        $this->editTrim = '';
        $this->editBatteryCapacity = '';
    }

    public function updatedEditTrim()
    {
        // Auto-fill battery capacity from known values
        $options = self::modelOptions();
        if (isset($options[$this->editModel]['trims'][$this->editTrim])) {
            $this->editBatteryCapacity = (string) $options[$this->editModel]['trims'][$this->editTrim];
        }
    }

    public function saveVehicle()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editModel' => 'nullable|string|max:255',
            'editTrim' => 'nullable|string|max:255',
            'editBatteryCapacity' => 'nullable|numeric|min:1|max:300',
        ]);

        $vehicle = Auth::user()->vehicles()->findOrFail($this->editingVehicleId);
        $vehicle->update([
            'name' => $this->editName,
            'model' => $this->editModel ?: null,
            'trim' => $this->editTrim ?: null,
            'battery_capacity_kwh' => $this->editBatteryCapacity ?: null,
        ]);

        $this->editingVehicleId = null;
    }

    public function addVehicle()
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newVin' => 'nullable|string|max:17',
        ]);

        Auth::user()->vehicles()->create([
            'name' => $this->newName,
            'vin' => $this->newVin ?: null,
            'show_on_dashboard' => false,
        ]);

        $this->reset(['newName', 'newVin', 'showAddForm']);
    }

    public ?int $confirmingDelete = null;

    public function toggleActive(int $vehicleId)
    {
        $vehicle = Auth::user()->vehicles()->findOrFail($vehicleId);
        $vehicle->update(['is_active' => ! $vehicle->is_active]);
    }

    public function toggleDashboard(int $vehicleId)
    {
        $vehicle = Auth::user()->vehicles()->findOrFail($vehicleId);
        $vehicle->update(['show_on_dashboard' => ! $vehicle->show_on_dashboard]);
    }

    public function confirmDelete(int $vehicleId)
    {
        $this->confirmingDelete = $vehicleId;
    }

    public function cancelDelete()
    {
        $this->confirmingDelete = null;
    }

    public function deleteVehicle(int $vehicleId)
    {
        $vehicle = Auth::user()->vehicles()->findOrFail($vehicleId);
        $vehicle->delete();
        $this->confirmingDelete = null;
    }

    public function render()
    {
        return view('livewire.vehicle-list', [
            'vehicles' => Auth::user()->vehicles()->with('latestState')->get(),
            'modelOptions' => self::modelOptions(),
        ]);
    }
}
