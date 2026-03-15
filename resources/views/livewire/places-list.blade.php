<div class="space-y-6">
    {{-- Create / Edit Form --}}
    @if($creating || $editingId)
        <div class="rounded-xl border border-border-default bg-surface p-4 space-y-4">
            <h3 class="text-sm font-medium text-text-muted">{{ $editingId ? 'Edit Place' : 'New Place' }}</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-text-subtle mb-1">Name</label>
                    <input type="text" wire:model="editName" placeholder="Home, Work, Supercharger..."
                        class="w-full rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-text-subtle mb-1">Radius (meters)</label>
                    <input type="range" wire:model.live="editRadius" min="10" max="1000" step="10"
                        class="w-full accent-red-500"
                        id="radius-slider">
                    <div class="flex justify-between text-xs text-text-subtle mt-1">
                        <span>10m</span>
                        <span class="text-text-primary font-medium">{{ $editRadius }}m</span>
                        <span>1000m</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-text-subtle mb-1">Auto Tag</label>
                    <input type="text" wire:model="editAutoTag" placeholder="Optional tag for drives..."
                        class="w-full rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-text-subtle mb-1">Coordinates</label>
                    <div class="text-sm text-text-muted py-2">
                        @if($editLat && $editLng)
                            {{ number_format($editLat, 5) }}, {{ number_format($editLng, 5) }}
                        @else
                            <span class="text-text-faint">Click on the map to set location</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Electricity Pricing --}}
            <div class="rounded-lg border border-border-input bg-surface-alt p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-medium text-text-secondary">Electricity Pricing</label>
                    <div class="flex rounded-md border border-border-input text-xs">
                        <button type="button" wire:click="$set('pricingMode', 'flat')"
                            class="px-3 py-1 rounded-l-md {{ $pricingMode === 'flat' ? 'bg-elevated text-text-primary' : 'text-text-muted hover:text-text-secondary' }}">
                            Flat Rate
                        </button>
                        <button type="button" wire:click="$set('pricingMode', 'tou')"
                            class="px-3 py-1 rounded-r-md border-l border-border-input {{ $pricingMode === 'tou' ? 'bg-elevated text-text-primary' : 'text-text-muted hover:text-text-secondary' }}">
                            Time of Use
                        </button>
                    </div>
                </div>

                @if($pricingMode === 'flat')
                    <div class="max-w-xs">
                        <label class="block text-xs text-text-subtle mb-1">Cost per kWh</label>
                        <div class="flex items-center gap-1">
                            <span class="text-sm text-text-muted">$</span>
                            <input type="number" wire:model="editCostPerKwh" step="0.001" placeholder="0.12"
                                class="w-full rounded-lg border border-border-input bg-page px-3 py-1.5 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                        </div>
                    </div>
                @else
                    @php
                        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    @endphp
                    <div class="space-y-2">
                        @foreach($touRates as $i => $rate)
                            <div class="flex flex-wrap items-center gap-2" wire:key="tou-{{ $i }}">
                                <select wire:model="touRates.{{ $i }}.day_of_week"
                                    class="rounded-lg border border-border-input bg-page px-2 py-1.5 text-xs text-text-primary focus:border-red-500 focus:outline-none">
                                    @foreach($dayNames as $dayIdx => $dayName)
                                        <option value="{{ $dayIdx }}">{{ $dayName }}</option>
                                    @endforeach
                                </select>
                                <input type="time" wire:model="touRates.{{ $i }}.start_time"
                                    class="rounded-lg border border-border-input bg-page px-2 py-1.5 text-xs text-text-primary focus:border-red-500 focus:outline-none">
                                <span class="text-xs text-text-subtle">to</span>
                                <input type="time" wire:model="touRates.{{ $i }}.end_time"
                                    class="rounded-lg border border-border-input bg-page px-2 py-1.5 text-xs text-text-primary focus:border-red-500 focus:outline-none">
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-text-muted">$</span>
                                    <input type="number" wire:model="touRates.{{ $i }}.rate_per_kwh" step="0.001" placeholder="0.12"
                                        class="w-20 rounded-lg border border-border-input bg-page px-2 py-1.5 text-xs text-text-primary focus:border-red-500 focus:outline-none">
                                </div>
                                <button type="button" wire:click="removeTouRate({{ $i }})"
                                    class="text-red-400 hover:text-red-300">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                        @error('touRates') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="addTouRate"
                        class="text-xs text-red-400 hover:text-red-300">
                        + Add Rate
                    </button>
                    <p class="text-xs text-text-faint">Define rates per day/time window. Charges are split across matching windows to calculate cost.</p>
                @endif
            </div>

            {{-- Map for editing --}}
            <div>
                <div id="place-edit-map" style="height: 20rem; width: 100%; background: var(--theme-surface); border-radius: 0.5rem;"></div>
            </div>

            @error('editName') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
            @error('editLat') <p class="text-xs text-red-400">Location is required — click on the map.</p> @enderror

            <div class="flex gap-3">
                <button wire:click="savePlace"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500">
                    {{ $editingId ? 'Update Place' : 'Save Place' }}
                </button>
                <button wire:click="cancelEdit"
                    class="rounded-lg border border-border-input px-4 py-2 text-sm text-text-muted hover:text-text-primary">
                    Cancel
                </button>
            </div>
        </div>
    @else
        <div class="flex items-center justify-between">
            <div></div>
            <button wire:click="createPlace"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500">
                + New Place
            </button>
        </div>
    @endif

    {{-- All Places Map --}}
    @if($places->count() > 0 && !$creating && !$editingId)
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <h3 class="mb-3 text-sm font-medium text-text-muted">All Places</h3>
            <div id="places-overview-map" style="height: 20rem; width: 100%; background: var(--theme-surface); border-radius: 0.5rem;"></div>
        </div>
    @endif

    {{-- Places Table --}}
    @if($places->isEmpty() && !$creating)
        <div class="rounded-xl border border-border-default bg-surface p-12 text-center">
            <p class="text-text-subtle">No places defined yet. Create one to tag your frequent locations.</p>
        </div>
    @elseif($places->count() > 0)
        <div class="overflow-hidden rounded-xl border border-border-default">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface text-xs uppercase text-text-muted">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3 text-right">Radius</th>
                        <th class="px-4 py-3">Auto Tag</th>
                        <th class="px-4 py-3 text-right">Cost/kWh</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @foreach($places as $place)
                        <tr class="bg-page transition hover:bg-surface" wire:key="place-{{ $place->id }}">
                            <td class="px-4 py-3 font-medium text-text-primary">{{ $place->name }}</td>
                            <td class="px-4 py-3 text-xs text-text-subtle">{{ number_format($place->latitude, 5) }}, {{ number_format($place->longitude, 5) }}</td>
                            <td class="px-4 py-3 text-right text-text-muted">{{ $place->radius_meters }}m</td>
                            <td class="px-4 py-3">
                                @if($place->auto_tag)
                                    <span class="rounded-full bg-surface-alt px-2 py-0.5 text-xs text-text-secondary">{{ $place->auto_tag }}</span>
                                @else
                                    <span class="text-text-faint">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-text-muted">
                                @if($place->tou_rates_count > 0)
                                    <span class="rounded-full bg-surface-alt px-2 py-0.5 text-xs">ToU ({{ $place->tou_rates_count }})</span>
                                @elseif($place->electricity_cost_per_kwh)
                                    ${{ number_format($place->electricity_cost_per_kwh, 3) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="editPlace({{ $place->id }})" class="text-xs text-text-muted hover:text-text-primary mr-2">Edit</button>
                                <button wire:click="deletePlace({{ $place->id }})" wire:confirm="Delete {{ $place->name }}?" class="text-xs text-red-400 hover:text-red-300">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@script
<script>
    // Overview map
    var __overviewMap = null;

    function initOverviewMap(places) {
        if (!window.L || !places || places.length === 0) return;

        var el = document.getElementById('places-overview-map');
        if (!el) return;

        if (__overviewMap) {
            try { __overviewMap.remove(); } catch(e) {}
            __overviewMap = null;
        }

        var map = L.map(el, { attributionControl: false });
        L.tileLayer(window.getMapTileUrl(), { maxZoom: 19 }).addTo(map);
        window.registerMap(map);

        var bounds = [];
        places.forEach(function(p) {
            L.circleMarker([p.lat, p.lng], { radius: 6, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1, weight: 2 })
                .bindPopup('<b>' + p.name + '</b><br>' + p.radius + 'm radius')
                .addTo(map);
            L.circle([p.lat, p.lng], { radius: p.radius, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 0.1, weight: 1 }).addTo(map);
            bounds.push([p.lat, p.lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(L.latLngBounds(bounds).pad(0.2));
        }
        setTimeout(function() { map.invalidateSize(); }, 300);
        __overviewMap = map;
    }

    $wire.on('places-map-updated', function(params) {
        setTimeout(function() { initOverviewMap(params.places); }, 100);
    });

    // Edit map
    var __editMap = null;
    var __editMarker = null;
    var __editCircle = null;
    var __editRadius = 50;

    function initEditMap(lat, lng, radius, hasLocation) {
        if (!window.L) return;

        var el = document.getElementById('place-edit-map');
        if (!el) return;

        if (__editMap) {
            try { __editMap.remove(); } catch(e) {}
            __editMap = null;
            __editMarker = null;
            __editCircle = null;
        }

        __editRadius = radius;
        var zoom = hasLocation ? 16 : 4;

        var map = L.map(el, { attributionControl: false });
        L.tileLayer(window.getMapTileUrl(), { maxZoom: 19 }).addTo(map);
        window.registerMap(map);
        map.setView([lat, lng], zoom);

        function setMarker(lat, lng, r) {
            if (__editMarker) { map.removeLayer(__editMarker); map.removeLayer(__editCircle); }
            __editMarker = L.circleMarker([lat, lng], { radius: 6, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1, weight: 2 }).addTo(map);
            __editCircle = L.circle([lat, lng], { radius: r, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 0.15, weight: 1 }).addTo(map);
        }

        if (hasLocation) {
            setMarker(lat, lng, radius);
        }

        map.on('click', function(e) {
            setMarker(e.latlng.lat, e.latlng.lng, __editRadius);
            $wire.set('editLat', Math.round(e.latlng.lat * 100000) / 100000);
            $wire.set('editLng', Math.round(e.latlng.lng * 100000) / 100000);
        });

        // Listen for radius slider changes
        var slider = document.getElementById('radius-slider');
        if (slider) {
            slider.addEventListener('input', function() {
                __editRadius = parseInt(this.value);
                if (__editMarker) {
                    var pos = __editMarker.getLatLng();
                    map.removeLayer(__editCircle);
                    __editCircle = L.circle([pos.lat, pos.lng], { radius: __editRadius, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 0.15, weight: 1 }).addTo(map);
                }
            });
        }

        setTimeout(function() { map.invalidateSize(); }, 300);
        __editMap = map;
    }

    $wire.on('place-edit-map-init', function(params) {
        setTimeout(function() { initEditMap(params.lat, params.lng, params.radius, params.hasLocation); }, 100);
    });
</script>
@endscript
