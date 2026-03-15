<div class="mb-6 rounded-xl border border-border-default bg-surface p-6">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-text-muted">Temperature vs Efficiency</h3>
    @if($hasTempEffData)
        <div wire:ignore class="h-64">
            <canvas id="temp-eff-chart"></canvas>
        </div>
    @else
        <p class="text-sm text-text-subtle">Not enough data with both temperature and efficiency readings.</p>
    @endif
</div>
