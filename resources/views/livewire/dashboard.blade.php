<div wire:poll.30s>
    {{-- Health warnings --}}
    @if(count($warnings) > 0)
        <div class="mb-6 space-y-3">
            @foreach($warnings as $warning)
                <div class="flex items-start gap-3 rounded-lg border px-4 py-3
                    {{ ($warning['severity'] ?? '') === 'critical' ? 'border-red-800 bg-red-950/50' : '' }}
                    {{ ($warning['severity'] ?? '') === 'warning' ? 'border-yellow-800 bg-yellow-950/50' : '' }}
                    {{ ($warning['severity'] ?? '') === 'info' ? 'border-blue-800 bg-blue-950/50' : '' }}">
                    @if(($warning['severity'] ?? '') === 'critical')
                        <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-400" />
                    @elseif(($warning['severity'] ?? '') === 'warning')
                        <x-heroicon-o-exclamation-circle class="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-400" />
                    @else
                        <x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-400" />
                    @endif
                    <div class="flex-1">
                        <p class="text-sm font-medium
                            {{ ($warning['severity'] ?? '') === 'critical' ? 'text-red-300' : '' }}
                            {{ ($warning['severity'] ?? '') === 'warning' ? 'text-yellow-300' : '' }}
                            {{ ($warning['severity'] ?? '') === 'info' ? 'text-blue-300' : '' }}">
                            @if(isset($warning['vehicle']))
                                <span class="font-normal opacity-75">{{ $warning['vehicle'] }}:</span>
                            @endif
                            {{ $warning['message'] }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Quick command result --}}
    @if($quickCommandResult)
        <div class="mb-6 flex items-start gap-3 rounded-lg border px-4 py-3
            {{ $quickCommandSuccess ? 'border-green-800 bg-green-950/50' : 'border-red-800 bg-red-950/50' }}">
            @if($quickCommandSuccess)
                <x-heroicon-o-check class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-400" />
                <p class="text-sm font-medium text-green-300">{{ $quickCommandResult }}</p>
            @else
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-400" />
                <p class="text-sm font-medium text-red-300">{{ $quickCommandResult }}</p>
            @endif
        </div>
    @endif

    @if($vehicles->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-border-default bg-surface p-12 text-center">
            <x-heroicon-o-bolt class="mb-4 h-16 w-16 text-text-faint" />
            <h3 class="text-lg font-semibold text-text-secondary">No vehicles linked</h3>
            <p class="mt-1 text-sm text-text-subtle">Add your first Tesla to start logging data.</p>
            <a href="{{ route('setup') }}" class="mt-4 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                Connect Tesla Account
            </a>
        </div>
    @else
        {{-- Vehicle status cards --}}
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach($vehicles as $vehicle)
                <div class="flex flex-col gap-3" wire:key="vehicle-{{ $vehicle->id }}">
                <div class="rounded-xl border border-border-default bg-surface p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">{{ $vehicle->name }}</h3>
                            <div class="flex items-center gap-3 text-xs text-text-subtle">
                                @if($odometer)
                                    <span>{{ number_format(auth()->user()->convertDistance($odometer), 0) }} {{ auth()->user()->distanceUnit() }}</span>
                                @endif
                                @if($softwareVersion)
                                    <span>{{ $softwareVersion }}</span>
                                @endif
                            </div>
                        </div>
                        @if($vehicle->latestState)
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                    @switch($vehicle->latestState->state)
                                        @case('driving') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 @break
                                        @case('charging') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 @break
                                        @case('sleeping') bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400 @break
                                        @case('idle') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300 @break
                                        @default bg-gray-200 text-gray-500 dark:bg-gray-800 dark:text-gray-500
                                    @endswitch">
                                    {{ ucfirst($vehicle->latestState->state) }}
                                </span>
                                @if($vehicle->latestState->climate_on)
                                    <span class="rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 px-2.5 py-0.5 text-xs font-medium dark:text-blue-300">Climate</span>
                                @endif
                                @if($vehicle->latestState->sentry_mode)
                                    <span class="rounded-full bg-red-100 text-red-800 dark:bg-red-900 px-2.5 py-0.5 text-xs font-medium dark:text-red-300">Sentry</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($vehicle->latestState)
                        <div class="flex items-baseline gap-6">
                            <div>
                                <p class="text-xs text-text-subtle">Battery</p>
                                <p class="text-2xl font-bold">{{ $vehicle->latestState->battery_level !== null ? number_format($vehicle->latestState->battery_level, 0) : '—' }}%</p>
                            </div>
                            <div>
                                <p class="text-xs text-text-subtle">Range</p>
                                <p class="text-2xl font-bold">{{ $vehicle->latestState->rated_range ? number_format(auth()->user()->convertDistance($vehicle->latestState->rated_range), 0) : '—' }} {{ auth()->user()->distanceUnit() }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-text-subtle">Temp</p>
                                <p class="text-2xl font-bold">
                                    {{ auth()->user()->formatTemp($vehicle->latestState->inside_temp) ?? '—' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs">
                                @if($vehicle->latestState->locked)
                                    <x-heroicon-o-lock-closed class="h-3.5 w-3.5 text-green-400" />
                                    <span class="text-green-400">Locked</span>
                                @else
                                    <x-heroicon-o-lock-open class="h-3.5 w-3.5 text-orange-400" />
                                    <span class="text-orange-400">Unlocked</span>
                                @endif
                            </div>
                            <p class="text-xs text-text-faint">Updated {{ $vehicle->latestState->timestamp->diffForHumans() }}</p>
                        </div>
                    @else
                        <p class="mt-2 text-sm text-text-subtle">No data received yet.</p>
                    @endif
                </div>

                {{-- Quick action buttons --}}
                @if($vehicle->latestState && $vehicle->tesla_vehicle_id)
                    <div class="flex gap-3">
                        {{-- Lock/Unlock --}}
                        <button wire:click="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->locked ? 'unlock' : 'lock' }}')"
                            wire:loading.attr="disabled"
                            class="group flex flex-1 flex-col items-center justify-center gap-2 rounded-xl bg-amber-950/40 py-4 transition hover:bg-amber-950/60 disabled:opacity-50">
                            <span wire:loading wire:target="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->locked ? 'unlock' : 'lock' }}')">
                                <svg class="h-7 w-7 animate-spin text-orange-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </span>
                            <span wire:loading.remove wire:target="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->locked ? 'unlock' : 'lock' }}')">
                                @if($vehicle->latestState->locked)
                                    <x-heroicon-s-lock-closed class="h-7 w-7 text-orange-400" />
                                @else
                                    <x-heroicon-s-lock-open class="h-7 w-7 text-orange-400" />
                                @endif
                            </span>
                            <span class="text-xs font-medium text-orange-400">{{ $vehicle->latestState->locked ? 'Unlock' : 'Lock' }}</span>
                        </button>

                        {{-- Climate --}}
                        <button wire:click="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->climate_on ? 'climate_off' : 'climate_on' }}')"
                            wire:loading.attr="disabled"
                            class="group flex flex-1 flex-col items-center justify-center gap-2 rounded-xl bg-blue-950/40 py-4 transition hover:bg-blue-950/60 disabled:opacity-50">
                            <span wire:loading wire:target="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->climate_on ? 'climate_off' : 'climate_on' }}')">
                                <svg class="h-7 w-7 animate-spin text-blue-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </span>
                            <span wire:loading.remove wire:target="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->climate_on ? 'climate_off' : 'climate_on' }}')">
                                <x-heroicon-o-sun class="h-7 w-7 text-blue-400" />
                            </span>
                            <span class="text-xs font-medium text-blue-400">{{ $vehicle->latestState->climate_on ? 'Climate Off' : 'Climate On' }}</span>
                        </button>

                        {{-- Charge --}}
                        <button wire:click="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->charge_state === 'Charging' ? 'charge_stop' : 'charge_start' }}')"
                            wire:loading.attr="disabled"
                            class="group flex flex-1 flex-col items-center justify-center gap-2 rounded-xl bg-green-950/40 py-4 transition hover:bg-green-950/60 disabled:opacity-50">
                            <span wire:loading wire:target="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->charge_state === 'Charging' ? 'charge_stop' : 'charge_start' }}')">
                                <svg class="h-7 w-7 animate-spin text-green-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </span>
                            <span wire:loading.remove wire:target="quickCommand({{ $vehicle->id }}, '{{ $vehicle->latestState->charge_state === 'Charging' ? 'charge_stop' : 'charge_start' }}')">
                                <x-heroicon-s-bolt class="h-7 w-7 text-green-400" />
                            </span>
                            <span class="text-xs font-medium text-green-400">{{ $vehicle->latestState->charge_state === 'Charging' ? 'Stop Charge' : 'Start Charge' }}</span>
                        </button>
                    </div>
                @endif

                {{-- Links row --}}
                @if($vehicle->latestState)
                    <div class="flex items-center justify-end gap-3">
                        @if($vehicle->tesla_vehicle_id)
                            <a href="{{ route('web.vehicle-commands', $vehicle) }}" class="text-xs text-text-subtle hover:text-text-secondary">All Commands</a>
                        @endif
                        <a href="{{ route('web.vehicle-health', $vehicle) }}" class="flex items-center gap-1 text-xs text-text-subtle hover:text-text-secondary">
                            @if(isset($vehicleDegradation[$vehicle->id]))
                                <span class="{{ $vehicleDegradation[$vehicle->id]->degradation_pct > 10 ? 'text-yellow-500' : 'text-green-400' }}">
                                    {{ number_format($vehicleDegradation[$vehicle->id]->degradation_pct, 1) }}% deg
                                </span>
                                <span>&middot;</span>
                            @endif
                            <span>Health</span>
                            <x-heroicon-m-chevron-right class="h-3 w-3" />
                        </a>
                    </div>
                @endif
                </div>{{-- /vehicle wrapper --}}
            @endforeach
        </div>

        {{-- Weekly stats --}}
        <div class="mt-6 rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-text-muted">Last 7 Days</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div>
                    <p class="text-2xl font-bold">{{ $weekStats['drives'] }}</p>
                    <p class="text-xs text-text-subtle">Drives</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ number_format(auth()->user()->convertDistance($weekStats['distance']), 0) }}</p>
                    <p class="text-xs text-text-subtle">{{ auth()->user()->usesKm() ? 'Kilometers' : 'Miles' }}</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ number_format($weekStats['energy_used'], 1) }}</p>
                    <p class="text-xs text-text-subtle">kWh Used</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $weekStats['efficiency'] ? number_format(auth()->user()->convertEfficiency($weekStats['efficiency']), 0) : '—' }}</p>
                    <p class="text-xs text-text-subtle">{{ auth()->user()->efficiencyUnit() }} Avg</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $weekStats['charges'] }}</p>
                    <p class="text-xs text-text-subtle">Charges</p>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ number_format($weekStats['energy_added'], 1) }}</p>
                    <p class="text-xs text-text-subtle">kWh Added</p>
                </div>
            </div>
        </div>

        {{-- Battery sparkline & activity heatmap --}}
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            {{-- Battery sparkline --}}
            <div class="rounded-xl border border-border-default bg-surface p-6">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-text-muted">Battery Level</h3>
                @if(count($sparklinePoints) > 1)
                    <div wire:ignore class="h-32">
                        <canvas id="battery-sparkline"></canvas>
                    </div>
                @else
                    <p class="text-sm text-text-subtle">Not enough data yet.</p>
                @endif
            </div>

            {{-- Daily Activity (stacked) --}}
            <div class="rounded-xl border border-border-default bg-surface p-6">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-text-muted">Daily Activity</h3>
                <div class="mb-2 flex items-center gap-4 text-xs text-text-subtle">
                    <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded" style="background-color: #22c55e"></span> Driven</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded" style="background-color: #3b82f6"></span> Charged</span>
                </div>
                @php
                    $maxTotal = max(array_map(fn ($d) => $d['driven'] + $d['charged'], $activityDays)) ?: 1;
                @endphp
                <div class="flex items-end gap-3" style="height: 120px">
                    @foreach($activityDays as $day)
                        @php
                            $total = $day['driven'] + $day['charged'];
                            $drivenHeight = $total > 0 ? max(4, round(($day['driven'] / $maxTotal) * 90)) : 0;
                            $chargedHeight = $total > 0 ? max(4, round(($day['charged'] / $maxTotal) * 90)) : 0;
                            if ($day['driven'] == 0) $drivenHeight = 0;
                            if ($day['charged'] == 0) $chargedHeight = 0;
                            if ($total == 0) { $drivenHeight = 3; }
                        @endphp
                        <div class="flex h-full flex-1 flex-col items-center justify-end">
                            <span class="mb-1 text-xs font-medium tabular-nums {{ $total > 0 ? 'text-text-secondary' : 'text-text-faint' }}">
                                {{ $total > 0 ? number_format($total, 0) : '—' }}
                            </span>
                            <div class="flex w-full flex-col items-stretch justify-end">
                                @if($day['charged'] > 0)
                                    <div class="w-full rounded-t" style="height: {{ $chargedHeight }}px; background-color: #3b82f6"
                                         title="{{ $day['label'] }}: {{ number_format(auth()->user()->convertDistance($day['charged']), 1) }} {{ auth()->user()->distanceUnit() }} charged"></div>
                                @endif
                                @if($day['driven'] > 0)
                                    <div class="w-full {{ $day['charged'] > 0 ? '' : 'rounded-t' }} rounded-b" style="height: {{ $drivenHeight }}px; background-color: #22c55e"
                                         title="{{ $day['label'] }}: {{ number_format(auth()->user()->convertDistance($day['driven']), 1) }} {{ auth()->user()->distanceUnit() }} driven"></div>
                                @elseif($total == 0)
                                    <div class="w-full rounded bg-surface-alt" style="height: 3px"></div>
                                @endif
                            </div>
                            <span class="mt-1.5 text-xs text-text-muted">
                                {{ $day['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Recent activity --}}
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            {{-- Recent Drives --}}
            <div class="rounded-xl border border-border-default bg-surface p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-text-muted">Recent Drives</h3>
                    <a href="{{ route('web.drives') }}" class="text-xs text-text-subtle hover:text-text-secondary">View all</a>
                </div>
                @if($recentDrives->isEmpty())
                    <p class="text-sm text-text-subtle">No drives yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach($recentDrives as $drive)
                            <a href="{{ route('web.drives.show', $drive) }}" class="block rounded-lg bg-surface-alt/50 px-4 py-3 transition hover:bg-surface-alt">
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            {{ $drive->startPlace?->name ?? Str::limit($drive->start_address, 25) ?? 'Unknown' }}
                                            <span class="text-text-faint">&rarr;</span>
                                            {{ $drive->endPlace?->name ?? Str::limit($drive->end_address, 25) ?? 'Unknown' }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-text-subtle">
                                            {{ $drive->started_at->tz($userTz)->format('M j, g:ia') }}
                                            &middot; {{ $drive->started_at->diffForHumans($drive->ended_at, true) }}
                                        </p>
                                    </div>
                                    <div class="ml-4 flex-shrink-0 text-right">
                                        <p class="text-sm font-medium">{{ number_format(auth()->user()->convertDistance($drive->distance), 1) }} {{ auth()->user()->distanceUnit() }}</p>
                                        @if($drive->efficiency)
                                            <p class="text-xs text-text-subtle">{{ number_format(auth()->user()->convertEfficiency($drive->efficiency), 0) }} {{ auth()->user()->efficiencyUnit() }}</p>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent Charges --}}
            <div class="rounded-xl border border-border-default bg-surface p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-text-muted">Recent Charges</h3>
                    <a href="{{ route('web.charges') }}" class="text-xs text-text-subtle hover:text-text-secondary">View all</a>
                </div>
                @if($recentCharges->isEmpty())
                    <p class="text-sm text-text-subtle">No charges yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach($recentCharges as $charge)
                            <a href="{{ route('web.charges.show', $charge) }}" class="block rounded-lg bg-surface-alt/50 px-4 py-3 transition hover:bg-surface-alt">
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            {{ $charge->place?->name ?? Str::limit($charge->address, 35) ?? 'Unknown' }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-text-subtle">
                                            {{ $charge->started_at->tz($userTz)->format('M j, g:ia') }}
                                            &middot; {{ $charge->started_at->diffForHumans($charge->ended_at, true) }}
                                        </p>
                                    </div>
                                    <div class="ml-4 flex-shrink-0 text-right">
                                        <p class="text-sm font-medium">
                                            {{ $charge->start_battery_level !== null && $charge->end_battery_level !== null
                                                ? number_format($charge->start_battery_level, 0) . '% → ' . number_format($charge->end_battery_level, 0) . '%'
                                                : '—' }}
                                        </p>
                                        <p class="text-xs text-text-subtle">
                                            {{ $charge->energy_added_kwh ? number_format($charge->energy_added_kwh, 1) . ' kWh' : '—' }}
                                        </p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @script
        <script>
            if (typeof Echo !== 'undefined') {
                @foreach($vehicles as $vehicle)
                    Echo.channel('vehicle.{{ $vehicle->id }}')
                        .listen('VehicleStateChanged', (e) => {
                            $wire.$refresh();
                        });
                @endforeach
            }

            $wire.on('sparkline-data', ({ points }) => {
                const canvas = document.getElementById('battery-sparkline');
                if (!canvas || points.length < 2) return;

                if (window.__dashSparkline) {
                    window.__dashSparkline.destroy();
                }

                const ctx = canvas.getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
                gradient.addColorStop(0, 'rgba(34, 197, 94, 0.2)');
                gradient.addColorStop(1, 'rgba(34, 197, 94, 0)');

                const themeMuted = getComputedStyle(document.documentElement).getPropertyValue('--theme-text-muted').trim();
                const themeSurfaceAlt = getComputedStyle(document.documentElement).getPropertyValue('--theme-surface-alt').trim();

                window.__dashSparkline = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: points.map(p => p.ts),
                        datasets: [{
                            data: points.map(p => p.bat),
                            borderColor: '#22c55e',
                            borderWidth: 1.5,
                            backgroundColor: gradient,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHitRadius: 8,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: (items) => {
                                        const d = new Date(items[0].parsed.x);
                                        return d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                                    },
                                    label: (item) => item.parsed.y.toFixed(0) + '%',
                                },
                            },
                        },
                        scales: {
                            x: {
                                type: 'time',
                                display: true,
                                grid: { display: false },
                                ticks: {
                                    color: themeMuted,
                                    font: { size: 10 },
                                    maxTicksLimit: 7,
                                    callback: function(value) {
                                        return new Date(value).toLocaleDateString([], { weekday: 'short' });
                                    },
                                },
                                border: { display: false },
                            },
                            y: {
                                min: 0,
                                max: 100,
                                display: true,
                                grid: { color: themeSurfaceAlt },
                                ticks: {
                                    color: themeMuted,
                                    font: { size: 10 },
                                    stepSize: 25,
                                    callback: (v) => v + '%',
                                },
                                border: { display: false },
                            },
                        },
                    },
                });
            });
        </script>
        @endscript
    @endif
</div>
