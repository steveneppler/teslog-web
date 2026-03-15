<div class="rounded-xl border border-border-default bg-surface p-6" wire:key="v-{{ $vehicle->id }}">
    {{-- Delete confirmation --}}
    @if($confirmingDelete === $vehicle->id)
        <div class="flex items-center justify-between rounded-lg border border-red-800 bg-red-950 p-4">
            <div>
                <p class="text-sm font-medium text-red-300">Delete {{ $vehicle->name }}?</p>
                <p class="mt-1 text-xs text-red-400">This will permanently remove this vehicle and all its data (drives, charges, states).</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="deleteVehicle({{ $vehicle->id }})"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Delete
                </button>
                <button wire:click="cancelDelete"
                    class="rounded-lg border border-border-input px-3 py-2 text-sm text-text-muted hover:text-text-primary">
                    Cancel
                </button>
            </div>
        </div>
    @elseif($editingVehicleId !== $vehicle->id)
        {{-- View mode --}}
        <div>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-semibold">{{ $vehicle->name }}</h3>
                    @if($vehicle->tesla_vehicle_id)
                        <span class="rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 px-2 py-0.5 text-xs">Live</span>
                    @else
                        <span class="rounded-full bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400 px-2 py-0.5 text-xs">Manual</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($vehicle->tesla_vehicle_id)
                        <span class="text-xs {{ $vehicle->is_active ? 'text-green-400' : 'text-text-subtle' }}">
                            {{ $vehicle->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <button wire:click="toggleActive({{ $vehicle->id }})"
                            class="rounded border border-border-input px-3 py-1 text-xs text-text-muted hover:text-text-primary">
                            {{ $vehicle->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    @endif
                    <button wire:click="toggleDashboard({{ $vehicle->id }})"
                        class="rounded border border-border-input px-3 py-1 text-xs {{ $vehicle->show_on_dashboard ? 'text-green-400' : 'text-text-subtle' }} hover:text-text-primary"
                        title="{{ $vehicle->show_on_dashboard ? 'Shown on dashboard' : 'Hidden from dashboard' }}">
                        {{ $vehicle->show_on_dashboard ? 'On Dashboard' : 'Off Dashboard' }}
                    </button>
                    <button wire:click="editVehicle({{ $vehicle->id }})"
                        class="rounded border border-border-input px-3 py-1 text-xs text-text-muted hover:text-text-primary">
                        Edit
                    </button>
                    <button wire:click="confirmDelete({{ $vehicle->id }})"
                        class="rounded border border-border-input px-3 py-1 text-xs text-text-muted hover:text-red-400">
                        Delete
                    </button>
                </div>
            </div>
            <p class="mt-1 text-sm text-text-subtle">
                {{ $vehicle->vin ?? 'No VIN' }}
            </p>
            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                @if($vehicle->model)
                    <span class="rounded-full bg-surface-alt px-2.5 py-0.5 text-text-secondary">
                        {{ $vehicle->model }}{{ $vehicle->trim ? ' ' . $vehicle->trim : '' }}
                    </span>
                @endif
                @if($vehicle->battery_capacity_kwh)
                    <span class="rounded-full bg-surface-alt px-2.5 py-0.5 text-text-secondary">
                        {{ $vehicle->battery_capacity_kwh }} kWh
                    </span>
                @endif
                @if($vehicle->firmware_version)
                    <span class="rounded-full bg-surface-alt px-2.5 py-0.5 text-text-muted">
                        {{ $vehicle->firmware_version }}
                    </span>
                @endif
                @if(!$vehicle->model || !$vehicle->battery_capacity_kwh)
                    <span class="text-xs text-yellow-500">Set model & battery capacity for energy calculations</span>
                @endif
            </div>
            @if($vehicle->latestState)
                <p class="mt-2 text-xs text-text-faint">Last seen {{ $vehicle->latestState->timestamp->diffForHumans() }}</p>
            @elseif(!$vehicle->tesla_vehicle_id)
                <p class="mt-2 text-xs text-text-faint">Not connected to Tesla — for historical data only</p>
            @endif
            <div class="mt-3 flex gap-3">
                <a href="{{ route('web.vehicle-health', $vehicle) }}" class="text-xs text-text-subtle hover:text-text-secondary">Battery Health</a>
                <a href="{{ route('web.vehicle-firmware', $vehicle) }}" class="text-xs text-text-subtle hover:text-text-secondary">Firmware History</a>
                @if($vehicle->tesla_vehicle_id)
                    <a href="{{ route('web.vehicle-commands', $vehicle) }}" class="text-xs text-text-subtle hover:text-text-secondary">Commands</a>
                @endif
            </div>
        </div>
    @else
        {{-- Edit mode --}}
        <form wire:submit="saveVehicle" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Name</label>
                    <input type="text" wire:model="editName"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                    @error('editName') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">VIN</label>
                    <input type="text" value="{{ $vehicle->vin }}" disabled
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt/50 px-4 py-2 text-text-subtle">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Model</label>
                    <select wire:model.live="editModel"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                        @foreach($modelOptions as $key => $option)
                            <option value="{{ $key }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Trim</label>
                    <select wire:model.live="editTrim"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none"
                        {{ !$editModel ? 'disabled' : '' }}>
                        <option value="">Select trim...</option>
                        @if($editModel && isset($modelOptions[$editModel]['trims']))
                            @foreach($modelOptions[$editModel]['trims'] as $trimName => $capacity)
                                <option value="{{ $trimName }}">{{ $trimName }} ({{ $capacity }} kWh)</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Battery Capacity (kWh)</label>
                    <input type="number" wire:model="editBatteryCapacity" step="0.1" min="1" max="300"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                    @error('editBatteryCapacity') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                    <p class="mt-1 text-xs text-text-subtle">Auto-filled from model/trim. Override if needed.</p>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Save
                </button>
                <button type="button" wire:click="cancelEdit"
                    class="rounded-lg border border-border-input px-4 py-2 text-sm text-text-muted hover:text-text-primary">
                    Cancel
                </button>
            </div>
        </form>
    @endif
</div>
