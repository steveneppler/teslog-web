<div class="mb-6 rounded-xl border border-border-default bg-surface p-6">
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-text-muted">Efficiency Over Time</h3>
        <div class="flex gap-1">
            @foreach([30, 90, 180, 365] as $d)
                <button wire:click="updateEfficiencyDays({{ $d }})"
                    class="rounded-md px-2.5 py-1 text-xs {{ $efficiencyDays === $d ? 'bg-red-500 text-white' : 'bg-surface-alt text-text-muted hover:text-text-secondary' }}">
                    {{ $d }}d
                </button>
            @endforeach
        </div>
    </div>
    @if($hasEfficiencyData)
        <div wire:ignore class="h-64">
            <canvas id="efficiency-chart"></canvas>
        </div>
    @else
        <p class="text-sm text-text-subtle">Not enough efficiency data for the selected period.</p>
    @endif
</div>
