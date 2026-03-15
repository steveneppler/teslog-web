<div>
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-text-muted">{{ $vehicles->count() }} vehicle(s)</p>
        <button wire:click="$toggle('showAddForm')" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
            Add Vehicle
        </button>
    </div>

    @if($showAddForm)
        <form wire:submit="addVehicle" class="mb-6 rounded-xl border border-border-default bg-surface p-6">
            <h4 class="mb-3 text-sm font-medium text-text-muted">Add a manual vehicle (for importing historical data)</h4>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Name</label>
                    <input type="text" wire:model="newName" placeholder="My Tesla"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                    @error('newName') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary">VIN (optional)</label>
                    <input type="text" wire:model="newVin" placeholder="5YJ..."
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Save</button>
                <button type="button" wire:click="$toggle('showAddForm')" class="rounded-lg border border-border-input px-4 py-2 text-sm text-text-muted hover:text-text-primary">Cancel</button>
            </div>
        </form>
    @endif

    @php
        $liveVehicles = $vehicles->filter(fn ($v) => $v->tesla_vehicle_id);
        $manualVehicles = $vehicles->filter(fn ($v) => !$v->tesla_vehicle_id);
    @endphp

    {{-- Live vehicles --}}
    @if($liveVehicles->isNotEmpty())
        <div class="mb-6">
            <h3 class="mb-3 flex items-center gap-2 text-sm font-medium text-text-muted">
                <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                Live Vehicles
            </h3>
            <div class="space-y-4">
                @foreach($liveVehicles as $vehicle)
                    @include('livewire._vehicle-card', ['vehicle' => $vehicle])
                @endforeach
            </div>
        </div>
    @endif

    {{-- Manual / Archived vehicles --}}
    @if($manualVehicles->isNotEmpty())
        <div>
            <h3 class="mb-3 flex items-center gap-2 text-sm font-medium text-text-muted">
                <span class="inline-block h-2 w-2 rounded-full bg-gray-500"></span>
                Manual / Archived
            </h3>
            <div class="space-y-4">
                @foreach($manualVehicles as $vehicle)
                    @include('livewire._vehicle-card', ['vehicle' => $vehicle])
                @endforeach
            </div>
        </div>
    @endif
</div>
