<div>
    {{-- Navigation & filters --}}
    <div class="relative z-10 mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            @if($period !== 'all')
                <button wire:click="previous" class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-secondary hover:bg-elevated">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
            @endif
            <div class="relative" x-data="{ picking: false }">
                <button x-on:click="picking = !picking" class="text-lg font-semibold text-text-primary hover:text-text-primary">
                    {{ $periodLabel }}
                </button>
                @if($period !== 'all')
                    <div x-cloak x-show="picking" x-on:click.outside="picking = false" x-transition
                         class="absolute left-1/2 top-full z-20 mt-2 -translate-x-1/2 rounded-lg border border-border-input bg-surface-alt p-3 shadow-xl">
                        @if($period === 'week')
                            <input type="date" value="{{ $week }}"
                                x-on:change="$wire.jumpTo($event.target.value); picking = false"
                                class="rounded-lg border border-border-strong bg-surface px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                        @elseif($period === 'month')
                            <select x-on:change="$wire.jumpTo($event.target.value); picking = false"
                                class="rounded-lg border border-border-strong bg-surface px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                                @php
                                    $selectedMonth = \Carbon\Carbon::parse($month . '-01');
                                    $oldest = min($selectedMonth->copy()->subMonths(12), now()->subMonths(23));
                                    $newest = now()->startOfMonth();
                                @endphp
                                @for($m = $newest->copy(); $m->gte($oldest); $m = $m->copy()->subMonth())
                                    <option value="{{ $m->format('Y-m') }}" @selected($m->format('Y-m') === $month)>{{ $m->format('F Y') }}</option>
                                @endfor
                            </select>
                        @elseif($period === 'year')
                            <select x-on:change="$wire.jumpTo($event.target.value); picking = false"
                                class="rounded-lg border border-border-strong bg-surface px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                                @for($y = (int) now()->format('Y'); $y >= 2012; $y--)
                                    <option value="{{ $y }}" @selected($y == (int) $year)>{{ $y }}</option>
                                @endfor
                            </select>
                        @endif
                    </div>
                @endif
            </div>
            @if($period !== 'all')
                <button wire:click="next" class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-secondary hover:bg-elevated" @if($isCurrent) disabled @endif style="{{ $isCurrent ? 'opacity: 0.5' : '' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                @if(!$isCurrent)
                    <button wire:click="current" class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-xs text-text-muted hover:bg-elevated">Today</button>
                @endif
            @endif
        </div>
        <div class="flex items-center gap-3">
            <select wire:model.live="period"
                class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                <option value="week">Week</option>
                <option value="month">Month</option>
                <option value="year">Year</option>
                <option value="all">All</option>
            </select>
            @if($vehicles->count() > 1)
                <select wire:model.live="vehicleFilter"
                    class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                    <option value="">All vehicles</option>
                    @foreach($vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="text" wire:model.live="tagFilter" placeholder="Filter by tag..."
                class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
            <button wire:click="toggleBulkMode"
                class="rounded-lg border px-3 py-2 text-sm {{ $bulkMode ? 'border-red-500 bg-red-500/10 text-red-500' : 'border-border-input bg-surface-alt text-text-muted hover:text-text-secondary' }}">
                Bulk Tag
            </button>
        </div>
    </div>

    {{-- Summary map --}}
    <div class="mb-6 rounded-xl border border-border-default bg-surface p-3" style="{{ count($summaryRoutes) === 0 ? 'display:none' : '' }}">
        <div wire:ignore id="drives-summary-map" style="height: 18rem; width: 100%; background: var(--theme-surface); border-radius: 0.5rem;"></div>
    </div>

    {{-- Period summary --}}
    @if($allDrives->isNotEmpty())
        @php
            $totalDistance = $allDrives->sum('distance');
            $totalEnergy = $allDrives->sum('energy_used_kwh');
            $totalSeconds = $allDrives->sum(fn ($d) => $d->started_at && $d->ended_at ? $d->started_at->diffInSeconds($d->ended_at) : 0);
            $totalHours = floor($totalSeconds / 3600);
            $totalMins = floor(($totalSeconds % 3600) / 60);
            $avgEfficiency = $totalDistance > 0 ? ($totalEnergy * 1000) / $totalDistance : null;
        @endphp
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ $allDrives->count() }}</div>
                <div class="text-xs text-text-subtle">Drives</div>
            </div>
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ number_format(auth()->user()->convertDistance($totalDistance), 1) }}</div>
                <div class="text-xs text-text-subtle">{{ auth()->user()->usesKm() ? 'Kilometers' : 'Miles' }}</div>
            </div>
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ $totalHours > 0 ? $totalHours . 'h ' : '' }}{{ $totalMins }}m</div>
                <div class="text-xs text-text-subtle">Driving Time</div>
            </div>
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ $totalEnergy ? number_format($totalEnergy, 1) : '—' }}</div>
                <div class="text-xs text-text-subtle">kWh Used</div>
            </div>
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ $avgEfficiency ? number_format(auth()->user()->convertEfficiency($avgEfficiency), 0) : '—' }}</div>
                <div class="text-xs text-text-subtle">Avg {{ auth()->user()->efficiencyUnit() }}</div>
            </div>
        </div>
    @endif

    @if($drivesByDate->isEmpty())
        <div class="rounded-xl border border-border-default bg-surface p-12 text-center">
            <p class="text-text-subtle">No drives in this period.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($drivesByDate as $date => $drives)
                <div>
                    <h3 class="mb-3 text-sm font-medium text-text-muted">{{ \Carbon\Carbon::parse($date, $userTz)->format('l, M j') }}</h3>
                    <div class="overflow-x-auto rounded-xl border border-border-default">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface text-xs uppercase text-text-muted">
                                <tr>
                                    @if($bulkMode)
                                        <th class="w-10 px-2 py-2 text-center">
                                            <input type="checkbox"
                                                wire:click="{{ count($selectedDrives) > 0 ? 'deselectAll' : 'selectAll' }}"
                                                @checked(count($selectedDrives) > 0)
                                                class="accent-red-500">
                                        </th>
                                    @endif
                                    <th class="px-4 py-2">Time</th>
                                    <th class="px-4 py-2">From → To</th>
                                    <th class="px-4 py-2 text-right">Distance</th>
                                    <th class="px-4 py-2 text-right">Duration</th>
                                    <th class="px-4 py-2 text-right">Energy</th>
                                    <th class="px-4 py-2 text-right">Efficiency</th>
                                    <th class="px-4 py-2 text-right">Battery</th>
                                    <th class="px-4 py-2">Tag</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-default">
                                @foreach($drives as $drive)
                                    <tr class="cursor-pointer bg-page transition hover:bg-surface"
                                        wire:key="drive-{{ $drive->id }}"
                                        onclick="window.location='{{ route('web.drives.show', $drive) }}'">
                                        @if($bulkMode)
                                            <td class="w-10 px-2 py-3 text-center" onclick="event.stopPropagation()">
                                                <input type="checkbox" wire:click="toggleDrive({{ $drive->id }})"
                                                    @checked(in_array($drive->id, $selectedDrives))
                                                    class="accent-red-500">
                                            </td>
                                        @endif
                                        <td class="whitespace-nowrap px-4 py-3 text-text-secondary">
                                            {{ $drive->started_at->tz($userTz)->format('g:ia') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="max-w-xs truncate text-text-secondary">{{ $drive->startPlace?->name ?? $drive->start_address ?? Str::limit(($drive->start_latitude ? number_format($drive->start_latitude, 3) . ', ' . number_format($drive->start_longitude, 3) : '—'), 30) }}</div>
                                            <div class="max-w-xs truncate text-xs text-text-subtle">→ {{ $drive->endPlace?->name ?? $drive->end_address ?? Str::limit(($drive->end_latitude ? number_format($drive->end_latitude, 3) . ', ' . number_format($drive->end_longitude, 3) : '—'), 30) }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-secondary">{{ $drive->distance ? number_format(auth()->user()->convertDistance($drive->distance), 1) . ' ' . auth()->user()->distanceUnit() : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-muted">
                                            @if($drive->started_at && $drive->ended_at)
                                                @php
                                                    $diff = $drive->started_at->diff($drive->ended_at);
                                                @endphp
                                                {{ $diff->h > 0 ? $diff->h . 'h ' : '' }}{{ $diff->i }}m
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-secondary">{{ $drive->energy_used_kwh ? number_format($drive->energy_used_kwh, 1) . ' kWh' : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-secondary">{{ $drive->efficiency ? number_format(auth()->user()->convertEfficiency($drive->efficiency), 0) . ' ' . auth()->user()->efficiencyUnit() : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-muted">{{ $drive->start_battery_level !== null ? number_format($drive->start_battery_level, 0) : '—' }}% → {{ $drive->end_battery_level !== null ? number_format($drive->end_battery_level, 0) : '—' }}%</td>
                                        <td class="px-4 py-3">
                                            @if($drive->tag)
                                                <span class="rounded-full bg-surface-alt px-2 py-0.5 text-xs text-text-secondary">{{ $drive->tag }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @php
                        $dayDistance = $drives->sum('distance');
                        $dayEnergy = $drives->sum('energy_used_kwh');
                        $daySeconds = $drives->sum(fn ($d) => $d->started_at && $d->ended_at ? $d->started_at->diffInSeconds($d->ended_at) : 0);
                        $dayHours = floor($daySeconds / 3600);
                        $dayMins = floor(($daySeconds % 3600) / 60);
                        $dayEfficiency = $dayDistance > 0 ? ($dayEnergy * 1000) / $dayDistance : null;
                    @endphp
                    <div class="mt-3 flex flex-wrap gap-4 rounded-xl border border-border-default bg-surface px-4 py-3 text-sm">
                        <div>
                            <span class="text-text-subtle">Drives:</span>
                            <span class="font-medium text-text-secondary">{{ $drives->count() }}</span>
                        </div>
                        <div>
                            <span class="text-text-subtle">Distance:</span>
                            <span class="font-medium text-text-secondary">{{ number_format(auth()->user()->convertDistance($dayDistance), 1) }} {{ auth()->user()->distanceUnit() }}</span>
                        </div>
                        <div>
                            <span class="text-text-subtle">Time:</span>
                            <span class="font-medium text-text-secondary">{{ $dayHours > 0 ? $dayHours . 'h ' : '' }}{{ $dayMins }}m</span>
                        </div>
                        @if($dayEnergy)
                            <div>
                                <span class="text-text-subtle">Energy:</span>
                                <span class="font-medium text-text-secondary">{{ number_format($dayEnergy, 1) }} kWh</span>
                            </div>
                        @endif
                        @if($dayEfficiency)
                            <div>
                                <span class="text-text-subtle">Avg Efficiency:</span>
                                <span class="font-medium text-text-secondary">{{ number_format(auth()->user()->convertEfficiency($dayEfficiency), 0) }} {{ auth()->user()->efficiencyUnit() }}</span>
                            </div>
                        @endif
                    </div>

                    @if(!empty($mapData[$date]))
                        <div class="mt-3 rounded-xl border border-border-default bg-surface p-3">
                            <div id="day-map-{{ $date }}" class="day-map-container" style="height: 16rem; width: 100%; background: var(--theme-surface); border-radius: 0.5rem;"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Bulk action bar --}}
        @if($bulkMode && count($selectedDrives) > 0)
            <div class="fixed inset-x-0 bottom-0 z-50 border-t border-border-default bg-surface px-6 py-3 shadow-xl lg:left-64">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium text-text-secondary">{{ count($selectedDrives) }} drive{{ count($selectedDrives) !== 1 ? 's' : '' }} selected</span>
                    <input type="text" wire:model="bulkTag" placeholder="Tag name..."
                        class="rounded-lg border border-border-input bg-surface-alt px-3 py-1.5 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                    <button wire:click="applyBulkTag"
                        class="rounded-lg bg-red-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-600"
                        @if(!$bulkTag) disabled style="opacity: 0.5" @endif>
                        Apply Tag
                    </button>
                    <button wire:click="clearBulkTag"
                        class="rounded-lg border border-border-input bg-surface-alt px-3 py-1.5 text-sm text-text-muted hover:text-text-secondary">
                        Clear Tag
                    </button>
                    <button wire:click="deselectAll"
                        class="rounded-lg border border-border-input bg-surface-alt px-3 py-1.5 text-sm text-text-muted hover:text-text-secondary">
                        Deselect All
                    </button>
                </div>
            </div>
        @endif

        @script
        <script>
            window.__dayMapInstances = window.__dayMapInstances || {};
            window.__summaryMapInstance = null;
            window.__summaryMapLayers = null;

            function initSummaryMap(routes) {
                if (!window.L || !routes || routes.length === 0) return;

                var el = document.getElementById('drives-summary-map');
                if (!el) return;

                if (window.__summaryMapInstance) {
                    if (window.__summaryMapLayers) {
                        window.__summaryMapLayers.clearLayers();
                    }
                } else {
                    window.__summaryMapInstance = L.map(el, { attributionControl: false, zoomControl: true });
                    L.tileLayer(window.getMapTileUrl(), { maxZoom: 19 }).addTo(window.__summaryMapInstance);
                    window.registerMap(window.__summaryMapInstance);
                    window.setupMapScrollZoom(window.__summaryMapInstance);
                    window.__summaryMapLayers = L.layerGroup().addTo(window.__summaryMapInstance);
                }

                var allBounds = [];
                routes.forEach(function(route) {
                    var latlngs = route.coords.map(function(c) { return [c[0], c[1]]; });
                    L.polyline(latlngs, { color: route.color, weight: 2.5, opacity: 0.7 }).addTo(window.__summaryMapLayers);
                    allBounds = allBounds.concat(latlngs);
                });

                if (allBounds.length > 0) {
                    window.__summaryMapInstance.fitBounds(L.latLngBounds(allBounds).pad(0.1));
                }
            }

            function initDayMaps(mapData) {
                if (!window.L || !mapData) return;
                Object.keys(window.__dayMapInstances).forEach(function(key) {
                    try { window.__dayMapInstances[key].remove(); } catch(e) {}
                    delete window.__dayMapInstances[key];
                });
                Object.keys(mapData).forEach(function(date) {
                    var routes = mapData[date];
                    if (!routes || routes.length === 0) return;
                    var el = document.getElementById('day-map-' + date);
                    if (!el) return;
                    var map = L.map(el, { attributionControl: false, zoomControl: false, scrollWheelZoom: false, dragging: false, doubleClickZoom: false, touchZoom: false });
                    L.tileLayer(window.getMapTileUrl(), { maxZoom: 19 }).addTo(map);
                    window.registerMap(map);
                    var allBounds = [];
                    routes.forEach(function(route) {
                        var latlngs = route.coords.map(function(c) { return [c[0], c[1]]; });
                        L.polyline(latlngs, { color: route.color, weight: 3, opacity: 0.8 }).addTo(map);
                        L.circleMarker(latlngs[0], { radius: 5, color: route.color, fillColor: route.color, fillOpacity: 1, weight: 0 }).bindTooltip(route.label + ' start').addTo(map);
                        L.circleMarker(latlngs[latlngs.length - 1], { radius: 5, color: route.color, fillColor: route.color, fillOpacity: 1, weight: 0 }).bindTooltip(route.label + ' end').addTo(map);
                        allBounds = allBounds.concat(latlngs);
                    });
                    if (allBounds.length > 0) {
                        map.fitBounds(L.latLngBounds(allBounds).pad(0.1));
                    }
                    window.__dayMapInstances[date] = map;
                });
            }

            $wire.on('summary-map-updated', function(params) {
                setTimeout(function() { initSummaryMap(params.routes); }, 100);
            });

            $wire.on('maps-updated', function(params) {
                setTimeout(function() { initDayMaps(params.mapData); }, 100);
            });
        </script>
        @endscript
    @endif
</div>
