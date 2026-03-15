<?php

namespace App\Livewire;

use App\Jobs\ProcessImportData;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;

class TeslaFiImport extends Component
{
    use WithFileUploads;

    public array $files = [];
    public string $importType = 'raw';
    public string $vehicleId = '';
    public string $timezone = '';
    public string $units = 'mi';

    public bool $processing = false;
    public string $processingStep = '';
    public string $processingKey = '';

    public bool $hasResults = false;
    public int $totalImported = 0;
    public int $totalSkipped = 0;
    public array $importErrors = [];
    public string $uploadError = '';

    public bool $geocoding = false;
    public int $geocodeDone = 0;
    public int $geocodeTotal = 0;

    public function mount()
    {
        $this->timezone = Auth::user()->timezone ?? 'America/New_York';

        // Resume tracking if there's an active import job
        $activeKey = Cache::get('import-active-' . Auth::id());
        if ($activeKey) {
            $status = Cache::get($activeKey);
            if ($status && in_array($status['status'], ['queued', 'processing'])) {
                $this->processingKey = $activeKey;
                $this->processing = true;
                $this->processingStep = $status['step'] ?? 'Processing...';
            } else {
                // Job finished while user was away — show results
                if ($status && $status['status'] === 'complete') {
                    $this->hasResults = true;
                    $this->totalImported = $status['imported'] ?? 0;
                    $this->totalSkipped = $status['skipped'] ?? 0;
                    $this->importErrors = $status['errors'] ?? [];
                    Cache::forget($activeKey);
                }
                Cache::forget('import-active-' . Auth::id());
            }
        }

        $this->checkGeocoding();
    }

    public function updatedFiles()
    {
        $this->uploadError = '';

        foreach ($this->files as $file) {
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $errors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds the server upload size limit. Try uploading smaller files or use the CLI import instead.',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds the form upload size limit.',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded. Please try again.',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
                ];
                $this->uploadError = $errors[$file->getError()] ?? 'Upload failed (error code: ' . $file->getError() . ')';
                $this->files = [];

                return;
            }
        }
    }

    public function import()
    {
        $this->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:102400|mimes:csv,txt',
            'importType' => 'required|in:raw',
            'vehicleId' => 'required|exists:vehicles,id',
            'timezone' => 'required|string',
        ]);

        $vehicle = Vehicle::where('id', $this->vehicleId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $vehicle) {
            $this->addError('vehicleId', 'Vehicle not found or does not belong to you.');

            return;
        }

        // Save uploaded files to storage so the job can access them
        $filePaths = [];
        $fileNames = [];
        foreach ($this->files as $file) {
            $fileNames[] = $file->getClientOriginalName();
            $filePaths[] = $file->store('imports', 'local');
        }

        $this->processingKey = 'import-' . Auth::id() . '-' . now()->timestamp;
        Cache::put($this->processingKey, [
            'status' => 'queued',
            'step' => 'Queued...',
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ], 3600);

        Cache::put('import-active-' . Auth::id(), $this->processingKey, 3600);

        ProcessImportData::dispatch(
            (int) $this->vehicleId,
            $this->importType,
            $this->timezone,
            $this->units,
            $filePaths,
            $fileNames,
            $this->processingKey,
        )->onQueue('imports');

        $this->files = [];
        $this->hasResults = false;
        $this->totalImported = 0;
        $this->totalSkipped = 0;
        $this->importErrors = [];
        $this->processing = true;
        $this->processingStep = 'Queued...';
    }

    public function checkProcessing()
    {
        if (! $this->processingKey) {
            return;
        }

        $status = Cache::get($this->processingKey);

        if (! $status) {
            return;
        }

        $this->processingStep = $status['step'] ?? 'Processing...';
        $this->totalImported = $status['imported'] ?? 0;
        $this->totalSkipped = $status['skipped'] ?? 0;

        if ($status['status'] === 'complete') {
            $this->processing = false;
            $this->processingStep = '';
            $this->hasResults = true;
            $this->totalImported = $status['imported'] ?? 0;
            $this->totalSkipped = $status['skipped'] ?? 0;
            $this->importErrors = $status['errors'] ?? [];
            Cache::forget($this->processingKey);
            Cache::forget('import-active-' . Auth::id());
            $this->processingKey = '';
        } elseif ($status['status'] === 'failed') {
            $this->processing = false;
            $this->processingStep = '';
            $this->hasResults = true;
            $this->totalImported = $status['imported'] ?? 0;
            $this->totalSkipped = $status['skipped'] ?? 0;
            $this->importErrors = $status['errors'] ?? [];
            $this->importErrors[] = 'Processing failed: ' . ($status['error'] ?? 'Unknown error');
            Cache::forget($this->processingKey);
            Cache::forget('import-active-' . Auth::id());
            $this->processingKey = '';
        }

        $this->checkGeocoding();
    }

    public function checkGeocoding()
    {
        $vehicles = Vehicle::where('user_id', Auth::id())->pluck('id');
        $this->geocoding = false;
        $this->geocodeDone = 0;
        $this->geocodeTotal = 0;

        foreach ($vehicles as $vehicleId) {
            $progress = Cache::get("geocode-progress-{$vehicleId}");
            if ($progress) {
                $this->geocoding = true;
                $this->geocodeDone += $progress['done'] ?? 0;
                $this->geocodeTotal += $progress['total'] ?? 0;
            }
        }
    }

    public function render()
    {
        $vehicles = Vehicle::where('user_id', Auth::id())->get();

        return view('livewire.teslafi-import', [
            'vehicles' => $vehicles,
        ]);
    }
}
