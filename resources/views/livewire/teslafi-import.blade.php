<div class="max-w-2xl space-y-6"
    @if($processing) wire:poll.2s="checkProcessing" @elseif($geocoding) wire:poll.5s="checkGeocoding" @endif>

    {{-- Processing banner --}}
    @if($processing)
        <div class="rounded-xl border border-blue-800 bg-blue-950/50 p-6">
            <div class="flex items-center gap-3">
                <svg class="h-6 w-6 flex-shrink-0 animate-spin text-blue-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="font-semibold text-blue-300">Processing imported data...</h3>
                    <p class="mt-1 text-sm font-medium text-blue-400">{{ $processingStep }}</p>
                    @if($totalImported > 0)
                        <p class="mt-1 text-xs text-blue-500">{{ number_format($totalImported) }} records imported{{ $totalSkipped > 0 ? ', ' . number_format($totalSkipped) . ' skipped' : '' }}</p>
                    @endif
                    <p class="mt-2 text-xs text-blue-600">This runs in the background. You can navigate away and come back.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Geocoding progress banner --}}
    @if($geocoding && !$processing)
        <div class="rounded-xl border border-indigo-800 bg-indigo-950/50 p-6">
            <div class="flex items-center gap-3">
                <svg class="h-6 w-6 flex-shrink-0 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="font-semibold text-indigo-300">Geocoding addresses...</h3>
                    <p class="mt-1 text-sm font-medium text-indigo-400">{{ number_format($geocodeDone) }} / {{ number_format($geocodeTotal) }} locations</p>
                    @if($geocodeTotal > 0)
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-indigo-900">
                            <div class="h-full rounded-full bg-indigo-500 transition-all duration-500" style="width: {{ min(100, round(($geocodeDone / $geocodeTotal) * 100)) }}%"></div>
                        </div>
                    @endif
                    <p class="mt-2 text-xs text-indigo-600">Reverse geocoding via OpenStreetMap (~1 per second). This runs in the background.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Results banner --}}
    @if($hasResults)
        <div class="rounded-xl border border-border-input bg-surface p-6">
            <h3 class="mb-4 text-lg font-semibold">Import Results</h3>

            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-lg bg-surface-alt p-4 text-center">
                    <div class="text-2xl font-bold text-green-400">{{ number_format($totalImported) }}</div>
                    <div class="mt-1 text-sm text-text-muted">Imported</div>
                </div>
                <div class="rounded-lg bg-surface-alt p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-400">{{ number_format($totalSkipped) }}</div>
                    <div class="mt-1 text-sm text-text-muted">Skipped</div>
                </div>
                <div class="rounded-lg bg-surface-alt p-4 text-center">
                    <div class="text-2xl font-bold {{ count($importErrors) > 0 ? 'text-red-400' : 'text-text-muted' }}">{{ number_format(count($importErrors)) }}</div>
                    <div class="mt-1 text-sm text-text-muted">Errors</div>
                </div>
            </div>

            @if(count($importErrors) > 0)
                <div class="mt-4 max-h-48 overflow-y-auto rounded-lg border border-red-900 bg-red-950/50 p-4">
                    <p class="mb-2 text-sm font-medium text-red-400">Errors:</p>
                    <ul class="space-y-1 text-sm text-red-300">
                        @foreach(array_slice($importErrors, 0, 50) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                        @if(count($importErrors) > 50)
                            <li class="text-red-500">... and {{ count($importErrors) - 50 }} more errors.</li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>
    @endif

    {{-- Import form --}}
    <form wire:submit="import" class="space-y-6">

        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-lg font-semibold">TeslaFi CSV Import</h3>

            <div class="space-y-4">
                {{-- Vehicle --}}
                <div>
                    <label class="block text-sm font-medium text-text-secondary">Vehicle</label>
                    <select wire:model="vehicleId"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                        <option value="">Select a vehicle...</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->name ?: $vehicle->vin }} ({{ $vehicle->model }})</option>
                        @endforeach
                    </select>
                    @error('vehicleId') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Timezone --}}
                <div>
                    <label class="block text-sm font-medium text-text-secondary">TeslaFi Timezone</label>
                    <select wire:model="timezone"
                        class="mt-1 block w-full rounded-lg border border-border-input bg-surface-alt px-4 py-2 text-text-primary focus:border-red-500 focus:outline-none">
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-text-subtle">The timezone your TeslaFi account was set to when logging data.</p>
                </div>

                {{-- File upload --}}
                <div>
                    <label class="block text-sm font-medium text-text-secondary">CSV Files</label>
                    <div class="mt-1">
                        <label class="flex cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-border-input bg-surface-alt/50 px-6 py-8 transition hover:border-border-strong hover:bg-surface-alt">
                            <div class="text-center">
                                <svg class="mx-auto h-10 w-10 text-text-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="mt-2 text-sm text-text-muted">
                                    <span class="text-red-400 hover:text-red-300">Choose files</span> or drag and drop
                                </p>
                                <p class="mt-1 text-xs text-text-subtle">CSV files up to 100MB each</p>
                            </div>
                            <input type="file" wire:model="files" multiple accept=".csv,.txt" class="hidden">
                        </label>
                    </div>
                    @error('files') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    @error('files.*') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror

                    {{-- Upload progress --}}
                    <div wire:loading wire:target="files" class="mt-3">
                        <div class="flex items-center gap-2 text-sm text-text-muted">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Uploading...
                        </div>
                    </div>

                    {{-- Upload error --}}
                    @if($uploadError)
                        <div class="mt-3 rounded-lg border border-red-800 bg-red-950/50 p-3 text-sm text-red-400">
                            {{ $uploadError }}
                        </div>
                    @endif

                    {{-- Show selected files --}}
                    @if(count($files) > 0)
                        <div class="mt-3 space-y-1">
                            @foreach($files as $file)
                                <div class="flex items-center gap-2 text-sm text-text-muted">
                                    <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ $file->getClientOriginalName() }}
                                    <span class="text-text-faint">({{ number_format($file->getSize() / 1024, 0) }} KB)</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                wire:loading.attr="disabled"
                wire:target="import"
                @if($processing) disabled @endif
                class="rounded-lg bg-red-600 px-6 py-2 font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="import">Import</span>
                <span wire:loading wire:target="import" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Importing...
                </span>
            </button>

        </div>
    </form>
</div>
