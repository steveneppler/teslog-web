<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Services\TeslaAuthService;
use App\Support\TelemetryFields;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class TelemetrySettings extends Component
{
    public string $vehicleId = '';

    public string $search = '';

    public array $fields = [];

    public string $message = '';

    public function mount(): void
    {
        $this->vehicleId = (string) (Auth::user()->vehicles()->value('id') ?? '');
        $this->loadFields();
    }

    private function vehicle(): Vehicle
    {
        return Auth::user()->vehicles()->findOrFail($this->vehicleId);
    }

    public function updatedVehicleId(): void
    {
        $this->resetErrorBag();
        $this->message = '';
        $this->loadFields();
    }

    private function loadFields(): void
    {
        if ($this->vehicleId === '') {
            $this->fields = [];

            return;
        }
        $this->fillFields($this->vehicle()->telemetry_fields ?? TelemetryFields::defaults());
    }

    private function fillFields(array $preferences): void
    {
        $this->fields = [];
        foreach (TelemetryFields::catalog() as $name => $metadata) {
            $this->fields[$name] = [
                'enabled' => isset($preferences[$name]),
                'interval_seconds' => $preferences[$name]['interval_seconds'] ?? 60,
                'minimum_delta' => $preferences[$name]['minimum_delta'] ?? '',
            ];
        }
    }

    public function restoreDefaults(): void
    {
        $this->vehicle();
        $this->fillFields(TelemetryFields::defaults());
        $this->message = 'Defaults loaded. Save and apply to change collection.';
    }

    public function setAllEnabled(bool $enabled): void
    {
        $this->vehicle();
        foreach (TelemetryFields::catalog() as $name => $metadata) {
            $this->fields[$name]['enabled'] = $enabled;
        }
        $this->message = 'Selection updated. Save and apply to change collection.';
    }

    public function save(): void
    {
        $vehicle = $this->vehicle();
        $this->validate([
            'fields' => 'required|array:'.implode(',', array_keys(TelemetryFields::catalog())),
            'fields.*' => 'array:enabled,interval_seconds,minimum_delta',
            'fields.*.enabled' => 'required|boolean',
        ]);
        $selected = [];
        foreach ($this->fields as $name => $options) {
            if ($options['enabled']) {
                $selected[$name] = ['interval_seconds' => $options['interval_seconds'] ?? null];
                if (($options['minimum_delta'] ?? '') !== '') {
                    $selected[$name]['minimum_delta'] = $options['minimum_delta'];
                }
            }
        }
        $vehicle->update(['telemetry_fields' => TelemetryFields::validate($selected)]);
        $this->message = 'Preferences saved. Apply saved preferences to update the vehicle.';
    }

    public function apply(TeslaAuthService $teslaAuth): void
    {
        $vehicle = $this->vehicle();
        $this->resetErrorBag();
        $this->message = '';
        if (! $vehicle->tesla_access_token || $vehicle->tesla_token_expires_at?->isPast()) {
            $this->addError('telemetry', 'Reconnect your Tesla account before applying preferences.');

            return;
        }
        try {
            $teslaAuth->configureFleetTelemetry($vehicle->tesla_access_token, $vehicle->vin);
            $this->message = $vehicle->telemetry_fields === []
                ? 'Telemetry disabled for this application. Existing history is retained.'
                : 'Saved preferences sent to Tesla. The vehicle will adopt them when connected.';
        } catch (\Exception $e) {
            Log::warning('Could not apply telemetry preferences', ['vehicle_id' => $vehicle->id, 'error' => $e->getMessage()]);
            $this->addError('telemetry', 'Tesla did not accept the configuration. Check the connection, key pairing and server logs, then retry.');
        }
    }

    public function render()
    {
        $search = strtolower(trim($this->search));
        $catalog = array_filter(TelemetryFields::catalog(), fn ($metadata, $name) => $search === ''
            || str_contains(strtolower($name.' '.$metadata['category']), $search), ARRAY_FILTER_USE_BOTH);

        return view('livewire.telemetry-settings', [
            'vehicles' => Auth::user()->vehicles()->get(),
            'groups' => collect($catalog)->groupBy('category', preserveKeys: true),
            'total' => count(TelemetryFields::catalog()),
        ]);
    }
}
