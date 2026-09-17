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
        {{-- Stats overlay — filled from the map event, since the wrapper is wire:ignore'd --}}
        <div data-stats-overlay
            class="pointer-events-none absolute right-4 top-4 z-[9998] hidden max-w-[80%] rounded-xl border border-border-default bg-surface/85 px-4 py-3 shadow-lg backdrop-blur">
            <p data-overlay-title class="font-semibold text-text-primary"></p>
            <p data-overlay-subtitle class="text-xs text-text-muted"></p>
            <div data-overlay-items class="mt-2 grid grid-cols-2 gap-x-5 gap-y-1.5"></div>
        </div>

        {{-- Display options — kept inside the wrapper so they stay reachable in fullscreen --}}
        <div data-options-panel
            class="absolute bottom-16 right-4 z-[10000] hidden w-64 rounded-xl border border-border-default bg-surface p-4 shadow-lg">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm font-semibold text-text-primary">Display options</p>
                <button type="button" data-options-reset class="text-xs text-text-muted underline transition hover:text-text-secondary">Reset</button>
            </div>

            <label class="block text-xs text-text-subtle" data-weight-label>Line thickness</label>
            <div class="mt-1 flex items-center gap-2">
                <input type="range" data-opt-weight min="1" max="12" step="1"
                    class="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-surface-alt accent-red-500">
                <span data-weight-value class="w-8 shrink-0 text-right text-xs tabular-nums text-text-muted"></span>
            </div>

            <label class="mt-3 block text-xs text-text-subtle">Line opacity</label>
            <div class="mt-1 flex items-center gap-2">
                <input type="range" data-opt-opacity min="10" max="100" step="5"
                    class="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-surface-alt accent-red-500">
                <span data-opacity-value class="w-8 shrink-0 text-right text-xs tabular-nums text-text-muted"></span>
            </div>

            <label class="mt-4 flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" data-opt-stats-overlay
                    class="rounded border-border-strong bg-surface-alt text-red-500 focus:ring-red-500">
                <span class="text-text-secondary">Stats overlay</span>
            </label>

            <label class="mt-3 flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" data-opt-single-color
                    class="rounded border-border-strong bg-surface-alt text-red-500 focus:ring-red-500">
                <span class="text-text-secondary">Single color for all routes</span>
            </label>

            <div data-color-controls class="mt-2">
                <div class="flex items-center gap-2">
                    <input type="color" data-opt-color
                        class="h-8 w-10 cursor-pointer rounded border border-border-input bg-surface p-0.5">
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach(['#e82127', '#ffffff', '#38bdf8', '#22c55e', '#f59e0b', '#a855f7'] as $preset)
                            <button type="button" data-color-preset="{{ $preset }}"
                                class="h-5 w-5 rounded-full border border-border-strong transition hover:scale-110"
                                style="background: {{ $preset }}" title="{{ $preset }}"></button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <button data-options-btn
            class="absolute bottom-4 right-16 z-[10000] rounded-lg border border-border-default bg-surface p-2 shadow-md transition hover:bg-surface-alt"
            title="Display options">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </button>

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
    var __routeLines = [];

    // Route styling lives in the browser: the map holds up to 15k sampled points,
    // so restyling must not cost a Livewire round trip that re-queries all of them.
    var STORAGE_KEY = 'teslog.lifetimeMapStyle';
    var DEFAULT_OPTIONS = {
        weight: 2,
        fullscreenWeight: 5,
        opacity: 0.6,
        singleColor: false,
        color: '#e82127',
        statsOverlay: false,
    };
    var options = Object.assign({}, DEFAULT_OPTIONS);

    function loadOptions() {
        try {
            var stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
            Object.keys(DEFAULT_OPTIONS).forEach(function(key) {
                if (stored[key] !== undefined && stored[key] !== null) options[key] = stored[key];
            });
        } catch (e) {
            // Private browsing or corrupt value — the defaults are fine.
        }
    }

    function saveOptions() {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(options));
        } catch (e) {
            // Storage unavailable — styling still applies for this visit.
        }
    }

    function currentWeight() {
        return isFullscreen ? options.fullscreenWeight : options.weight;
    }

    function routeStyle(baseColor) {
        return {
            color: options.singleColor ? options.color : baseColor,
            weight: currentWeight(),
            opacity: options.opacity,
        };
    }

    function applyRouteStyles() {
        __routeLines.forEach(function(entry) {
            entry.line.setStyle(routeStyle(entry.baseColor));
        });
    }

    var __overlayData = null;

    function renderStatsOverlay(overlay) {
        if (overlay) __overlayData = overlay;
        if (!overlayEl) return;

        var data = __overlayData;
        overlayEl.classList.toggle('hidden', !options.statsOverlay || !data);
        if (!options.statsOverlay || !data) return;

        // Titles and units come from user-entered vehicle names, so build the
        // overlay as text nodes rather than markup.
        overlayTitle.textContent = data.title || '';
        overlaySubtitle.textContent = data.subtitle || '';
        overlaySubtitle.classList.toggle('hidden', !data.subtitle);

        overlayItems.textContent = '';
        (data.items || []).forEach(function(item) {
            var cell = document.createElement('div');

            var label = document.createElement('p');
            label.className = 'text-[10px] uppercase tracking-wide text-text-subtle';
            label.textContent = item.label;

            var value = document.createElement('p');
            value.className = 'font-semibold leading-tight text-text-primary';
            value.textContent = item.value;

            cell.appendChild(label);
            cell.appendChild(value);
            overlayItems.appendChild(cell);
        });

        // Fullscreen is watched from further away, so the overlay scales up with it.
        overlayEl.classList.toggle('text-base', isFullscreen);
        overlayEl.classList.toggle('px-6', isFullscreen);
        overlayEl.classList.toggle('py-5', isFullscreen);
        overlayTitle.classList.toggle('text-2xl', isFullscreen);
        overlayItems.classList.toggle('gap-x-8', isFullscreen);
    }

    function initLifetimeMap(routes, charges, overlay) {
        renderStatsOverlay(overlay);

        if (!window.L) return;

        var el = document.getElementById('lifetime-map');
        if (!el) return;

        if (!__lifetimeMap) {
            __lifetimeMap = L.map(el, { zoomControl: true });
            window.addMapTileLayer(__lifetimeMap);
            window.registerMap(__lifetimeMap);
            window.setupMapScrollZoom(__lifetimeMap);
        }

        // Clear previous routes
        if (__lifetimeLayer) {
            __lifetimeMap.removeLayer(__lifetimeLayer);
        }
        __lifetimeLayer = L.layerGroup().addTo(__lifetimeMap);
        __routeLines = [];

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
            var line = L.polyline(latlngs, Object.assign({
                smoothFactor: 1,
            }, routeStyle(route.color))).addTo(__lifetimeLayer);
            __routeLines.push({ line: line, baseColor: route.color });
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
                var tooltip = '<strong>' + window.escapeHtml(m.label) + '</strong><br>' +
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
        window.setMapFreePan && window.setMapFreePan(__lifetimeMap, isFullscreen);
        syncOptionsPanel();
        applyRouteStyles();
        renderStatsOverlay();
        setTimeout(function() { __lifetimeMap && __lifetimeMap.invalidateSize(); }, 200);
    }

    btn.addEventListener('click', toggleFullscreen);

    // Display options panel
    var panel = wrapper.querySelector('[data-options-panel]');
    var optionsBtn = wrapper.querySelector('[data-options-btn]');
    var weightInput = wrapper.querySelector('[data-opt-weight]');
    var weightLabel = wrapper.querySelector('[data-weight-label]');
    var weightValue = wrapper.querySelector('[data-weight-value]');
    var opacityInput = wrapper.querySelector('[data-opt-opacity]');
    var opacityValue = wrapper.querySelector('[data-opacity-value]');
    var singleColorInput = wrapper.querySelector('[data-opt-single-color]');
    var colorInput = wrapper.querySelector('[data-opt-color]');
    var colorControls = wrapper.querySelector('[data-color-controls]');
    var statsOverlayInput = wrapper.querySelector('[data-opt-stats-overlay]');
    var resetBtn = wrapper.querySelector('[data-options-reset]');
    var overlayEl = wrapper.querySelector('[data-stats-overlay]');
    var overlayTitle = wrapper.querySelector('[data-overlay-title]');
    var overlaySubtitle = wrapper.querySelector('[data-overlay-subtitle]');
    var overlayItems = wrapper.querySelector('[data-overlay-items]');

    loadOptions();

    function syncOptionsPanel() {
        weightInput.value = currentWeight();
        weightValue.textContent = currentWeight() + 'px';
        weightLabel.textContent = isFullscreen ? 'Line thickness (fullscreen)' : 'Line thickness';
        opacityInput.value = Math.round(options.opacity * 100);
        opacityValue.textContent = Math.round(options.opacity * 100) + '%';
        statsOverlayInput.checked = options.statsOverlay;
        singleColorInput.checked = options.singleColor;
        colorInput.value = options.color;
        colorControls.classList.toggle('hidden', !options.singleColor);
    }

    function updateOptions(changes) {
        Object.assign(options, changes);
        saveOptions();
        syncOptionsPanel();
        applyRouteStyles();
        renderStatsOverlay();
    }

    syncOptionsPanel();

    optionsBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        panel.classList.toggle('hidden');
    });

    panel.addEventListener('click', function(e) { e.stopPropagation(); });

    function onDocumentClick() {
        panel.classList.add('hidden');
    }
    document.addEventListener('click', onDocumentClick);

    weightInput.addEventListener('input', function() {
        var weight = parseInt(this.value, 10);
        updateOptions(isFullscreen ? { fullscreenWeight: weight } : { weight: weight });
    });

    opacityInput.addEventListener('input', function() {
        updateOptions({ opacity: parseInt(this.value, 10) / 100 });
    });

    statsOverlayInput.addEventListener('change', function() {
        updateOptions({ statsOverlay: this.checked });
    });

    singleColorInput.addEventListener('change', function() {
        updateOptions({ singleColor: this.checked });
    });

    colorInput.addEventListener('input', function() {
        updateOptions({ singleColor: true, color: this.value });
    });

    wrapper.querySelectorAll('[data-color-preset]').forEach(function(swatch) {
        swatch.addEventListener('click', function() {
            updateOptions({ singleColor: true, color: this.getAttribute('data-color-preset') });
        });
    });

    resetBtn.addEventListener('click', function() {
        updateOptions(Object.assign({}, DEFAULT_OPTIONS));
    });

    function onKeydown(e) {
        if (e.key !== 'Escape') return;

        if (!panel.classList.contains('hidden')) {
            panel.classList.add('hidden');
        } else if (isFullscreen) {
            toggleFullscreen();
        }
    }
    document.addEventListener('keydown', onKeydown);

    $wire.on('lifetime-map-updated', function(params) {
        setTimeout(function() { initLifetimeMap(params.routes, params.charges, params.overlay); }, 100);
    });

    // Cleanup on Livewire navigation
    return function() {
        document.removeEventListener('keydown', onKeydown);
        document.removeEventListener('click', onDocumentClick);
    };
</script>
@endscript
