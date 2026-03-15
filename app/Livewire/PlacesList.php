<?php

namespace App\Livewire;

use App\Models\Charge;
use App\Models\Drive;
use App\Models\Place;
use App\Models\PlaceTouRate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PlacesList extends Component
{
    public ?int $editingId = null;
    public string $editName = '';
    public float $editLat = 0;
    public float $editLng = 0;
    public int $editRadius = 50;
    public ?float $editCostPerKwh = null;
    public string $editAutoTag = '';
    public string $pricingMode = 'flat'; // 'flat' or 'tou'
    public array $touRates = [];

    // For creating a new place
    public bool $creating = false;

    public function mount()
    {
        // Support creating from query params (e.g., from drive/charge detail "Save as Place" link)
        $lat = request()->query('lat');
        $lng = request()->query('lng');
        if ($lat && $lng) {
            $this->editLat = (float) $lat;
            $this->editLng = (float) $lng;
            $this->editName = request()->query('name', '');
            $this->editRadius = 50;
            $this->creating = true;
        }
    }

    public function createPlace()
    {
        $this->reset('editingId', 'editName', 'editLat', 'editLng', 'editRadius', 'editCostPerKwh', 'editAutoTag', 'pricingMode', 'touRates');
        $this->editRadius = 50;
        $this->creating = true;
    }

    public function createFromLocation(float $lat, float $lng, string $address = '')
    {
        $this->editLat = $lat;
        $this->editLng = $lng;
        $this->editName = $address;
        $this->editRadius = 50;
        $this->editCostPerKwh = null;
        $this->editAutoTag = '';
        $this->pricingMode = 'flat';
        $this->touRates = [];
        $this->editingId = null;
        $this->creating = true;
    }

    public function editPlace(int $id)
    {
        $place = Place::where('user_id', Auth::id())->findOrFail($id);
        $this->editingId = $place->id;
        $this->editName = $place->name;
        $this->editLat = $place->latitude;
        $this->editLng = $place->longitude;
        $this->editRadius = $place->radius_meters;
        $this->editCostPerKwh = $place->electricity_cost_per_kwh;
        $this->editAutoTag = $place->auto_tag ?? '';
        $this->creating = false;

        $existingRates = $place->touRates()->orderBy('day_of_week')->orderBy('start_time')->get();
        if ($existingRates->isNotEmpty()) {
            $this->pricingMode = 'tou';
            $this->touRates = $existingRates->map(fn ($r) => [
                'day_of_week' => (string) $r->day_of_week,
                'start_time' => substr($r->start_time, 0, 5),
                'end_time' => substr($r->end_time, 0, 5),
                'rate_per_kwh' => (string) $r->rate_per_kwh,
            ])->all();
        } else {
            $this->pricingMode = $place->electricity_cost_per_kwh ? 'flat' : 'flat';
            $this->touRates = [];
        }
    }

    public function savePlace()
    {
        $rules = [
            'editName' => 'required|string|max:255',
            'editLat' => 'required|numeric|between:-90,90',
            'editLng' => 'required|numeric|between:-180,180',
            'editRadius' => 'required|integer|min:10|max:5000',
            'editAutoTag' => 'nullable|string|max:100',
        ];

        if ($this->pricingMode === 'flat') {
            $rules['editCostPerKwh'] = 'nullable|numeric|min:0';
        } else {
            $rules['touRates'] = 'required|array|min:1';
            $rules['touRates.*.day_of_week'] = 'required|integer|between:0,6';
            $rules['touRates.*.start_time'] = 'required|date_format:H:i';
            $rules['touRates.*.end_time'] = 'required|date_format:H:i';
            $rules['touRates.*.rate_per_kwh'] = 'required|numeric|min:0';
        }

        $this->validate($rules);

        $data = [
            'user_id' => Auth::id(),
            'name' => $this->editName,
            'latitude' => $this->editLat,
            'longitude' => $this->editLng,
            'radius_meters' => $this->editRadius,
            'electricity_cost_per_kwh' => $this->pricingMode === 'flat' ? ($this->editCostPerKwh ?: null) : null,
            'auto_tag' => $this->editAutoTag ?: null,
        ];

        if ($this->editingId) {
            $place = Place::where('user_id', Auth::id())->findOrFail($this->editingId);
            $place->update($data);
        } else {
            $place = Place::create($data);
        }

        // Save ToU rates
        $place->touRates()->delete();
        if ($this->pricingMode === 'tou') {
            foreach ($this->touRates as $rate) {
                $place->touRates()->create([
                    'day_of_week' => (int) $rate['day_of_week'],
                    'start_time' => $rate['start_time'],
                    'end_time' => $rate['end_time'],
                    'rate_per_kwh' => (float) $rate['rate_per_kwh'],
                ]);
            }
        }

        // Rematch drives and charges to this place
        $this->rematchPlace($place);

        $this->cancelEdit();
    }

    public function deletePlace(int $id)
    {
        $place = Place::where('user_id', Auth::id())->findOrFail($id);

        // Clear place references from drives/charges
        Drive::where('start_place_id', $id)->update(['start_place_id' => null]);
        Drive::where('end_place_id', $id)->update(['end_place_id' => null]);
        Charge::where('place_id', $id)->update(['place_id' => null]);

        $place->delete();
    }

    public function addTouRate()
    {
        $this->touRates[] = [
            'day_of_week' => '1',
            'start_time' => '00:00',
            'end_time' => '23:59',
            'rate_per_kwh' => '',
        ];
    }

    public function removeTouRate(int $index)
    {
        unset($this->touRates[$index]);
        $this->touRates = array_values($this->touRates);
    }

    public function cancelEdit()
    {
        $this->reset('editingId', 'editName', 'editLat', 'editLng', 'editRadius', 'editCostPerKwh', 'editAutoTag', 'creating', 'pricingMode', 'touRates');
    }

    private function rematchPlace(Place $place): void
    {
        // Match drive start locations
        $drives = Drive::whereHas('vehicle', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereNotNull('start_latitude')
            ->whereNotNull('start_longitude')
            ->get();

        foreach ($drives as $drive) {
            $startDist = $this->haversineMeters($drive->start_latitude, $drive->start_longitude, $place->latitude, $place->longitude);
            if ($startDist <= $place->radius_meters) {
                $drive->start_place_id = $place->id;
            } elseif ($drive->start_place_id === $place->id) {
                $drive->start_place_id = null;
            }

            $endDist = $this->haversineMeters($drive->end_latitude, $drive->end_longitude, $place->latitude, $place->longitude);
            if ($endDist <= $place->radius_meters) {
                $drive->end_place_id = $place->id;
            } elseif ($drive->end_place_id === $place->id) {
                $drive->end_place_id = null;
            }

            if ($drive->isDirty()) {
                $drive->save();
            }
        }

        // Match charge locations
        $charges = Charge::whereHas('vehicle', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $chargeCost = app(\App\Services\ChargeCostService::class);
        $userTz = Auth::user()->timezone ?? 'UTC';

        foreach ($charges as $charge) {
            $dist = $this->haversineMeters($charge->latitude, $charge->longitude, $place->latitude, $place->longitude);
            if ($dist <= $place->radius_meters) {
                $charge->place_id = $place->id;
            } elseif ($charge->place_id === $place->id) {
                $charge->place_id = null;
            }

            if ($charge->isDirty()) {
                $charge->save();
                $chargeCost->calculateCost($charge->fresh(), $userTz);
            }
        }
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function render()
    {
        $places = Place::where('user_id', Auth::id())->withCount('touRates')->orderBy('name')->get();

        $placesMapData = $places->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'lat' => $p->latitude,
                'lng' => $p->longitude,
                'radius' => $p->radius_meters,
            ];
        })->values();

        $showOverview = $places->isNotEmpty() && ! $this->creating && ! $this->editingId;
        if ($showOverview) {
            $this->dispatch('places-map-updated', places: $placesMapData);
        }

        $showEdit = $this->creating || $this->editingId;
        if ($showEdit) {
            $this->dispatch('place-edit-map-init', lat: $this->editLat ?: 39.8283, lng: $this->editLng ?: -98.5795, radius: $this->editRadius, hasLocation: (bool) ($this->editLat && $this->editLng));
        }

        return view('livewire.places-list', [
            'places' => $places,
            'placesMapData' => $placesMapData,
        ]);
    }
}
