<div class="mb-6 rounded-xl border border-border-default bg-surface p-6">
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button wire:click="previousMonth" class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-secondary hover:bg-elevated">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div class="relative" x-data="{ picking: false }">
                <h3 x-on:click="picking = !picking" class="cursor-pointer text-lg font-semibold text-text-primary hover:text-text-secondary">{{ $calendarLabel }}</h3>
                <div x-cloak x-show="picking" x-on:click.outside="picking = false" x-transition
                     class="absolute left-1/2 top-full z-20 mt-2 -translate-x-1/2 rounded-lg border border-border-input bg-surface-alt p-3 shadow-xl">
                    <select x-on:change="$wire.set('calendarMonth', $event.target.value); picking = false"
                        class="rounded-lg border border-border-strong bg-surface px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                        @php
                            $selectedMonth = \Carbon\Carbon::parse($calendarMonth . '-01');
                            $oldest = min($selectedMonth->copy()->subMonths(12), now()->subMonths(23));
                            $newest = now()->startOfMonth();
                        @endphp
                        @for($m = $newest->copy(); $m->gte($oldest); $m = $m->copy()->subMonth())
                            <option value="{{ $m->format('Y-m') }}" @selected($m->format('Y-m') === $calendarMonth)>{{ $m->format('F Y') }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <button wire:click="nextMonth" class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-secondary hover:bg-elevated"
                @if($isCurrentMonth) disabled style="opacity: 0.5" @endif>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            @if(!$isCurrentMonth)
                <button wire:click="currentMonth" class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-xs text-text-muted hover:bg-elevated">Today</button>
            @endif
        </div>
        <div class="flex items-center gap-3 text-xs text-text-subtle">
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span> Drives</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-green-500"></span> Charge</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-red-500"></span> Supercharge</span>
        </div>
    </div>
    <div class="grid grid-cols-7 gap-px overflow-hidden rounded-lg border border-border-default bg-border-default">
        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
            <div class="bg-surface px-2 py-1.5 text-center text-xs font-medium text-text-muted">{{ $dayName }}</div>
        @endforeach
        @foreach($calendarWeeks as $week)
            @foreach($week as $day)
                @if($day['inMonth'])
                    <a href="{{ route('web.drives', ['period' => 'week', 'week' => \Carbon\Carbon::parse($day['date'])->startOfWeek()->format('Y-m-d')]) }}"
                       class="flex min-h-[3.5rem] flex-col bg-page p-1.5 transition hover:bg-surface-alt {{ $day['isToday'] ? 'ring-2 ring-inset ring-red-500' : '' }}">
                        <span class="text-xs font-medium {{ $day['isToday'] ? 'text-red-500' : 'text-text-secondary' }}">{{ $day['day'] }}</span>
                        <div class="mt-auto flex flex-wrap gap-1">
                            @if($day['drives'])
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-medium text-blue-400"><span class="inline-block h-1.5 w-1.5 rounded-full bg-blue-500"></span>{{ $day['drives'] }}</span>
                            @endif
                            @if($day['acCharges'])
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-medium text-green-400"><span class="inline-block h-1.5 w-1.5 rounded-full bg-green-500"></span>{{ $day['acCharges'] }}</span>
                            @endif
                            @if($day['dcCharges'])
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-medium text-red-400"><span class="inline-block h-1.5 w-1.5 rounded-full bg-red-500"></span>{{ $day['dcCharges'] }}</span>
                            @endif
                        </div>
                    </a>
                @else
                    <div class="min-h-[3.5rem] bg-surface p-1.5">
                        <span class="text-xs text-text-subtle/40">{{ $day['day'] }}</span>
                    </div>
                @endif
            @endforeach
        @endforeach
    </div>
</div>
