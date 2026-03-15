<div>
    {{-- Back link --}}
    <div class="mb-6">
        <a href="{{ route('web.vehicles') }}" class="text-sm text-text-subtle hover:text-text-secondary">&larr; Vehicles</a>
    </div>

    {{-- Summary --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-border-default bg-surface p-5">
            <p class="text-xs text-text-subtle">Current Version</p>
            <p class="text-lg font-bold text-green-400">{{ $vehicle->firmware_version ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-5">
            <p class="text-xs text-text-subtle">Total Updates</p>
            <p class="text-2xl font-bold">{{ $totalVersions }}</p>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-5">
            <p class="text-xs text-text-subtle">Major Versions</p>
            <p class="text-2xl font-bold">{{ $firmwareGroups->count() }}</p>
        </div>
    </div>

    {{-- Firmware timeline --}}
    <div class="rounded-xl border border-border-default bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-text-muted">Version Timeline</h3>
        @if($firmwareGroups->isEmpty())
            <p class="text-sm text-text-subtle">No firmware updates recorded yet.</p>
        @else
            <div class="space-y-5">
                @foreach($firmwareGroups as $majorVersion => $versions)
                    <div>
                        {{-- Major version header --}}
                        <div class="mb-2 flex items-center gap-2">
                            <h4 class="text-sm font-semibold {{ $loop->first ? 'text-green-400' : 'text-text-secondary' }}">{{ $majorVersion }}</h4>
                            <span class="text-xs text-text-faint">
                                {{ $versions->first()->detected_at->format('M Y') }}
                                @if($versions->count() > 1)
                                    &middot; {{ $versions->count() }} updates
                                @endif
                                &middot; {{ $versions->sum('days') }} days total
                            </span>
                        </div>

                        {{-- Minor versions within group --}}
                        <div class="ml-2 space-y-1.5 border-l-2 border-border-default pl-4">
                            @foreach($versions as $fw)
                                <div class="group">
                                    <div class="flex items-center gap-3">
                                        {{-- Timeline dot --}}
                                        <div class="-ml-[1.3rem] h-2 w-2 rounded-full {{ $fw->is_current ? 'bg-green-500' : 'bg-gray-500' }}"></div>

                                        {{-- Version info --}}
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium {{ $fw->is_current ? 'text-green-400' : 'text-text-secondary' }}">{{ $fw->version }}</span>
                                                <span class="rounded-full bg-surface-alt px-2 py-0.5 text-xs tabular-nums text-text-subtle">{{ $fw->days }}d</span>
                                                @if($fw->is_current)
                                                    <span class="rounded-full bg-green-900/50 px-2 py-0.5 text-xs text-green-400">current</span>
                                                @endif
                                            </div>
                                            <p class="text-xs text-text-faint">{{ $fw->detected_at->format('M j, Y') }}</p>
                                        </div>

                                        {{-- Duration bar --}}
                                        <div class="hidden w-32 sm:block">
                                            <div class="h-1.5 rounded-full bg-surface-alt">
                                                <div class="h-1.5 rounded-full {{ $fw->is_current ? 'bg-green-500' : 'bg-gray-500' }}"
                                                     style="width: {{ max(3, round($fw->days / $maxDays * 100)) }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
