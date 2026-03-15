<div class="space-y-6">
    {{-- Back link --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('web.charges') }}" onclick="if (document.referrer.includes('/charges')) { event.preventDefault(); history.back(); }"
           class="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text-primary">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to Charges
        </a>
        @if(auth()->user()->debug_mode)
            <a href="{{ route('web.debug', ['vehicleFilter' => $charge->vehicle_id, 'from' => $charge->started_at->copy()->subMinutes(5)->tz(auth()->user()->userTz())->format('Y-m-d\TH:i'), 'to' => $charge->ended_at->copy()->addMinutes(5)->tz(auth()->user()->userTz())->format('Y-m-d\TH:i'), 'stateFilter' => 'charging']) }}"
               class="inline-flex items-center gap-1 text-sm text-red-400 hover:text-red-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                View Raw Data
            </a>
        @endif
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold">{{ $charge->started_at->tz($userTz)->format('M j, Y') }}</h2>
            <p class="text-sm text-text-muted">{{ $charge->started_at->tz($userTz)->format('g:ia') }} — {{ $charge->ended_at->tz($userTz)->format('g:ia') }} · {{ $this->duration }} · {{ $charge->vehicle->name }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-medium
            @if($charge->charge_type === \App\Enums\ChargeType::Supercharger) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
            @elseif($charge->charge_type === \App\Enums\ChargeType::Dc) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
            @else bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
            @endif">
            {{ $charge->charge_type->label() }}
        </span>
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Energy Added</div>
            <div class="mt-1 text-2xl font-bold">{{ $charge->energy_added_kwh ? number_format($charge->energy_added_kwh, 1) : '—' }}</div>
            <div class="text-xs text-text-subtle">kWh</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Max Power</div>
            <div class="mt-1 text-2xl font-bold">{{ $charge->max_charger_power ? number_format($charge->max_charger_power, 0) : '—' }}</div>
            <div class="text-xs text-text-subtle">kW</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Battery</div>
            <div class="mt-1 text-2xl font-bold">{{ $charge->start_battery_level !== null ? number_format($charge->start_battery_level, 0) : '—' }}%</div>
            <div class="text-xs text-text-subtle">→ {{ $charge->end_battery_level !== null ? number_format($charge->end_battery_level, 0) : '—' }}%</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Cost</div>
            <div class="mt-1 text-2xl font-bold">{{ $charge->cost ? '$' . number_format($charge->cost, 2) : '—' }}</div>
            <div class="text-xs text-text-subtle">{{ $charge->cost && $charge->energy_added_kwh ? '$' . number_format($charge->cost / $charge->energy_added_kwh, 3) . '/kWh' : '' }}</div>
        </div>
    </div>

    {{-- Range --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Duration</div>
            <div class="mt-1 text-lg font-semibold">{{ $this->duration }}</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Range Added</div>
            <div class="mt-1 text-lg font-semibold">
                @if($charge->start_rated_range && $charge->end_rated_range)
                    {{ number_format(auth()->user()->convertDistance($charge->end_rated_range - $charge->start_rated_range), 0) }} {{ auth()->user()->distanceUnit() }}
                @else
                    —
                @endif
            </div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">Start Range</div>
            <div class="mt-1 text-lg font-semibold">{{ $charge->start_rated_range ? number_format(auth()->user()->convertDistance($charge->start_rated_range), 0) . ' ' . auth()->user()->distanceUnit() : '—' }}</div>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="text-sm text-text-muted">End Range</div>
            <div class="mt-1 text-lg font-semibold">{{ $charge->end_rated_range ? number_format(auth()->user()->convertDistance($charge->end_rated_range), 0) . ' ' . auth()->user()->distanceUnit() : '—' }}</div>
        </div>
    </div>

    {{-- Location --}}
    @if($charge->address || ($charge->latitude && $charge->longitude))
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-medium uppercase text-text-subtle">Location</span>
                @if($charge->latitude && $charge->longitude && !$charge->place)
                    <a href="{{ route('web.places') }}?lat={{ $charge->latitude }}&lng={{ $charge->longitude }}&name={{ urlencode($charge->address ?? '') }}"
                       class="text-xs text-red-400 hover:text-red-300">Save as Place</a>
                @endif
            </div>
            <div class="text-sm text-text-secondary">
                @if($charge->place)
                    <span class="inline-flex items-center gap-1">
                        <svg class="h-3.5 w-3.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        {{ $charge->place->name }}
                    </span>
                    @if($charge->address)
                        <div class="mt-0.5 text-xs text-text-subtle">{{ $charge->address }}</div>
                    @endif
                @else
                    {{ $charge->address ?? 'Unknown location' }}
                @endif
            </div>
            @if($charge->latitude && $charge->longitude)
                <div class="mt-1 text-xs text-text-faint">{{ number_format($charge->latitude, 5) }}, {{ number_format($charge->longitude, 5) }}</div>

                {{-- Location Map --}}
                <div wire:ignore>
                    <div id="charge-map" style="height: 12rem; width: 100%; margin-top: 0.75rem; background: var(--theme-surface); border-radius: 0.5rem;"></div>
                    <script>
                        (function() {
                            function initMap() {
                                var el = document.getElementById('charge-map');
                                if (!el || !window.L) return;
                                var map = L.map(el, { attributionControl: false });
                                L.tileLayer(window.getMapTileUrl(), { maxZoom: 19 }).addTo(map);
                                L.circleMarker([{{ $charge->latitude }}, {{ $charge->longitude }}], { radius: 8, color: '#22c55e', fillColor: '#22c55e', fillOpacity: 1, weight: 2 }).addTo(map);
                                map.setView([{{ $charge->latitude }}, {{ $charge->longitude }}], 15);
                                setTimeout(function() { map.invalidateSize(); }, 300);
                            }
                            if (document.readyState === 'complete') { initMap(); }
                            else { window.addEventListener('load', initMap); }
                        })();
                    </script>
                </div>
            @endif
        </div>
    @endif

    {{-- Charge Curve --}}
    @if($points->count() > 1)
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <h3 class="mb-3 text-sm font-medium text-text-muted">Charge Curve</h3>
            <div class="h-64" wire:ignore>
                <canvas id="charge-curve-chart"></canvas>
            </div>
        </div>

        {{-- Power Over Time --}}
        @if($points->whereNotNull('charger_power_kw')->count() > 1)
            <div class="rounded-xl border border-border-default bg-surface p-4">
                <h3 class="mb-3 text-sm font-medium text-text-muted">Charger Power</h3>
                <div class="h-48" wire:ignore>
                    <canvas id="power-chart"></canvas>
                </div>
            </div>
        @endif
    @endif

    {{-- Notes --}}
    @if($charge->notes)
        <div class="rounded-xl border border-border-default bg-surface p-4">
            <h3 class="mb-2 text-sm font-medium text-text-muted">Notes</h3>
            <p class="text-sm text-text-secondary">{{ $charge->notes }}</p>
        </div>
    @endif
</div>

@script
<script>
    const chartData = @js($points->map(fn ($p) => ['time' => $p->timestamp->tz($userTz)->format('g:ia'), 'battery' => $p->battery_level, 'power' => $p->charger_power_kw, 'voltage' => $p->voltage, 'current' => $p->current, 'range' => $p->rated_range])->values());

    if (window.Chart) {
        const cc = window.getChartColors();

        // Charge curve
        const curveEl = document.getElementById('charge-curve-chart');
        if (curveEl && chartData.length > 1) {
            new Chart(curveEl, {
                type: 'line',
                data: {
                    labels: chartData.map(d => d.time),
                    datasets: [{
                        label: 'Battery %',
                        data: chartData.map(d => d.battery),
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
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

        // Power chart
        const powerEl = document.getElementById('power-chart');
        if (powerEl && chartData.some(d => d.power)) {
            const filtered = chartData.filter(d => d.power !== null);
            new Chart(powerEl, {
                type: 'line',
                data: {
                    labels: filtered.map(d => d.time),
                    datasets: [{
                        label: 'Power (kW)',
                        data: filtered.map(d => d.power),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: cc.tick, maxTicksLimit: 8 }, grid: { color: cc.grid } },
                        y: { ticks: { color: cc.tick, callback: v => v + ' kW' }, grid: { color: cc.grid }, beginAtZero: true },
                    },
                },
            });
        }
    }
</script>
@endscript
