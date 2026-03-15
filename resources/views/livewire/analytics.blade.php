<div>
    {{-- Controls bar --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        @if($vehicles->count() > 1)
            <select wire:model.live="vehicleFilter"
                class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                <option value="">All vehicles</option>
                @foreach($vehicles as $v)
                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- Calendar --}}
    <livewire:analytics.calendar-panel :vehicleFilter="$vehicleFilter" />

    {{-- Efficiency over time --}}
    <livewire:analytics.efficiency-chart :vehicleFilter="$vehicleFilter" />

    {{-- Energy used vs charged --}}
    <livewire:analytics.energy-chart :vehicleFilter="$vehicleFilter" />

    {{-- Cost section --}}
    <livewire:analytics.cost-charts :vehicleFilter="$vehicleFilter" />

    {{-- Temperature vs Efficiency scatter --}}
    <livewire:analytics.temp-efficiency-chart :vehicleFilter="$vehicleFilter" />

    {{-- Export panel --}}
    <div class="rounded-xl border border-border-default bg-surface p-6 space-y-6">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-text-muted">Export Data</h3>

        {{-- Drives / Charges export --}}
        <div>
            <p class="mb-3 text-xs font-medium text-text-secondary">Drives & Charges</p>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="mb-1 block text-xs text-text-subtle">From</label>
                    <input type="date" wire:model="exportFrom"
                        class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-text-subtle">To</label>
                    <input type="date" wire:model="exportTo"
                        class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-text-subtle">Tag (optional)</label>
                    <input type="text" wire:model="exportTag" placeholder="e.g. business"
                        class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-text-subtle">Type</label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-1.5 text-sm text-text-secondary">
                            <input type="radio" wire:model="exportType" value="drives" class="accent-red-500"> Drives
                        </label>
                        <label class="flex items-center gap-1.5 text-sm text-text-secondary">
                            <input type="radio" wire:model="exportType" value="charges" class="accent-red-500"> Charges
                        </label>
                    </div>
                </div>
                <button wire:click="downloadExport"
                    class="rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">
                    Download CSV
                </button>
            </div>
        </div>

        {{-- Raw data export --}}
        <div class="border-t border-border-default pt-6">
            <p class="mb-1 text-xs font-medium text-text-secondary">Raw Data (Vehicle States)</p>
            <p class="mb-3 text-xs text-text-subtle">Export by month in TeslaFi-compatible format. Can be re-imported into Teslog or other tools.</p>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="mb-1 block text-xs text-text-subtle">Vehicle</label>
                    <select wire:model="rawExportVehicle"
                        class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                        <option value="">Select...</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-text-subtle">Month</label>
                    <input type="month" wire:model="rawExportMonth"
                        class="rounded-lg border border-border-input bg-surface-alt px-3 py-2 text-sm text-text-primary focus:border-red-500 focus:outline-none">
                </div>
                <button wire:click="downloadRawExport"
                    class="rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">
                    Download CSV
                </button>
            </div>
        </div>
    </div>

    @script
    <script>
        const chartColors = typeof window.getChartColors === 'function' ? window.getChartColors() : {};
        const tickColor = chartColors.tick || getComputedStyle(document.documentElement).getPropertyValue('--theme-text-muted').trim() || '#6b7280';
        const gridColor = chartColors.grid || getComputedStyle(document.documentElement).getPropertyValue('--theme-surface-alt').trim() || '#1f2937';

        // Efficiency chart (line, dual Y-axis)
        Livewire.on('efficiency-chart-data', ({ data, effUnit, tempUnit }) => {
            const canvas = document.getElementById('efficiency-chart');
            if (!canvas || data.length < 2) return;
            if (window.__effChart) window.__effChart.destroy();

            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.15)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

            const hasTemp = data.some(d => d.avg_temp_raw !== null);

            const datasets = [{
                label: 'Efficiency',
                data: data.map(d => d.avg_efficiency),
                borderColor: '#3b82f6',
                borderWidth: 2,
                backgroundColor: gradient,
                fill: true,
                tension: 0.3,
                pointRadius: data.length > 60 ? 0 : 2,
                pointHitRadius: 8,
                yAxisID: 'y',
            }];

            if (hasTemp) {
                datasets.push({
                    label: 'Temperature',
                    data: data.map(d => d.avg_temp_raw),
                    borderColor: '#f59e0b',
                    borderWidth: 2,
                    borderDash: [5, 3],
                    fill: false,
                    tension: 0.3,
                    pointRadius: data.length > 60 ? 0 : 2,
                    pointHitRadius: 8,
                    yAxisID: 'y1',
                });
            }

            window.__effChart = new Chart(ctx, {
                type: 'line',
                data: { labels: data.map(d => d.date), datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: true, labels: { color: tickColor, boxWidth: 12, padding: 16 } },
                        tooltip: {
                            callbacks: {
                                label: (item) => {
                                    if (item.datasetIndex === 0) return 'Efficiency: ' + (item.parsed.y || '—') + ' ' + effUnit;
                                    return 'Temp: ' + (item.parsed.y || '—') + ' ' + tempUnit;
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
                            title: { display: true, text: effUnit, color: tickColor, font: { size: 10 } },
                            grid: { color: gridColor },
                            ticks: { color: tickColor, font: { size: 10 } },
                            border: { display: false },
                        },
                        y1: {
                            display: hasTemp,
                            position: 'right',
                            title: { display: true, text: tempUnit, color: tickColor, font: { size: 10 } },
                            grid: { display: false },
                            ticks: { color: tickColor, font: { size: 10 }, callback: (v) => v + tempUnit },
                            border: { display: false },
                        },
                    },
                },
            });
        });

        // Energy chart (bar)
        Livewire.on('energy-chart-data', ({ data }) => {
            const canvas = document.getElementById('energy-chart');
            if (!canvas || data.length === 0) return;
            if (window.__energyChart) window.__energyChart.destroy();

            window.__energyChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.map(d => d.date),
                    datasets: [
                        {
                            label: 'Energy Used (kWh)',
                            data: data.map(d => d.used),
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderRadius: 3,
                        },
                        {
                            label: 'Energy Charged (kWh)',
                            data: data.map(d => d.added),
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderRadius: 3,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: true, labels: { color: tickColor, boxWidth: 12, padding: 16 } },
                        tooltip: { callbacks: { label: (item) => item.dataset.label + ': ' + item.parsed.y + ' kWh' } },
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { unit: data.length > 30 ? 'week' : 'day' },
                            grid: { display: false },
                            ticks: { color: tickColor, font: { size: 10 }, maxTicksLimit: 10 },
                            border: { display: false },
                        },
                        y: {
                            grid: { color: gridColor },
                            ticks: { color: tickColor, font: { size: 10 } },
                            border: { display: false },
                            title: { display: true, text: 'kWh', color: tickColor, font: { size: 10 } },
                        },
                    },
                },
            });
        });

        // Cost charts (doughnut + monthly bar)
        Livewire.on('cost-chart-data', ({ byType, monthly, currency }) => {
            // Doughnut
            const doughnutCanvas = document.getElementById('cost-doughnut-chart');
            if (doughnutCanvas && byType.length > 0) {
                if (window.__costDoughnut) window.__costDoughnut.destroy();

                const typeColors = {
                    'AC': '#3b82f6',
                    'DC': '#ef4444',
                    'Supercharger': '#f59e0b',
                    'Home': '#22c55e',
                };
                const fallbackColors = ['#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1'];

                window.__costDoughnut = new Chart(doughnutCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: byType.map(t => t.charge_type || 'Unknown'),
                        datasets: [{
                            data: byType.map(t => parseFloat(t.total_cost)),
                            backgroundColor: byType.map((t, i) => typeColors[t.charge_type] || fallbackColors[i % fallbackColors.length]),
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, position: 'right', labels: { color: tickColor, padding: 12 } },
                            tooltip: {
                                callbacks: {
                                    label: (item) => item.label + ': ' + currency + ' ' + item.parsed.toFixed(2),
                                },
                            },
                        },
                    },
                });
            }

            // Monthly bar
            const monthlyCanvas = document.getElementById('cost-monthly-chart');
            if (monthlyCanvas && monthly.length > 0) {
                if (window.__costMonthly) window.__costMonthly.destroy();

                window.__costMonthly = new Chart(monthlyCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: monthly.map(m => m.month),
                        datasets: [{
                            label: 'Monthly Cost',
                            data: monthly.map(m => parseFloat(m.total_cost)),
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderRadius: 3,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (item) => currency + ' ' + item.parsed.y.toFixed(2),
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: tickColor, font: { size: 10 } },
                                border: { display: false },
                            },
                            y: {
                                grid: { color: gridColor },
                                ticks: { color: tickColor, font: { size: 10 }, callback: (v) => currency + ' ' + v },
                                border: { display: false },
                            },
                        },
                    },
                });
            }
        });

        // Temperature vs Efficiency (line chart, averaged by temp)
        Livewire.on('temp-eff-chart-data', ({ data, effUnit, tempUnit }) => {
            const canvas = document.getElementById('temp-eff-chart');
            if (!canvas || data.length < 3) return;
            if (window.__tempEffChart) window.__tempEffChart.destroy();

            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.15)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

            window.__tempEffChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.temp + tempUnit),
                    datasets: [{
                        label: 'Avg Efficiency',
                        data: data.map(d => d.efficiency),
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 2,
                        pointHitRadius: 8,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (item) => item.parsed.y + ' ' + effUnit + ' at ' + data[item.dataIndex].temp + tempUnit,
                            },
                        },
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Temperature (' + tempUnit + ')', color: tickColor, font: { size: 10 } },
                            grid: { display: false },
                            ticks: { color: tickColor, font: { size: 10 }, maxTicksLimit: 15 },
                            border: { display: false },
                        },
                        y: {
                            title: { display: true, text: 'Avg Efficiency (' + effUnit + ')', color: tickColor, font: { size: 10 } },
                            grid: { color: gridColor },
                            ticks: { color: tickColor, font: { size: 10 } },
                            border: { display: false },
                        },
                    },
                },
            });
        });
    </script>
    @endscript
</div>
