<div>
    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('web.vehicles') }}" class="text-sm text-text-subtle hover:text-text-secondary">&larr; Back to Vehicles</a>
    </div>

    {{-- Vehicle status summary --}}
    @if($vehicle->latestState)
        <div class="mb-6 rounded-xl border border-border-default bg-surface p-4">
            <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-lg font-semibold">{{ $vehicle->name }}</h3>
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
                @if($vehicle->latestState->locked)
                    <svg class="h-4 w-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                @else
                    <svg class="h-4 w-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                    </svg>
                @endif
                @if($vehicle->latestState->climate_on)
                    <span class="rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 px-2.5 py-0.5 text-xs font-medium">Climate</span>
                @endif
                @if($vehicle->latestState->sentry_mode)
                    <span class="rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 px-2.5 py-0.5 text-xs font-medium">Sentry</span>
                @endif
            </div>
            <div class="mt-3 flex flex-wrap gap-6 text-sm text-text-secondary">
                <span>Battery: <strong>{{ $vehicle->latestState->battery_level !== null ? number_format($vehicle->latestState->battery_level, 0) . '%' : '—' }}</strong></span>
                <span>Range: <strong>{{ $vehicle->latestState->rated_range ? number_format(auth()->user()->convertDistance($vehicle->latestState->rated_range), 0) . ' ' . auth()->user()->distanceUnit() : '—' }}</strong></span>
                <span class="text-xs text-text-faint">Updated {{ $vehicle->latestState->timestamp->diffForHumans() }}</span>
            </div>
        </div>
    @endif

    {{-- Result banner --}}
    @if($lastResultMessage)
        <div class="mb-6 flex items-start gap-3 rounded-lg border px-4 py-3
            {{ $lastResultSuccess ? 'border-green-800 bg-green-950/50' : 'border-red-800 bg-red-950/50' }}">
            @if($lastResultSuccess)
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-sm font-medium text-green-300">{{ $lastResultMessage }}</p>
            @else
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm font-medium text-red-300">{{ $lastResultMessage }}</p>
            @endif
        </div>
    @endif

    @if(!$canExecute)
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-yellow-800 bg-yellow-950/50 px-4 py-3">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-medium text-yellow-300">Commands are disabled for manually-added vehicles without a Tesla connection.</p>
        </div>
    @endif

    <p class="mb-6 text-xs text-text-faint">May take up to 30 seconds if vehicle is asleep.</p>

    {{-- Command sections --}}
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Security --}}
        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-text-muted">Security</h3>
            <div class="grid grid-cols-2 gap-3">
                <button wire:click="executeCommand('lock')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'lock')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @else
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    @endif
                    Lock
                </button>
                <button wire:click="executeCommand('unlock')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'unlock')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @else
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    @endif
                    Unlock
                </button>
                <button wire:click="executeCommand('sentry_on')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'sentry_on')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @else
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    @endif
                    Sentry On
                </button>
                <button wire:click="executeCommand('sentry_off')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'sentry_off')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @else
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    @endif
                    Sentry Off
                </button>
            </div>
        </div>

        {{-- Climate --}}
        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-text-muted">Climate</h3>
            <div class="grid grid-cols-2 gap-3">
                <button wire:click="executeCommand('climate_on')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'climate_on')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Climate On
                </button>
                <button wire:click="executeCommand('climate_off')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'climate_off')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Climate Off
                </button>
                <button wire:click="executeCommand('vent_windows')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'vent_windows')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Vent Windows
                </button>
                <button wire:click="executeCommand('close_windows')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'close_windows')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Close Windows
                </button>
            </div>
            <div class="mt-4 rounded-lg border border-border-default bg-surface-alt/50 p-4">
                <p class="mb-2 text-xs font-medium text-text-muted">Set Temperatures ({{ $usesF ? '°F' : '°C' }})</p>
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <label class="block text-xs text-text-subtle">Driver</label>
                        <input type="number" wire:model="driverTemp" min="{{ $usesF ? 59 : 15 }}" max="{{ $usesF ? 82 : 28 }}" step="{{ $usesF ? 1 : 0.5 }}"
                            class="mt-1 block w-full rounded-lg border border-border-input bg-surface px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none"
                            {{ !$canExecute ? 'disabled' : '' }}>
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs text-text-subtle">Passenger</label>
                        <input type="number" wire:model="passengerTemp" min="{{ $usesF ? 59 : 15 }}" max="{{ $usesF ? 82 : 28 }}" step="{{ $usesF ? 1 : 0.5 }}"
                            class="mt-1 block w-full rounded-lg border border-border-input bg-surface px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none"
                            {{ !$canExecute ? 'disabled' : '' }}>
                    </div>
                    <button wire:click="executeCommand('set_temps')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        @if($executingCommand === 'set_temps')
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        @else
                            Set
                        @endif
                    </button>
                </div>
            </div>
        </div>

        {{-- Charging --}}
        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-text-muted">Charging</h3>
            <div class="grid grid-cols-2 gap-3">
                <button wire:click="executeCommand('charge_start')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'charge_start')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Start Charging
                </button>
                <button wire:click="executeCommand('charge_stop')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'charge_stop')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Stop Charging
                </button>
                <button wire:click="executeCommand('charge_port_open')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'charge_port_open')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Open Port
                </button>
                <button wire:click="executeCommand('charge_port_close')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'charge_port_close')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Close Port
                </button>
            </div>
            <div class="mt-4 rounded-lg border border-border-default bg-surface-alt/50 p-4">
                <p class="mb-2 text-xs font-medium text-text-muted">
                    Set Charge Limit
                    @if($vehicle->latestState?->charge_limit_soc)
                        <span class="font-normal text-text-subtle">(currently {{ number_format($vehicle->latestState->charge_limit_soc, 0) }}%)</span>
                    @endif
                </p>
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <input type="range" wire:model="chargeLimit" min="50" max="100" step="1"
                            class="w-full accent-red-600" {{ !$canExecute ? 'disabled' : '' }}>
                        <div class="mt-1 flex justify-between text-xs text-text-faint">
                            <span>50%</span>
                            <span class="font-medium text-text-secondary">{{ $chargeLimit }}%</span>
                            <span>100%</span>
                        </div>
                    </div>
                    <button wire:click="executeCommand('set_charge_limit')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        @if($executingCommand === 'set_charge_limit')
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        @else
                            Set
                        @endif
                    </button>
                </div>
            </div>
        </div>

        {{-- Other --}}
        <div class="rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-text-muted">Other</h3>
            <div class="grid grid-cols-2 gap-3">
                <button wire:click="executeCommand('honk_horn')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'honk_horn')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Honk Horn
                </button>
                <button wire:click="executeCommand('flash_lights')" {{ !$canExecute || $executingCommand ? 'disabled' : '' }}
                    class="flex items-center justify-center gap-2 rounded-lg border border-border-input bg-surface-alt px-4 py-3 text-sm font-medium text-text-primary transition hover:bg-surface-alt/80 disabled:opacity-50 disabled:cursor-not-allowed">
                    @if($executingCommand === 'flash_lights')
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    Flash Lights
                </button>
            </div>
        </div>
    </div>

    {{-- Command History --}}
    @if($commandHistory->isNotEmpty())
        <div class="mt-6 rounded-xl border border-border-default bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-text-muted">Command History</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border-default text-left text-xs text-text-muted">
                            <th class="pb-2 pr-4">Time</th>
                            <th class="pb-2 pr-4">Command</th>
                            <th class="pb-2 pr-4">Status</th>
                            <th class="pb-2">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @foreach($commandHistory as $log)
                            <tr>
                                <td class="py-2 pr-4 text-text-subtle">{{ $log->executed_at->diffForHumans() }}</td>
                                <td class="py-2 pr-4 font-medium text-text-secondary">{{ str_replace('_', ' ', $log->command) }}</td>
                                <td class="py-2 pr-4">
                                    @if($log->success)
                                        <span class="rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 px-2 py-0.5 text-xs font-medium">Success</span>
                                    @else
                                        <span class="rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 px-2 py-0.5 text-xs font-medium">Failed</span>
                                    @endif
                                </td>
                                <td class="py-2 text-xs text-text-faint">{{ $log->error_message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
