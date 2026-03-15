<div>
    {{-- Back link --}}
    <div class="mb-6">
        <a href="{{ route('web.vehicles') }}" class="text-sm text-text-subtle hover:text-text-secondary">&larr; Vehicles</a>
    </div>

    {{-- Stats row --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-border-default bg-surface p-5">
            <p class="text-xs text-text-subtle">Degradation</p>
            <p class="text-2xl font-bold {{ $currentDegradation !== null && $currentDegradation > 10 ? 'text-yellow-500' : '' }}">
                {{ $currentDegradation !== null ? number_format($currentDegradation, 1) . '%' : '—' }}
            </p>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-5">
            <p class="text-xs text-text-subtle">Current Range at Full</p>
            <p class="text-2xl font-bold">
                {{ $currentRangeAtFull !== null ? number_format($user->convertDistance($currentRangeAtFull), 0) . ' ' . $user->distanceUnit() : '—' }}
            </p>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-5">
            <p class="text-xs text-text-subtle">Original Range at Full</p>
            <p class="text-2xl font-bold">
                {{ $originalRangeAtFull !== null ? number_format($user->convertDistance($originalRangeAtFull), 0) . ' ' . $user->distanceUnit() : '—' }}
            </p>
        </div>
        <div class="rounded-xl border border-border-default bg-surface p-5">
            <p class="text-xs text-text-subtle">Battery Capacity</p>
            <p class="text-2xl font-bold">
                {{ $vehicle->battery_capacity_kwh ? number_format($vehicle->battery_capacity_kwh, 0) . ' kWh' : '—' }}
            </p>
        </div>
    </div>

    {{-- Degradation chart --}}
    <div class="mt-6 rounded-xl border border-border-default bg-surface p-6">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-text-muted">Range & Degradation Over Time</h3>
        @if(count($chartData) > 1)
            <div wire:ignore class="h-64">
                <canvas id="health-chart"></canvas>
            </div>
        @else
            <p class="text-sm text-text-subtle">Not enough data yet. Battery health snapshots are recorded daily when the vehicle reaches 90%+ charge.</p>
        @endif
    </div>

    {{-- Battery health table --}}
    <div class="mt-6 rounded-xl border border-border-default bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-text-muted">Battery Health History</h3>
        @if($healthRecords->isEmpty())
            <p class="text-sm text-text-subtle">No battery health records yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border-default text-left text-xs text-text-muted">
                            <th class="pb-2 font-medium">Date</th>
                            <th class="pb-2 font-medium">Battery Level</th>
                            <th class="pb-2 font-medium">Rated Range</th>
                            <th class="pb-2 font-medium">Degradation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @foreach($healthRecords as $record)
                            <tr>
                                <td class="py-2 text-text-secondary">{{ $record->recorded_at->format('M j, Y') }}</td>
                                <td class="py-2">{{ $record->battery_level }}%</td>
                                <td class="py-2">{{ number_format($user->convertDistance($record->rated_range), 0) }} {{ $user->distanceUnit() }}</td>
                                <td class="py-2 {{ $record->degradation_pct !== null && $record->degradation_pct > 10 ? 'text-yellow-500' : '' }}">
                                    {{ $record->degradation_pct !== null ? number_format($record->degradation_pct, 1) . '%' : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @script
    <script>
        $wire.on('health-chart-data', ({ data }) => {
            const canvas = document.getElementById('health-chart');
            if (!canvas || data.length < 2) return;

            if (window.__healthChart) {
                window.__healthChart.destroy();
            }

            const ctx = canvas.getContext('2d');
            const colors = typeof window.getChartColors === 'function' ? window.getChartColors() : {};
            const tickColor = colors.tickColor || getComputedStyle(document.documentElement).getPropertyValue('--theme-text-muted').trim();
            const gridColor = colors.gridColor || getComputedStyle(document.documentElement).getPropertyValue('--theme-surface-alt').trim();

            const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.15)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

            window.__healthChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.date),
                    datasets: [
                        {
                            label: 'Range at Full',
                            data: data.map(d => d.range_at_full),
                            borderColor: '#3b82f6',
                            borderWidth: 2,
                            backgroundColor: gradient,
                            fill: true,
                            tension: 0.3,
                            pointRadius: data.length > 60 ? 0 : 2,
                            pointHitRadius: 8,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Degradation %',
                            data: data.map(d => d.degradation),
                            borderColor: '#f59e0b',
                            borderWidth: 2,
                            borderDash: [5, 3],
                            fill: false,
                            tension: 0.3,
                            pointRadius: data.length > 60 ? 0 : 2,
                            pointHitRadius: 8,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            labels: { color: tickColor, boxWidth: 12, padding: 16 },
                        },
                        tooltip: {
                            callbacks: {
                                title: (items) => items[0]?.label || '',
                                label: (item) => {
                                    if (item.datasetIndex === 0) return 'Range: ' + (item.parsed.y?.toFixed(0) || '—') + ' mi';
                                    return 'Degradation: ' + (item.parsed.y?.toFixed(1) || '—') + '%';
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { unit: data.length > 90 ? 'month' : 'week' },
                            grid: { display: false },
                            ticks: { color: tickColor, font: { size: 10 }, maxTicksLimit: 10 },
                            border: { display: false },
                        },
                        y: {
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Range (mi)', color: tickColor, font: { size: 10 } },
                            grid: { color: gridColor },
                            ticks: { color: tickColor, font: { size: 10 } },
                            border: { display: false },
                        },
                        y1: {
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Degradation %', color: tickColor, font: { size: 10 } },
                            min: 0,
                            grid: { display: false },
                            ticks: { color: tickColor, font: { size: 10 }, callback: (v) => v + '%' },
                            border: { display: false },
                        },
                    },
                },
            });
        });
    </script>
    @endscript
</div>
