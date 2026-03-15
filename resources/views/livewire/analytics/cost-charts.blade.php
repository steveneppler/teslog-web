<div class="mb-6 grid gap-6 lg:grid-cols-2">
    {{-- Cost by charge type (doughnut) --}}
    <div class="rounded-xl border border-border-default bg-surface p-6">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-text-muted">Cost by Charge Type</h3>
            <div class="flex gap-1">
                @foreach([30, 90, 180, 365] as $d)
                    <button wire:click="updateCostDays({{ $d }})"
                        class="rounded-md px-2.5 py-1 text-xs {{ $costDays === $d ? 'bg-red-500 text-white' : 'bg-surface-alt text-text-muted hover:text-text-secondary' }}">
                        {{ $d }}d
                    </button>
                @endforeach
            </div>
        </div>
        @if($hasCostByType)
            <div wire:ignore class="flex h-56 items-center justify-center">
                <canvas id="cost-doughnut-chart"></canvas>
            </div>
        @else
            <p class="text-sm text-text-subtle">No cost data for the selected period.</p>
        @endif
    </div>

    {{-- Monthly cost trend (bar) --}}
    <div class="rounded-xl border border-border-default bg-surface p-6">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-text-muted">Monthly Cost Trend</h3>
        @if($hasCostMonthly)
            <div wire:ignore class="h-56">
                <canvas id="cost-monthly-chart"></canvas>
            </div>
        @else
            <p class="text-sm text-text-subtle">No cost data for the selected period.</p>
        @endif
    </div>
</div>
