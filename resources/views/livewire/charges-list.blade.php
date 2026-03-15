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
            <select wire:model.live="typeFilter"
                class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                <option value="">All types</option>
                <option value="ac">AC</option>
                <option value="dc">DC</option>
                <option value="supercharger">Supercharger</option>
            </select>
        </div>
    </div>

    {{-- Charging locations map --}}
    <div class="mb-6 rounded-xl border border-border-default bg-surface p-3" style="{{ count($mapMarkers) === 0 ? 'display:none' : '' }}">
        <div wire:ignore id="charges-map" style="height: 18rem; width: 100%; background: var(--theme-surface); border-radius: 0.5rem;"></div>
    </div>

    {{-- Period summary --}}
    @if($allCharges->isNotEmpty())
        @php
            $totalEnergy = $allCharges->sum('energy_added_kwh');
            $totalCost = $allCharges->sum('cost');
            $totalSeconds = $allCharges->sum(fn ($c) => $c->started_at && $c->ended_at ? $c->started_at->diffInSeconds($c->ended_at) : 0);
            $totalHours = floor($totalSeconds / 3600);
            $totalMins = floor(($totalSeconds % 3600) / 60);
        @endphp
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ $allCharges->count() }}</div>
                <div class="text-xs text-text-subtle">Charges</div>
            </div>
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ number_format($totalEnergy, 1) }}</div>
                <div class="text-xs text-text-subtle">kWh Added</div>
            </div>
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ $totalHours > 0 ? $totalHours . 'h ' : '' }}{{ $totalMins }}m</div>
                <div class="text-xs text-text-subtle">Charging Time</div>
            </div>
            <div class="rounded-xl border border-border-default bg-surface p-4 text-center">
                <div class="text-2xl font-bold text-text-primary">{{ $totalCost ? '$' . number_format($totalCost, 2) : '—' }}</div>
                <div class="text-xs text-text-subtle">Total Cost</div>
            </div>
        </div>
    @endif

    @if($chargesByDate->isEmpty())
        <div class="rounded-xl border border-border-default bg-surface p-12 text-center">
            <p class="text-text-subtle">No charges in this period.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($chargesByDate as $date => $charges)
                <div>
                    <h3 class="mb-3 text-sm font-medium text-text-muted">{{ \Carbon\Carbon::parse($date, $userTz)->format('l, M j') }}</h3>
                    <div class="overflow-hidden rounded-xl border border-border-default">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface text-xs uppercase text-text-muted">
                                <tr>
                                    <th class="px-4 py-2">Time</th>
                                    <th class="px-4 py-2">Location</th>
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2 text-right">Duration</th>
                                    <th class="px-4 py-2 text-right">Energy</th>
                                    <th class="px-4 py-2 text-right">Battery</th>
                                    <th class="px-4 py-2 text-right">Max Power</th>
                                    <th class="px-4 py-2 text-right">Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-default">
                                @foreach($charges as $charge)
                                    <tr class="cursor-pointer bg-page transition hover:bg-surface"
                                        wire:key="charge-{{ $charge->id }}"
                                        onclick="window.location='{{ route('web.charges.show', $charge) }}'">
                                        <td class="whitespace-nowrap px-4 py-3 text-text-secondary">
                                            {{ $charge->started_at->tz($userTz)->format('g:ia') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="max-w-xs truncate text-text-secondary">{{ $charge->place?->name ?? $charge->address ?? ($charge->latitude ? number_format($charge->latitude, 3) . ', ' . number_format($charge->longitude, 3) : '—') }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                                @if($charge->charge_type === \App\Enums\ChargeType::Supercharger) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                                                @elseif($charge->charge_type === \App\Enums\ChargeType::Dc) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                                @else bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                                @endif">
                                                {{ $charge->charge_type->label() }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-muted">
                                            @if($charge->started_at && $charge->ended_at)
                                                @php
                                                    $diff = $charge->started_at->diff($charge->ended_at);
                                                @endphp
                                                {{ $diff->h > 0 ? $diff->h . 'h ' : '' }}{{ $diff->i }}m
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-secondary">{{ $charge->energy_added_kwh ? number_format($charge->energy_added_kwh, 1) . ' kWh' : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-secondary">{{ $charge->start_battery_level !== null ? number_format($charge->start_battery_level, 0) : '—' }}% → {{ $charge->end_battery_level !== null ? number_format($charge->end_battery_level, 0) : '—' }}%</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-secondary">{{ $charge->max_charger_power ? number_format($charge->max_charger_power, 0) . ' kW' : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-text-secondary">{{ $charge->cost ? '$' . number_format($charge->cost, 2) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @php
                        $dayEnergy = $charges->sum('energy_added_kwh');
                        $dayCost = $charges->sum('cost');
                        $daySeconds = $charges->sum(fn ($c) => $c->started_at && $c->ended_at ? $c->started_at->diffInSeconds($c->ended_at) : 0);
                        $dayHours = floor($daySeconds / 3600);
                        $dayMins = floor(($daySeconds % 3600) / 60);
                    @endphp
                    <div class="mt-3 flex flex-wrap gap-4 rounded-xl border border-border-default bg-surface px-4 py-3 text-sm">
                        <div>
                            <span class="text-text-subtle">Charges:</span>
                            <span class="font-medium text-text-secondary">{{ $charges->count() }}</span>
                        </div>
                        <div>
                            <span class="text-text-subtle">Energy:</span>
                            <span class="font-medium text-text-secondary">{{ number_format($dayEnergy, 1) }} kWh</span>
                        </div>
                        <div>
                            <span class="text-text-subtle">Time:</span>
                            <span class="font-medium text-text-secondary">{{ $dayHours > 0 ? $dayHours . 'h ' : '' }}{{ $dayMins }}m</span>
                        </div>
                        @if($dayCost)
                            <div>
                                <span class="text-text-subtle">Cost:</span>
                                <span class="font-medium text-text-secondary">${{ number_format($dayCost, 2) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @script
    <script>
        window.__chargesMap = null;
        window.__chargesMarkers = null;

        function initChargesMap(markers) {
            if (!window.L || !markers || markers.length === 0) return;

            var el = document.getElementById('charges-map');
            if (!el) return;

            if (window.__chargesMap) {
                if (window.__chargesMarkers) {
                    window.__chargesMarkers.clearLayers();
                }
            } else {
                window.__chargesMap = L.map(el, { attributionControl: false, zoomControl: true });
                L.tileLayer(window.getMapTileUrl(), { maxZoom: 19 }).addTo(window.__chargesMap);
                window.registerMap(window.__chargesMap);
                window.__chargesMarkers = L.layerGroup().addTo(window.__chargesMap);
            }

            var bounds = [];
            markers.forEach(function(m) {
                var color = m.type === 'supercharger' ? '#ef4444' : (m.type === 'dc' ? '#3b82f6' : '#22c55e');
                var radius = Math.min(12, Math.max(6, m.count * 2));
                var marker = L.circleMarker([m.lat, m.lng], {
                    radius: radius,
                    color: color,
                    fillColor: color,
                    fillOpacity: 0.7,
                    weight: 2,
                });
                var tooltip = '<strong>' + m.label + '</strong><br>' +
                    m.count + (m.count === 1 ? ' charge' : ' charges') + ' · ' +
                    m.energy + ' kWh';
                marker.bindTooltip(tooltip);
                window.__chargesMarkers.addLayer(marker);
                bounds.push([m.lat, m.lng]);
            });

            if (bounds.length > 0) {
                window.__chargesMap.fitBounds(L.latLngBounds(bounds).pad(0.15));
            }
        }

        $wire.on('charge-markers', function(params) {
            setTimeout(function() { initChargesMap(params.markers); }, 100);
        });
    </script>
    @endscript
</div>
