<div class="mx-auto max-w-2xl py-8">
    {{-- Step Indicator --}}
    <div class="mb-10 flex items-center justify-center">
        @foreach ([1 => 'Connect', 2 => 'Vehicles', 3 => 'Done'] as $num => $label)
            <div class="flex items-center">
                <div class="flex flex-col items-center">
                    <div @class([
                        'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold',
                        'border-red-500 bg-red-500 text-white' => $step >= $num,
                        'border-border-strong bg-surface-alt text-text-muted' => $step < $num,
                    ])>
                        @if ($step > $num)
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $num }}
                        @endif
                    </div>
                    <span @class([
                        'mt-2 text-xs font-medium',
                        'text-text-primary' => $step >= $num,
                        'text-text-subtle' => $step < $num,
                    ])>{{ $label }}</span>
                </div>

                @if ($num < 3)
                    <div @class([
                        'mx-3 mb-6 h-0.5 w-16',
                        'bg-red-500' => $step > $num,
                        'bg-elevated' => $step <= $num,
                    ])></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Error Banner --}}
    @if ($error)
        <div class="mb-6 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
            {{ $error }}
        </div>
    @endif

    {{-- Step 1: Connect Tesla Account --}}
    @if ($step === 1)
        <div class="rounded-xl border border-border-default bg-surface p-8 text-center">
            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-surface-alt">
                <svg class="h-10 w-10 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.477 2 2 3.29 2 4.875V6c0 .084.012.166.025.247L2 6.375V7.5l.014.074C2.188 9.567 6.6 11 12 11s9.812-1.433 9.986-3.426L22 7.5V6.375l-.025-.128C21.988 6.166 22 6.084 22 6V4.875C22 3.29 17.523 2 12 2zm0 1c4.91 0 9 1.12 9 2.5v.375c-.003.064-.054.194-.288.385-.468.382-1.399.806-2.712 1.14C16.387 7.78 14.27 8 12 8s-4.387-.22-6-.601c-1.313-.334-2.244-.758-2.712-1.14C3.054 6.069 3.003 5.939 3 5.875V5.5C3 4.12 7.09 3 12 3zm-1 9v3h2v-3h3l-4-4-4 4h3zm-1 4v3h4v-3h-4z"/>
                </svg>
            </div>

            <h2 class="mb-3 text-2xl font-bold text-text-primary">Connect Your Tesla Account</h2>
            <p class="mb-8 text-text-muted">
                Link your Tesla account to start tracking your vehicles. You'll be redirected to Tesla's
                secure login page to authorize access.
            </p>

            <a href="{{ route('tesla.redirect') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-page">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                Connect Tesla Account
            </a>
        </div>
    @endif

    {{-- Step 2: Select Vehicles --}}
    @if ($step === 2)
        <div class="rounded-xl border border-border-default bg-surface p-8">
            @if (!empty($updatedVehicleNames))
                <div class="mb-6 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-400">
                    Tokens refreshed for: {{ implode(', ', $updatedVehicleNames) }}
                </div>
            @endif

            @if (count($vehicles) === 0 && !empty($updatedVehicleNames))
                <h2 class="mb-2 text-2xl font-bold text-text-primary">All Vehicles Linked</h2>
                <p class="mb-6 text-text-muted">All vehicles on your Tesla account are already linked. No new vehicles found.</p>
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-red-700">
                    Go to Dashboard
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            @elseif (count($vehicles) === 0)
                <h2 class="mb-2 text-2xl font-bold text-text-primary">Select Your Vehicles</h2>
                <div class="rounded-lg border border-border-input bg-surface-alt p-6 text-center text-text-muted">
                    No vehicles found on your Tesla account.
                </div>
            @else
                <h2 class="mb-2 text-2xl font-bold text-text-primary">
                    {{ !empty($updatedVehicleNames) ? 'New Vehicles Found' : 'Select Your Vehicles' }}
                </h2>
                <p class="mb-6 text-text-muted">
                    {{ !empty($updatedVehicleNames) ? 'The following new vehicles were found on your Tesla account. Select which to link.' : 'Choose which vehicles you\'d like to track with Teslog.' }}
                </p>

                <div class="space-y-3">
                    @foreach ($vehicles as $vehicle)
                        <label class="flex cursor-pointer items-center gap-4 rounded-lg border border-border-input bg-surface-alt p-4 transition hover:border-border-strong has-[:checked]:border-red-500/50 has-[:checked]:bg-red-500/5">
                            <input type="checkbox"
                                   wire:model.live="selectedVehicles"
                                   value="{{ $vehicle['id'] }}"
                                   class="h-5 w-5 rounded border-border-strong bg-elevated text-red-500 focus:ring-red-500 focus:ring-offset-0">
                            <div class="flex-1">
                                <div class="font-semibold text-text-primary">
                                    {{ $vehicle['display_name'] ?? 'Tesla Vehicle' }}
                                </div>
                                <div class="mt-1 flex items-center gap-3 text-sm text-text-muted">
                                    <span>{{ $vehicle['vin'] ?? 'Unknown VIN' }}</span>
                                    @if (! empty($vehicle['vehicle_state']['vehicle_name']))
                                        <span class="text-text-faint">&middot;</span>
                                        <span>{{ $vehicle['vehicle_state']['vehicle_name'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-xs text-text-subtle">
                                {{ ucfirst($vehicle['state'] ?? 'unknown') }}
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    @if (!empty($updatedVehicleNames))
                        <a href="{{ route('dashboard') }}"
                           class="rounded-lg border border-border-input px-6 py-3 text-sm text-text-muted hover:text-text-primary">
                            Skip
                        </a>
                    @endif
                    <button type="button"
                            wire:click="linkVehicles"
                            class="rounded-lg bg-red-600 px-6 py-3 text-sm font-semibold text-white hover:bg-red-700">
                        Link Selected Vehicles
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Step 3: Done --}}
    @if ($step === 3)
        <div class="rounded-xl border border-border-default bg-surface p-8">
            <div class="text-center">
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-500/10">
                    <svg class="h-10 w-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <h2 class="mb-3 text-2xl font-bold text-text-primary">Vehicles Linked!</h2>
                <p class="mb-6 text-text-muted">
                    Your vehicles have been linked and telemetry streaming has been configured.
                </p>
            </div>

            @if ($telemetryError)
                <div class="mb-6 rounded-lg border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-400">
                    <strong>Telemetry setup note:</strong> {{ $telemetryError }}
                </div>
            @endif

            <div class="mb-8 rounded-lg border border-border-input bg-surface-alt p-5">
                <h3 class="mb-3 text-sm font-semibold text-text-primary">Pair Your Public Key</h3>
                <p class="mb-3 text-sm text-text-muted">
                    Tesla requires your app's public key to be paired with each vehicle. This is a one-time step:
                </p>
                <ol class="mb-4 list-inside list-decimal space-y-2 text-sm text-text-muted">
                    <li>Open this link <strong>on your phone</strong>, near the vehicle:</li>
                </ol>
                <div class="mb-4 rounded-lg bg-elevated px-4 py-3">
                    <a href="https://tesla.com/_ak/{{ parse_url(config('app.url'), PHP_URL_HOST) }}"
                       target="_blank"
                       class="text-sm font-medium text-red-400 hover:text-red-300 break-all">
                        https://tesla.com/_ak/{{ parse_url(config('app.url'), PHP_URL_HOST) }}
                    </a>
                </div>
                <ol start="2" class="list-inside list-decimal space-y-2 text-sm text-text-muted">
                    <li>The Tesla app will prompt you to approve the key</li>
                    <li>Tap <strong>Approve</strong> on the vehicle's touchscreen</li>
                </ol>
                <p class="mt-3 text-xs text-text-subtle">
                    If you've already paired the key on this domain, you can skip this step.
                </p>
            </div>

            <div class="text-center">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-page">
                    Go to Dashboard
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
        </div>
    @endif
</div>
