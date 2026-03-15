<div class="mb-6 rounded-xl border border-border-default bg-surface p-6">
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-text-muted">Energy Used vs Charged</h3>
        <div class="flex gap-1">
            @foreach([7, 14, 30, 90] as $d)
                <button wire:click="updateEnergyDays({{ $d }})"
                    class="rounded-md px-2.5 py-1 text-xs {{ $energyDays === $d ? 'bg-red-500 text-white' : 'bg-surface-alt text-text-muted hover:text-text-secondary' }}">
                    {{ $d }}d
                </button>
            @endforeach
        </div>
    </div>
    @if($hasEnergyData)
        <div wire:ignore class="h-64">
            <canvas id="energy-chart"></canvas>
        </div>
    @else
        <p class="text-sm text-text-subtle">No energy data for the selected period.</p>
    @endif
</div>
