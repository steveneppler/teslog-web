<div class="space-y-6">
    {{-- Back link --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('web.drives') }}" onclick="if (document.referrer.includes('/drives')) { event.preventDefault(); history.back(); }"
           class="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text-primary">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to Drives
        </a>
        @if(auth()->user()->debug_mode)
            <a href="{{ route('web.debug', ['vehicleFilter' => $drive->vehicle_id, 'from' => $drive->started_at->copy()->subMinutes(5)->tz(auth()->user()->userTz())->format('Y-m-d\TH:i'), 'to' => $drive->ended_at->copy()->addMinutes(5)->tz(auth()->user()->userTz())->format('Y-m-d\TH:i'), 'stateFilter' => 'driving']) }}"
               class="inline-flex items-center gap-1 text-sm text-red-400 hover:text-red-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                View Raw Data
            </a>
        @endif
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold">{{ $drive->started_at->tz($userTz)->format('M j, Y') }}</h2>
            <p class="text-sm text-text-muted">{{ $drive->started_at->tz($userTz)->format('g:ia') }} — {{ $drive->ended_at->tz($userTz)->format('g:ia') }} · {{ $this->duration }} · {{ $drive->vehicle->name }}</p>
        </div>
        @if($drive->tag)
            <span class="rounded-full bg-surface-alt px-3 py-1 text-sm text-text-secondary">{{ $drive->tag }}</span>
        @endif
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Distance</div>
            <div class="mt-1 text-2xl font-bold">{{ $drive->distance ? number_format(auth()->user()->convertDistance($drive->distance), 1) : '—' }}</div>
            <div class="text-xs text-text-subtle">{{ auth()->user()->usesKm() ? 'kilometers' : 'miles' }}</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Energy Used</div>
            <div class="mt-1 text-2xl font-bold">{{ $drive->energy_used_kwh ? number_format($drive->energy_used_kwh, 1) : '—' }}</div>
            <div class="text-xs text-text-subtle">kWh</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Efficiency</div>
            <div class="mt-1 text-2xl font-bold">{{ $drive->efficiency ? number_format(auth()->user()->convertEfficiency($drive->efficiency), 0) : '—' }}</div>
            <div class="text-xs text-text-subtle">{{ auth()->user()->efficiencyUnit() }}</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Max Speed</div>
            <div class="mt-1 text-2xl font-bold">{{ $drive->max_speed ? number_format(auth()->user()->convertSpeed($drive->max_speed), 0) : '—' }}</div>
            <div class="text-xs text-text-subtle">{{ auth()->user()->speedUnit() }}</div>
        </div>
    </div>

    {{-- Secondary stats --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Avg Speed</div>
            <div class="mt-1 text-lg font-semibold">{{ $drive->avg_speed ? number_format(auth()->user()->convertSpeed($drive->avg_speed), 0) . ' ' . auth()->user()->speedUnit() : '—' }}</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Battery</div>
            <div class="mt-1 text-lg font-semibold">{{ $drive->start_battery_level !== null ? number_format($drive->start_battery_level, 0) : '—' }}% → {{ $drive->end_battery_level !== null ? number_format($drive->end_battery_level, 0) : '—' }}%</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Range</div>
            <div class="mt-1 text-lg font-semibold">{{ $drive->start_rated_range ? number_format(auth()->user()->convertDistance($drive->start_rated_range), 0) : '—' }} → {{ $drive->end_rated_range ? number_format(auth()->user()->convertDistance($drive->end_rated_range), 0) : '—' }} {{ auth()->user()->distanceUnit() }}</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Outdoor Temp</div>
            <div class="mt-1 text-lg font-semibold">{{ auth()->user()->formatTemp($drive->outside_temp_avg) ?? '—' }}</div>
        </div>
    </div>

    {{-- From / To --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-medium uppercase text-text-subtle">From</span>
                @if($drive->start_latitude && $drive->start_longitude && !$drive->startPlace)
                    <a href="{{ route('web.places') }}?lat={{ $drive->start_latitude }}&lng={{ $drive->start_longitude }}&name={{ urlencode($drive->start_address ?? '') }}"
                       class="text-xs text-red-400 hover:text-red-300">Save as Place</a>
                @endif
            </div>
            <div class="text-sm text-text-secondary">
                @if($drive->startPlace)
                    <span class="inline-flex items-center gap-1">
                        <svg class="h-3.5 w-3.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        {{ $drive->startPlace->name }}
                    </span>
                    @if($drive->start_address)
                        <div class="mt-0.5 text-xs text-text-subtle">{{ $drive->start_address }}</div>
                    @endif
                @else
                    {{ $drive->start_address ?? 'Unknown location' }}
                @endif
            </div>
            @if($drive->start_latitude && $drive->start_longitude)
                <div class="mt-1 text-xs text-text-faint">{{ number_format($drive->start_latitude, 5) }}, {{ number_format($drive->start_longitude, 5) }}</div>
            @endif
            <div class="mt-1 text-xs text-text-subtle">Odometer: {{ $drive->start_odometer ? number_format(auth()->user()->convertDistance($drive->start_odometer), 1) . ' ' . auth()->user()->distanceUnit() : '—' }}</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-medium uppercase text-text-subtle">To</span>
                @if($drive->end_latitude && $drive->end_longitude && !$drive->endPlace)
                    <a href="{{ route('web.places') }}?lat={{ $drive->end_latitude }}&lng={{ $drive->end_longitude }}&name={{ urlencode($drive->end_address ?? '') }}"
                       class="text-xs text-red-400 hover:text-red-300">Save as Place</a>
                @endif
            </div>
            <div class="text-sm text-text-secondary">
                @if($drive->endPlace)
                    <span class="inline-flex items-center gap-1">
                        <svg class="h-3.5 w-3.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        {{ $drive->endPlace->name }}
                    </span>
                    @if($drive->end_address)
                        <div class="mt-0.5 text-xs text-text-subtle">{{ $drive->end_address }}</div>
                    @endif
                @else
                    {{ $drive->end_address ?? 'Unknown location' }}
                @endif
            </div>
            @if($drive->end_latitude && $drive->end_longitude)
                <div class="mt-1 text-xs text-text-faint">{{ number_format($drive->end_latitude, 5) }}, {{ number_format($drive->end_longitude, 5) }}</div>
            @endif
            <div class="mt-1 text-xs text-text-subtle">Odometer: {{ $drive->end_odometer ? number_format(auth()->user()->convertDistance($drive->end_odometer), 1) . ' ' . auth()->user()->distanceUnit() : '—' }}</div>
        </div>
    </div>

    {{-- Route Map --}}
    @if($points->count() > 0)
        <div class="rounded-xl border border-border-default bg-surface p-4" wire:ignore>
            <h3 class="mb-3 text-sm font-medium text-text-muted">Route</h3>
            <div id="drive-map" style="height: 24rem; width: 100%; background: var(--theme-surface);"></div>
            <script>
                (function() {
                    function speedColor(speed, avg) {
                        if (speed == null || avg == null || avg === 0) return '#6b7280';
                        var ratio = speed / avg;
                        if (ratio <= 0.5) return '#ef4444';
                        if (ratio <= 0.8) return '#f59e0b';
                        if (ratio <= 1.2) return '#22c55e';
                        if (ratio <= 1.5) return '#3b82f6';
                        return '#8b5cf6';
                    }
                    function initMap() {
                        var el = document.getElementById('drive-map');
                        if (!el || !window.L) { console.error('Leaflet not loaded'); return; }
                        var pts = @json($mapPoints);
                        if (pts.length === 0) return;
                        var map = L.map(el, { attributionControl: false });
                        L.tileLayer(window.getMapTileUrl(), { maxZoom: 19 }).addTo(map);
                        window.registerMap(map);
                        var latlngs = pts.map(function(p) { return [p.lat, p.lng]; });
                        var speeds = pts.filter(function(p) { return p.speed != null && p.speed > 0; });
                        var avgSpeed = speeds.length > 0 ? speeds.reduce(function(s, p) { return s + p.speed; }, 0) / speeds.length : 0;
                        for (var i = 0; i < pts.length - 1; i++) {
                            var color = speedColor(pts[i].speed, avgSpeed);
                            L.polyline([[pts[i].lat, pts[i].lng], [pts[i+1].lat, pts[i+1].lng]], { color: color, weight: 4, opacity: 0.85 }).addTo(map);
                        }
                        L.circleMarker(latlngs[0], { radius: 8, color: '#22c55e', fillColor: '#22c55e', fillOpacity: 1, weight: 2 }).bindPopup('Start: ' + pts[0].timestamp).addTo(map);
                        L.circleMarker(latlngs[latlngs.length - 1], { radius: 8, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1, weight: 2 }).bindPopup('End: ' + pts[pts.length - 1].timestamp).addTo(map);
                        map.fitBounds(L.latLngBounds(latlngs).pad(0.1));
                        setTimeout(function() { map.invalidateSize(); }, 300);
                    }
                    if (document.readyState === 'complete') { initMap(); }
                    else { window.addEventListener('load', initMap); }
                })();
            </script>
        </div>

        {{-- Battery Level --}}
        @if($points->whereNotNull('battery_level')->count() > 1)
            <div class="rounded-xl border border-border-default bg-surface p-4">
                <h3 class="mb-3 text-sm font-medium text-text-muted">Battery Level</h3>
                <div class="h-48" wire:ignore>
                    <canvas id="battery-chart"></canvas>
                </div>
            </div>
        @endif

        {{-- Speed Chart --}}
        @if($points->whereNotNull('speed')->count() > 1)
            <div class="rounded-xl border border-border-default bg-surface p-4">
                <h3 class="mb-3 text-sm font-medium text-text-muted">Speed</h3>
                <div class="h-48" wire:ignore>
                    <canvas id="speed-chart"></canvas>
                </div>
            </div>
        @endif

        {{-- Elevation Profile --}}
        @if($points->whereNotNull('altitude')->count() > 1)
            <div class="rounded-xl border border-border-default bg-surface p-4">
                <h3 class="mb-3 text-sm font-medium text-text-muted">Elevation</h3>
                <div class="h-48" wire:ignore>
                    <canvas id="elevation-chart"></canvas>
                </div>
            </div>
        @endif
    @endif

    {{-- Notes --}}
    @if($drive->notes)
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <h3 class="mb-2 text-sm font-medium text-text-muted">Notes</h3>
            <p class="text-sm text-text-secondary">{{ $drive->notes }}</p>
        </div>
    @endif

</div>

@if($points->count() > 0)
@script
<script>
    const points = @js($points->map(fn ($p) => ['lat' => $p->latitude, 'lng' => $p->longitude, 'speed' => auth()->user()->convertSpeed($p->speed), 'battery' => $p->battery_level, 'altitude' => $p->altitude !== null ? round(auth()->user()->convertElevation($p->altitude)) : null, 'timestamp' => $p->timestamp->tz($userTz)->format('g:ia')])->values());
    const speedUnit = @js(auth()->user()->speedUnit());
    const elevationUnit = @js(auth()->user()->elevationUnit());
    const cc = window.getChartColors();

    // Battery level chart
    const batteryEl = document.getElementById('battery-chart');
    if (batteryEl && window.Chart) {
        const filtered = points.filter(p => p.battery !== null);
        if (filtered.length > 1) {
            const ctx = batteryEl.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, batteryEl.parentElement.clientHeight);
            gradient.addColorStop(0, 'rgba(34, 197, 94, 0.2)');
            gradient.addColorStop(1, 'rgba(34, 197, 94, 0)');
            new Chart(batteryEl, {
                type: 'line',
                data: {
                    labels: filtered.map(p => p.timestamp),
                    datasets: [{
                        label: 'Battery %',
                        data: filtered.map(p => p.battery),
                        borderColor: '#22c55e',
                        backgroundColor: gradient,
                        fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: cc.tick, maxTicksLimit: 8 }, grid: { color: cc.grid } },
                        y: { ticks: { color: cc.tick, callback: v => v + '%' }, grid: { color: cc.grid }, min: 0, max: 100 },
                    },
                },
            });
        }
    }

    // Elevation chart
    const elevationEl = document.getElementById('elevation-chart');
    if (elevationEl && window.Chart) {
        const filtered = points.filter(p => p.altitude !== null);
        if (filtered.length > 1) {
            new Chart(elevationEl, {
                type: 'line',
                data: {
                    labels: filtered.map(p => p.timestamp),
                    datasets: [{
                        label: 'Elevation (' + elevationUnit + ')',
                        data: filtered.map(p => p.altitude),
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: cc.tick, maxTicksLimit: 8 }, grid: { color: cc.grid } },
                        y: { ticks: { color: cc.tick }, grid: { color: cc.grid } },
                    },
                },
            });
        }
    }

    // Speed chart
    const speedEl = document.getElementById('speed-chart');
    if (speedEl && window.Chart) {
        const filtered = points.filter(p => p.speed !== null);
        if (filtered.length > 1) {
            new Chart(speedEl, {
                type: 'line',
                data: {
                    labels: filtered.map(p => p.timestamp),
                    datasets: [{
                        label: 'Speed (' + speedUnit + ')',
                        data: filtered.map(p => p.speed),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: cc.tick, maxTicksLimit: 8 }, grid: { color: cc.grid } },
                        y: { ticks: { color: cc.tick }, grid: { color: cc.grid }, beginAtZero: true },
                    },
                },
            });
        }
    }
</script>
@endscript
@endif
