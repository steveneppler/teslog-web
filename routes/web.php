<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\TeslaOAuthController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Redirect root to dashboard
Route::get('/', fn () => redirect()->route('dashboard'));


// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('dashboard', fn () => view('pages.dashboard'))->name('dashboard');
    Route::get('vehicles', fn () => view('pages.vehicles'))->name('web.vehicles');
    Route::get('drives', fn () => view('pages.drives'))->name('web.drives');
    Route::get('drives/{drive}', fn (\App\Models\Drive $drive) => view('pages.drive-detail', ['drive' => $drive]))->name('web.drives.show');
    Route::get('charges', fn () => view('pages.charges'))->name('web.charges');
    Route::get('charges/{charge}', fn (\App\Models\Charge $charge) => view('pages.charge-detail', ['charge' => $charge]))->name('web.charges.show');
    Route::get('map', fn () => view('pages.map'))->name('web.map');
    Route::get('places', fn () => view('pages.places'))->name('web.places');
    Route::get('vehicles/{vehicle}/health', fn (\App\Models\Vehicle $vehicle) => view('pages.vehicle-health', ['vehicle' => $vehicle]))->name('web.vehicle-health');
    Route::get('vehicles/{vehicle}/firmware', fn (\App\Models\Vehicle $vehicle) => view('pages.vehicle-firmware', ['vehicle' => $vehicle]))->name('web.vehicle-firmware');
    Route::get('vehicles/{vehicle}/commands', fn (\App\Models\Vehicle $vehicle) => view('pages.vehicle-commands', ['vehicle' => $vehicle]))->name('web.vehicle-commands');
    Route::get('debug', fn () => view('pages.debug'))->name('web.debug');
    Route::get('settings', fn () => view('pages.settings'))->name('settings');
    Route::patch('settings/theme', function (\Illuminate\Http\Request $request) {
        $request->validate(['theme' => 'nullable|in:light,dark']);
        $request->user()->update(['theme' => $request->theme ?: null]);
        return response()->json(['ok' => true]);
    })->name('settings.theme');
    Route::get('analytics', fn () => view('pages.analytics'))->name('web.analytics');
    Route::get('export/raw', [\App\Http\Controllers\Api\V1\ExportController::class, 'raw'])->name('web.export.raw');
    Route::get('export/drives', [\App\Http\Controllers\Api\V1\ExportController::class, 'drives'])->name('web.export.drives');
    Route::get('export/charges', [\App\Http\Controllers\Api\V1\ExportController::class, 'charges'])->name('web.export.charges');
    Route::get('import', fn () => view('pages.import'))->name('import');

    // Database backup download (streamed, bypasses Livewire)
    Route::get('settings/backup', function () {
        if (config('database.default') !== 'sqlite') {
            abort(404);
        }

        $dbPath = database_path('database.sqlite');
        if (! file_exists($dbPath)) {
            abort(404);
        }

        \Illuminate\Support\Facades\DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');

        $timestamp = now()->format('Y-m-d_His');
        $filename = "teslog-backup-{$timestamp}.sqlite";
        $size = filesize($dbPath);

        return response()->stream(function () use ($dbPath) {
            $stream = fopen($dbPath, 'rb');
            while (! feof($stream)) {
                echo fread($stream, 524288);
                flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/x-sqlite3',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => $size,
        ]);
    })->name('settings.backup');

    // Database restore — chunked upload
    Route::post('settings/restore/chunk', function (\Illuminate\Http\Request $request) {
        if (config('database.default') !== 'sqlite') {
            return response()->json(['error' => 'Only SQLite is supported.'], 400);
        }

        $request->validate([
            'chunk_index' => 'required|numeric|min:0',
            'total_chunks' => 'required|numeric|min:1',
            'upload_id' => 'required|string|regex:/^[a-z0-9]+$/',
        ]);

        $chunk = $request->file('chunk');
        if (! $chunk) {
            return response()->json(['error' => 'No chunk data received.'], 400);
        }

        $uploadId = $request->input('upload_id');
        $chunkIndex = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');
        $tempPath = storage_path("app/restore-{$uploadId}.part");

        try {
            // Move the uploaded chunk to a real temp file first
            // (getRealPath() can return a directory on some PHP configs)
            $chunkTmp = $tempPath . '.chunk';
            $chunk->move(storage_path('app'), basename($chunkTmp));

            $mode = $chunkIndex === 0 ? 'wb' : 'ab';
            $dest = fopen($tempPath, $mode);
            $source = fopen($chunkTmp, 'rb');
            while (! feof($source)) {
                fwrite($dest, fread($source, 524288));
            }
            fclose($source);
            fclose($dest);
            unlink($chunkTmp);

            // Not the last chunk yet
            if ($chunkIndex < $totalChunks - 1) {
                return response()->json(['status' => 'ok', 'chunk' => $chunkIndex]);
            }

            // Last chunk — validate and restore
            $isCompressed = (bool) $request->input('compressed', false);
            $dbPath = database_path('database.sqlite');
            $restorePath = database_path('database.sqlite.restoring');

            if ($isCompressed) {
                $gz = gzopen($tempPath, 'rb');
                $out = fopen($restorePath, 'wb');
                while (! gzeof($gz)) {
                    fwrite($out, gzread($gz, 524288));
                }
                gzclose($gz);
                fclose($out);
                unlink($tempPath);
            } else {
                rename($tempPath, $restorePath);
            }

            // Validate SQLite header
            $header = file_get_contents($restorePath, false, null, 0, 16);
            if (! str_starts_with($header, 'SQLite format 3')) {
                unlink($restorePath);

                return response()->json(['error' => 'Invalid backup file — not a SQLite database.'], 422);
            }

            \Illuminate\Support\Facades\DB::disconnect();

            foreach (['-wal', '-shm'] as $suffix) {
                if (file_exists($dbPath . $suffix)) {
                    unlink($dbPath . $suffix);
                }
            }

            rename($restorePath, $dbPath);

            return response()->json(['status' => 'restored']);
        } catch (\Exception $e) {
            foreach ([$tempPath, database_path('database.sqlite.restoring')] as $f) {
                if (file_exists($f)) {
                    @unlink($f);
                }
            }

            \Illuminate\Support\Facades\Log::error('Database restore failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Database restore failed. Check server logs for details.'], 500);
        }
    })->name('settings.restore.chunk');

    // Tesla OAuth & Setup
    Route::get('setup', fn () => view('pages.setup'))->name('setup');
    Route::get('auth/tesla/redirect', [TeslaOAuthController::class, 'redirect'])->name('tesla.redirect');
    Route::get('auth/tesla/callback', [TeslaOAuthController::class, 'callback'])->name('tesla.callback');
});
