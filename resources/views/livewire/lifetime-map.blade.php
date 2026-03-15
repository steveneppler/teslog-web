<div class="space-y-4">
    {{-- Controls --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            @foreach($vehicles as $vehicle)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border-input bg-surface px-3 py-2 text-sm transition hover:bg-surface-alt"
                    style="border-color: {{ $vehicleColorMap[$vehicle->id] }}40">
                    <input type="checkbox" wire:model.live="selectedVehicles" value="{{ $vehicle->id }}"
                        class="rounded border-border-strong bg-surface-alt text-red-500 focus:ring-red-500">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $vehicleColorMap[$vehicle->id] }}"></span>
                    <span class="text-text-secondary">{{ $vehicle->name ?: $vehicle->vin }}</span>
                </label>
            @endforeach
        </div>
        <div class="flex items-center gap-4 text-sm text-text-muted">
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border-input bg-surface px-3 py-2 transition hover:bg-surface-alt">
                <input type="checkbox" wire:model.live="showCharges"
                    class="rounded border-border-strong bg-surface-alt text-green-500 focus:ring-green-500">
                <span class="text-text-secondary">Charging Stops</span>
            </label>
            @if($showCharges)
                <label class="flex cursor-pointer items-center gap-1.5 text-sm">
                    <input type="checkbox" wire:model.live="chargeTypes" value="supercharger"
                        class="rounded border-border-strong bg-surface-alt text-red-500 focus:ring-red-500">
                    <span class="inline-block h-2 w-2 rounded-full bg-red-500"></span>
                    <span class="text-text-muted">Supercharger</span>
                </label>
                <label class="flex cursor-pointer items-center gap-1.5 text-sm">
                    <input type="checkbox" wire:model.live="chargeTypes" value="dc"
                        class="rounded border-border-strong bg-surface-alt text-blue-500 focus:ring-blue-500">
                    <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                    <span class="text-text-muted">DC</span>
                </label>
                <label class="flex cursor-pointer items-center gap-1.5 text-sm">
                    <input type="checkbox" wire:model.live="chargeTypes" value="ac"
                        class="rounded border-border-strong bg-surface-alt text-green-500 focus:ring-green-500">
                    <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                    <span class="text-text-muted">AC</span>
                </label>
            @endif
        </div>
    </div>

    {{-- Map --}}
    <div id="lifetime-map-wrapper" class="relative rounded-xl border border-border-default bg-surface p-2" wire:ignore>
        <div id="lifetime-map" style="height: calc(100vh - 20rem); width: 100%; background: var(--theme-surface); border-radius: 0.5rem;"></div>
        <button data-fullscreen-btn
            class="absolute bottom-4 right-4 z-[10000] rounded-lg border border-border-default bg-surface p-2 shadow-md transition hover:bg-surface-alt"
            title="Toggle fullscreen">
            <svg data-fullscreen-enter xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5" />
            </svg>
            <svg data-fullscreen-exit xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5 text-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4" />
            </svg>
        </button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Drives</p>
            <p class="text-lg font-semibold text-text-primary">{{ number_format($stats['drives']) }}</p>
        </div>
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Distance</p>
            <p class="text-lg font-semibold text-text-primary">{{ number_format(auth()->user()->convertDistance($stats['distance']), 0) }} <span class="text-sm text-text-muted">{{ auth()->user()->distanceUnit() }}</span></p>
        </div>
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Drive Time</p>
            <p class="text-lg font-semibold text-text-primary">{{ number_format($stats['drive_hours'], 0) }} <span class="text-sm text-text-muted">hrs</span></p>
        </div>
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Energy Used</p>
            <p class="text-lg font-semibold text-text-primary">{{ number_format($stats['energy_used'], 0) }} <span class="text-sm text-text-muted">kWh</span></p>
        </div>
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Efficiency</p>
            <p class="text-lg font-semibold text-text-primary">
                @if($stats['mi_per_kwh'])
                    {{ round(auth()->user()->convertEfficiency(1000 / $stats['mi_per_kwh'])) }} <span class="text-sm text-text-muted">{{ auth()->user()->efficiencyUnit() }}</span>
                    <span class="text-sm text-text-subtle">&middot;</span>
                    {{ number_format(auth()->user()->convertEfficiencyAlt($stats['mi_per_kwh']), 1) }} <span class="text-sm text-text-muted">{{ auth()->user()->efficiencyUnitAlt() }}</span>
                @else
                    <span class="text-text-subtle">&mdash;</span>
                @endif
            </p>
        </div>
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Top Speed</p>
            <p class="text-lg font-semibold text-text-primary">
                @if($stats['max_speed'])
                    {{ number_format(auth()->user()->convertSpeed($stats['max_speed']), 0) }} <span class="text-sm text-text-muted">{{ auth()->user()->speedUnit() }}</span>
                @else
                    <span class="text-text-subtle">&mdash;</span>
                @endif
            </p>
        </div>
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Charges</p>
            <p class="text-lg font-semibold text-text-primary">{{ number_format($stats['charges']) }}</p>
        </div>
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Energy Added</p>
            <p class="text-lg font-semibold text-text-primary">{{ number_format($stats['energy_added'], 0) }} <span class="text-sm text-text-muted">kWh</span></p>
        </div>
        @if($stats['charge_cost'])
            <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
                <p class="text-xs text-text-subtle">Charge Cost</p>
                <p class="text-lg font-semibold text-text-primary">${{ number_format($stats['charge_cost'], 2) }}</p>
            </div>
        @endif
        <div class="rounded-lg border border-border-default bg-surface px-4 py-3">
            <p class="text-xs text-text-subtle">Charge Locations</p>
            <p class="text-lg font-semibold text-text-primary">{{ number_format($stats['charge_locations']) }}</p>
        </div>
        @if($stats['first_drive'] && $stats['last_drive'])
            <div class="rounded-lg border border-border-default bg-surface px-4 py-3 sm:col-span-2">
                <p class="text-xs text-text-subtle">Date Range</p>
                <p class="text-sm font-semibold text-text-primary">
                    {{ \Illuminate\Support\Carbon::parse($stats['first_drive'])->format('M j, Y') }}
                    &ndash;
                    {{ \Illuminate\Support\Carbon::parse($stats['last_drive'])->format('M j, Y') }}
                </p>
            </div>
        @endif
    </div>
</div>

@script
<script>
    var __lifetimeMap = null;
    var __lifetimeLayer = null;
    var __chargeLayer = null;

    function initLifetimeMap(routes, charges) {
        if (!window.L) return;

        var el = document.getElementById('lifetime-map');
        if (!el) return;

        if (!__lifetimeMap) {
            __lifetimeMap = L.map(el, { attributionControl: false, zoomControl: true });
            L.tileLayer(window.getMapTileUrl(), { maxZoom: 19 }).addTo(__lifetimeMap);
            window.registerMap(__lifetimeMap);
        }

        // Clear previous routes
        if (__lifetimeLayer) {
            __lifetimeMap.removeLayer(__lifetimeLayer);
        }
        __lifetimeLayer = L.layerGroup().addTo(__lifetimeMap);

        // Clear previous charge markers
        if (__chargeLayer) {
            __lifetimeMap.removeLayer(__chargeLayer);
        }
        __chargeLayer = L.layerGroup().addTo(__lifetimeMap);

        if ((!routes || routes.length === 0) && (!charges || charges.length === 0)) {
            __lifetimeMap.setView([39.8283, -98.5795], 4);
            return;
        }

        var allBounds = [];

        routes.forEach(function(route) {
            if (!route.coords || route.coords.length < 2) return;
            var latlngs = route.coords.map(function(c) { return [c[0], c[1]]; });
            L.polyline(latlngs, {
                color: route.color,
                weight: 2,
                opacity: 0.6,
                smoothFactor: 1,
            }).addTo(__lifetimeLayer);
            allBounds = allBounds.concat(latlngs);
        });

        // Add charge markers
        if (charges && charges.length > 0) {
            charges.forEach(function(m) {
                var color = m.type === 'supercharger' ? '#ef4444' : (m.type === 'dc' ? '#3b82f6' : '#22c55e');
                var radius = Math.min(12, Math.max(5, m.count * 2));
                var marker = L.circleMarker([m.lat, m.lng], {
                    radius: radius,
                    color: color,
                    fillColor: color,
                    fillOpacity: 0.8,
                    weight: 2,
                });
                var tooltip = '<strong>' + m.label + '</strong><br>' +
                    m.count + (m.count === 1 ? ' charge' : ' charges') + ' · ' +
                    m.energy + ' kWh';
                marker.bindTooltip(tooltip);
                __chargeLayer.addLayer(marker);
                allBounds.push([m.lat, m.lng]);
            });
        }

        if (allBounds.length > 0) {
            __lifetimeMap.fitBounds(L.latLngBounds(allBounds).pad(0.05));
        }

        setTimeout(function() { __lifetimeMap.invalidateSize(); }, 200);
    }

    // Fullscreen toggle — scoped refs, cleanup on Livewire navigation
    var wrapper = document.getElementById('lifetime-map-wrapper');
    var mapEl = document.getElementById('lifetime-map');
    var btn = wrapper.querySelector('[data-fullscreen-btn]');
    var enterIcon = wrapper.querySelector('[data-fullscreen-enter]');
    var exitIcon = wrapper.querySelector('[data-fullscreen-exit]');
    var originalHeight = mapEl.style.height;
    var isFullscreen = false;

    function toggleFullscreen() {
        isFullscreen = !isFullscreen;
        wrapper.classList.toggle('lifetime-map-fullscreen', isFullscreen);
        mapEl.style.height = isFullscreen ? '100vh' : originalHeight;
        enterIcon.classList.toggle('hidden', isFullscreen);
        exitIcon.classList.toggle('hidden', !isFullscreen);
        setTimeout(function() { __lifetimeMap && __lifetimeMap.invalidateSize(); }, 200);
    }

    btn.addEventListener('click', toggleFullscreen);

    function onKeydown(e) {
        if (e.key === 'Escape' && isFullscreen) {
            toggleFullscreen();
        }
    }
    document.addEventListener('keydown', onKeydown);

    $wire.on('lifetime-map-updated', function(params) {
        setTimeout(function() { initLifetimeMap(params.routes, params.charges); }, 100);
    });

    // Cleanup on Livewire navigation
    return function() {
        document.removeEventListener('keydown', onKeydown);
    };
</script>
@endscript
