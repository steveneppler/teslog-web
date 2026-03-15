<div class="space-y-6">
    {{-- Tab toggle --}}
    <div class="flex gap-1 rounded-lg border border-border-default bg-surface p-1 w-fit">
        <button wire:click="setTab('processed')"
            class="rounded-md px-4 py-1.5 text-sm font-medium transition {{ $tab === 'processed' ? 'bg-surface-alt text-text-primary' : 'text-text-muted hover:text-text-secondary' }}">
            Processed States
        </button>
        <button wire:click="setTab('raw')"
            class="rounded-md px-4 py-1.5 text-sm font-medium transition {{ $tab === 'raw' ? 'bg-surface-alt text-text-primary' : 'text-text-muted hover:text-text-secondary' }}">
            Raw Telemetry
        </button>
    </div>

    {{-- Filter bar --}}
    <div class="rounded-xl border border-border-default bg-surface p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="block text-xs font-medium text-text-subtle">Vehicle</label>
                <select wire:model.live="vehicleFilter"
                    class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-text-subtle">From</label>
                <input type="datetime-local" wire:model.live="from"
                    class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-text-subtle">To</label>
                <input type="datetime-local" wire:model.live="to"
                    class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
            </div>
            @if($tab === 'processed')
                <div>
                    <label class="block text-xs font-medium text-text-subtle">State</label>
                    <select wire:model.live="stateFilter"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                        <option value="">All States</option>
                        @foreach($states as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-text-subtle">{{ $tab === 'raw' ? 'Field Name' : 'Field Search' }}</label>
                <div class="mt-1 flex gap-2">
                    <input type="text" wire:model.live.debounce.300ms="fieldFilter"
                        placeholder="{{ $tab === 'raw' ? 'e.g. ChargeState' : 'e.g. Charging' }}"
                        class="block w-full rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                    <button wire:click="resetFilters"
                        class="shrink-0 rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-xs font-medium text-text-muted hover:bg-elevated hover:text-text-primary">
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if($tab === 'processed')
        {{-- Processed States --}}
        <div class="text-sm text-text-muted">
            {{ $records->total() }} records found
        </div>

        <div class="overflow-x-auto rounded-xl border border-border-default">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border-default bg-surface text-xs uppercase text-text-subtle">
                    <tr>
                        <th class="whitespace-nowrap px-3 py-3"></th>
                        <th class="whitespace-nowrap px-3 py-3">Timestamp</th>
                        <th class="whitespace-nowrap px-3 py-3">State</th>
                        <th class="whitespace-nowrap px-3 py-3">Charge State</th>
                        <th class="whitespace-nowrap px-3 py-3">Battery</th>
                        <th class="whitespace-nowrap px-3 py-3">Charger kW</th>
                        <th class="whitespace-nowrap px-3 py-3">Charger V</th>
                        <th class="whitespace-nowrap px-3 py-3">Charger A</th>
                        <th class="whitespace-nowrap px-3 py-3">Speed</th>
                        <th class="whitespace-nowrap px-3 py-3">Power</th>
                        <th class="whitespace-nowrap px-3 py-3">Gear</th>
                        <th class="whitespace-nowrap px-3 py-3">Odometer</th>
                        <th class="whitespace-nowrap px-3 py-3">Lat</th>
                        <th class="whitespace-nowrap px-3 py-3">Lng</th>
                        <th class="whitespace-nowrap px-3 py-3">Inside</th>
                        <th class="whitespace-nowrap px-3 py-3">Outside</th>
                        <th class="whitespace-nowrap px-3 py-3">Energy</th>
                        <th class="whitespace-nowrap px-3 py-3">Range</th>
                        <th class="whitespace-nowrap px-3 py-3">Climate</th>
                        <th class="whitespace-nowrap px-3 py-3">Locked</th>
                        <th class="whitespace-nowrap px-3 py-3">Sentry</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @forelse($records as $r)
                        @php
                            $isDriving = $r->state === 'driving';
                            $isCharging = $r->state === 'charging';
                            $isExpanded = $showRawFor === $r->id;
                        @endphp
                        <tr class="bg-surface hover:bg-surface-alt cursor-pointer" wire:click="toggleRawFor({{ $r->id }})">
                            <td class="whitespace-nowrap px-3 py-2 text-text-subtle">
                                <svg class="h-4 w-4 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ $r->timestamp->tz($userTz)->format('M j g:i:sa') }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                    @if($r->state === 'driving') bg-blue-500/20 text-blue-400
                                    @elseif($r->state === 'charging') bg-green-500/20 text-green-400
                                    @elseif($r->state === 'sleeping') bg-purple-500/20 text-purple-400
                                    @else bg-gray-500/20 text-gray-400
                                    @endif">
                                    {{ $r->state ?? '—' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 {{ $isCharging ? 'text-green-400 font-medium' : 'text-text-muted' }}">{{ $r->charge_state ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $r->battery_level !== null ? $r->battery_level . '%' : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 {{ $isCharging && $r->charger_power ? 'text-green-400 font-medium' : 'text-text-muted' }}">{{ $r->charger_power ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 {{ $isCharging && $r->charger_voltage ? 'text-green-400' : 'text-text-muted' }}">{{ $r->charger_voltage ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 {{ $isCharging && $r->charger_current ? 'text-green-400' : 'text-text-muted' }}">{{ $r->charger_current ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 {{ $isDriving && $r->speed ? 'text-blue-400 font-medium' : 'text-text-muted' }}">{{ $r->speed ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 {{ $isDriving && $r->power !== null ? 'text-blue-400' : 'text-text-muted' }}">{{ $r->power ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 {{ $isDriving && $r->gear ? 'text-blue-400' : 'text-text-muted' }}">{{ $r->gear ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-muted">{{ $r->odometer ? number_format($r->odometer, 1) : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-text-muted">{{ $r->latitude ? number_format($r->latitude, 5) : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-text-muted">{{ $r->longitude ? number_format($r->longitude, 5) : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-muted">{{ $r->inside_temp !== null ? $r->inside_temp . '°' : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-muted">{{ $r->outside_temp !== null ? $r->outside_temp . '°' : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-muted">{{ $r->energy_remaining ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-muted">{{ $r->rated_range ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-muted">{{ $r->climate_on ? 'On' : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-muted">{{ $r->locked ? 'Yes' : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-muted">{{ $r->sentry_mode ? 'On' : '—' }}</td>
                        </tr>
                        {{-- Expanded raw telemetry --}}
                        @if($isExpanded)
                            <tr>
                                <td colspan="21" class="bg-surface-alt/50 px-3 py-3">
                                    @if($expandedRaw && $expandedRaw->count() > 0)
                                        <div class="ml-6">
                                            <p class="mb-2 text-xs font-medium text-text-subtle">
                                                Raw telemetry ({{ $expandedRaw->count() }} fields within ±30s of {{ $r->timestamp->tz($userTz)->format('g:i:sa') }})
                                            </p>
                                            <div class="grid gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                                                @foreach($expandedRaw as $raw)
                                                    <div class="flex items-baseline gap-2 font-mono text-xs">
                                                        <span class="text-text-subtle">{{ $raw->timestamp->tz($userTz)->format('g:i:sa') }}</span>
                                                        <span class="text-yellow-400">{{ $raw->field_name }}</span>
                                                        <span class="text-text-primary">{{ $raw->value_string ?? $raw->value_numeric ?? '—' }}</span>
                                                        @if(!$raw->processed)
                                                            <span class="text-red-400 text-[10px]">unprocessed</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <p class="ml-6 text-xs text-text-subtle">No raw telemetry found near this timestamp.</p>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="21" class="px-3 py-8 text-center text-text-muted">No records found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $records->links() }}
        </div>

    @else
        {{-- Raw Telemetry --}}
        <div class="text-sm text-text-muted">
            {{ $rawRecords->total() }} raw telemetry rows found
        </div>

        <div class="overflow-x-auto rounded-xl border border-border-default">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border-default bg-surface text-xs uppercase text-text-subtle">
                    <tr>
                        <th class="whitespace-nowrap px-3 py-3">Timestamp</th>
                        <th class="whitespace-nowrap px-3 py-3">Field Name</th>
                        <th class="whitespace-nowrap px-3 py-3">Numeric</th>
                        <th class="whitespace-nowrap px-3 py-3">String</th>
                        <th class="whitespace-nowrap px-3 py-3">Processed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @forelse($rawRecords as $raw)
                        <tr class="bg-surface hover:bg-surface-alt">
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ $raw->timestamp->tz($userTz)->format('M j g:i:sa') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-yellow-400">{{ $raw->field_name }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ $raw->value_numeric !== null ? $raw->value_numeric : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ $raw->value_string ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                @if($raw->processed)
                                    <span class="text-green-400 text-xs">Yes</span>
                                @else
                                    <span class="text-red-400 text-xs">No</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-text-muted">No raw telemetry found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $rawRecords->links() }}
        </div>
    @endif
</div>
