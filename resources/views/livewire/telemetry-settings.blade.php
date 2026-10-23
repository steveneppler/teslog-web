<section class="rounded-xl border border-border-default bg-surface p-6">
    <h3 class="text-lg font-semibold">Telemetry collection</h3>
    <p class="mt-2 text-sm text-text-secondary">
        Choose which signals this application collects for each vehicle. The catalog includes all {{ $total }}
        public fields in Tesla’s documentation and protocol as of October 23, 2026. Availability depends on vehicle, firmware and permissions.
        Additional fields are kept in raw history; enabling them does not add dashboard charts.
    </p>
    @if($vehicles->isEmpty())
        <p class="mt-4 text-sm text-text-secondary">Connect a vehicle to configure telemetry.</p>
    @else
        <label for="telemetry-vehicle" class="mt-4 block text-sm font-medium">Vehicle</label>
        <select id="telemetry-vehicle" wire:model.live="vehicleId" class="mt-1 w-full rounded-lg border border-border-input bg-surface-alt p-2">
            @foreach($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}">{{ $vehicle->name ?: $vehicle->vin }}</option>
            @endforeach
        </select>
        <p class="mt-3 text-sm text-text-secondary">
            More signals and shorter intervals can increase Tesla API charges and storage use.
            Disabling driving, location or charging signals can leave trips, maps and statistics incomplete.
            Disabling every signal stops this application's telemetry when applied; it does not erase history.
        </p>
        <form wire:submit="save" class="mt-4 space-y-4">
            <label for="telemetry-search" class="block text-sm font-medium">Search signals or categories</label>
            <input id="telemetry-search" type="search" wire:model.live.debounce.300ms="search"
                class="w-full rounded-lg border border-border-input bg-surface-alt p-2" placeholder="Battery, PackVoltage, Media…">
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setAllEnabled(true)" wire:confirm="Select all signals? Applying this selection can significantly increase Tesla API charges and storage use."
                    class="rounded-lg border border-border-input px-3 py-1 text-sm">Select all signals</button>
                <button type="button" wire:click="setAllEnabled(false)" class="rounded-lg border border-border-input px-3 py-1 text-sm">Deselect all signals</button>
            </div>
            <div class="max-h-96 overflow-y-auto space-y-4">
                @forelse($groups as $category => $signals)
                    <fieldset class="rounded-lg border border-border-input p-3">
                        <legend class="px-1 text-sm font-semibold">{{ $category }}</legend>
                        @foreach($signals as $name => $metadata)
                            <div wire:key="signal-{{ $vehicleId }}-{{ $name }}" class="border-b border-border-default py-3 last:border-0">
                                <label class="flex items-center gap-2 break-all text-sm">
                                    <input type="checkbox" wire:model="fields.{{ $name }}.enabled" class="rounded">
                                    {{ $name }}
                                </label>
                                <p class="mt-1 text-xs text-text-subtle">{{ $metadata['type'] }}</p>
                                <div class="mt-2 flex flex-wrap gap-3 text-xs">
                                    <label>Minimum interval (seconds)
                                        <input type="number" min="1" max="86400" step="1" wire:model="fields.{{ $name }}.interval_seconds"
                                            class="ml-1 w-24 rounded border border-border-input bg-surface-alt p-1">
                                    </label>
                                    @if(in_array($metadata['type'], ['real', 'integer']) || $name === 'Location')
                                        <label>Minimum change (optional)
                                            <input type="number" min="0" step="any" wire:model="fields.{{ $name }}.minimum_delta"
                                                class="ml-1 w-24 rounded border border-border-input bg-surface-alt p-1">
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </fieldset>
                @empty
                    <p class="text-sm text-text-secondary">No matching signals.</p>
                @endforelse
            </div>
            @if($errors->any())
                <ul role="alert" class="text-sm text-red-400">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            @if($message)
                <p role="status" class="text-sm text-text-secondary">{{ $message }}</p>
            @endif
            <div class="flex flex-wrap gap-2">
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white">Save preferences</button>
                <button type="button" wire:click="apply" wire:loading.attr="disabled" class="rounded-lg border border-border-input px-4 py-2 text-sm">Apply saved preferences</button>
                <button type="button" wire:click="restoreDefaults" class="rounded-lg border border-border-input px-4 py-2 text-sm">Restore defaults</button>
            </div>
            <p class="text-xs text-text-subtle">Saving updates local preferences only. Apply sends the saved configuration to Tesla. Changes do not recover past data. Location fields require location permission. Minimum change uses Tesla's native field units; Location uses meters.</p>
        </form>
    @endif
</section>
