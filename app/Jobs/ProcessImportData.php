<?php

namespace App\Jobs;

use App\Models\Charge;
use App\Models\Drive;
use App\Models\VehicleState;
use App\Services\TeslaFiImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProcessImportData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function retryUntil(): \DateTime
    {
        return now()->addHours(4);
    }

    private int $totalImported = 0;

    private int $totalSkipped = 0;

    private array $errors = [];

    public function __construct(
        public int $vehicleId,
        public string $importType,
        public string $timezone,
        public string $units,
        public array $filePaths,
        public array $fileNames,
        public string $cacheKey,
    ) {}

    public function handle(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $service = app(TeslaFiImportService::class);

        $this->updateStatus('Importing CSV data...');

        foreach ($this->filePaths as $i => $path) {
            $fullPath = Storage::disk('local')->path($path);
            $fileName = $this->fileNames[$i] ?? basename($path);

            $importedBefore = $this->totalImported;
            $skippedBefore = $this->totalSkipped;

            $progressCallback = function (int $imported, int $skipped) use ($fileName, $importedBefore, $skippedBefore) {
                $this->totalImported = $importedBefore + $imported;
                $this->totalSkipped = $skippedBefore + $skipped;
                $this->updateStatus("Importing {$fileName}... " . number_format($imported) . ' records');
            };

            $result = match ($this->importType) {
                'raw' => $service->importRawData($fullPath, $this->vehicleId, $this->timezone, $progressCallback),
                'drives' => $service->importDrives($fullPath, $this->vehicleId, $this->timezone, $this->units),
                'charges' => $service->importCharges($fullPath, $this->vehicleId, $this->timezone),
            };

            // For non-raw imports (no progress callback), add the result counts
            if ($this->importType !== 'raw') {
                $this->totalImported += $result['imported'];
                $this->totalSkipped += $result['skipped'];
            }

            foreach ($result['errors'] as $error) {
                $this->errors[] = $fileName . ': ' . $error;
            }

            $this->updateStatus('Imported ' . number_format($this->totalImported) . ' records...');
        }

        // Post-processing for raw imports
        if ($this->importType === 'raw' && $this->totalImported > 0) {
            $this->updateStatus('Processing states into drives and charges...');

            // Process states in monthly chunks via subprocess to avoid OOM
            $range = VehicleState::where('vehicle_id', $this->vehicleId)
                ->selectRaw('MIN(timestamp) as first_ts, MAX(timestamp) as last_ts')
                ->first();

            if ($range->first_ts && $range->last_ts) {
                $cursor = \Carbon\Carbon::parse($range->first_ts)->startOfMonth();
                $end = \Carbon\Carbon::parse($range->last_ts)->endOfMonth();
                $artisan = base_path('artisan');

                // Pre-fetch months that already have drives to avoid per-iteration queries
                $processedMonths = Drive::where('vehicle_id', $this->vehicleId)
                    ->selectRaw("strftime('%Y-%m', started_at) as month")
                    ->distinct()->pluck('month')->flip()->all();

                while ($cursor < $end) {
                    $monthEnd = $cursor->copy()->addMonth();
                    $monthKey = $cursor->format('Y-m');

                    if (! isset($processedMonths[$monthKey])) {
                        $this->updateStatus('Processing states: ' . $cursor->format('M Y') . '...');
                        $output = [];
                        $cmd = sprintf(
                            'php -d memory_limit=512M %s teslog:process-states --vehicle=%d --after=%s --before=%s 2>&1',
                            escapeshellarg($artisan),
                            $this->vehicleId,
                            escapeshellarg($cursor->toDateTimeString()),
                            escapeshellarg($monthEnd->toDateTimeString()),
                        );
                        exec($cmd, $output, $exitCode);
                        if ($exitCode !== 0) {
                            $this->errors[] = 'State processing failed for ' . $cursor->format('M Y') . ': ' . implode("\n", $output);
                        }
                    }

                    $cursor = $monthEnd;
                }
            }

            $driveCount = Drive::where('vehicle_id', $this->vehicleId)->count();
            $chargeCount = Charge::where('vehicle_id', $this->vehicleId)->count();

            $this->updateStatus("Created {$driveCount} drives and {$chargeCount} charges. Matching places...");

            Artisan::call('teslog:match-places', [
                '--vehicle' => $this->vehicleId,
            ]);

            GeocodeImportData::dispatch($this->vehicleId)->onQueue('imports');
        }

        // Clean up stored files
        foreach ($this->filePaths as $path) {
            Storage::disk('local')->delete($path);
        }

        Cache::put($this->cacheKey, [
            'status' => 'complete',
            'imported' => $this->totalImported,
            'skipped' => $this->totalSkipped,
            'errors' => $this->errors,
        ], 3600);
    }

    private function updateStatus(string $step): void
    {
        Cache::put($this->cacheKey, [
            'status' => 'processing',
            'step' => $step,
            'imported' => $this->totalImported,
            'skipped' => $this->totalSkipped,
            'errors' => $this->errors,
        ], 3600);
    }

    public function failed(\Throwable $exception): void
    {
        foreach ($this->filePaths as $path) {
            Storage::disk('local')->delete($path);
        }

        $existing = Cache::get($this->cacheKey, []);

        Cache::put($this->cacheKey, [
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'imported' => $existing['imported'] ?? 0,
            'skipped' => $existing['skipped'] ?? 0,
            'errors' => $existing['errors'] ?? [],
        ], 3600);
    }
}
