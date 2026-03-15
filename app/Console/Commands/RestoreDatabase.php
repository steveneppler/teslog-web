<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RestoreDatabase extends Command
{
    protected $signature = 'teslog:restore
        {path : Path to the backup file (.sqlite or .sqlite.gz)}';

    protected $description = 'Restore the database from a backup file';

    public function handle(): int
    {
        $driver = config('database.default');

        if ($driver !== 'sqlite') {
            $this->error("Restore command only supports SQLite. Current driver: {$driver}");

            return self::FAILURE;
        }

        $backupPath = $this->argument('path');
        if (! file_exists($backupPath)) {
            $this->error("Backup file not found: {$backupPath}");

            return self::FAILURE;
        }

        $dbPath = database_path('database.sqlite');
        $isCompressed = str_ends_with($backupPath, '.gz');

        // Validate the backup before replacing
        $tempPath = $dbPath . '.restoring';

        try {
            if ($isCompressed) {
                $source = gzopen($backupPath, 'rb');
                $dest = fopen($tempPath, 'wb');

                if (! $source || ! $dest) {
                    $this->error('Failed to open files for decompression.');

                    return self::FAILURE;
                }

                while (! gzeof($source)) {
                    fwrite($dest, gzread($source, 524288));
                }

                gzclose($source);
                fclose($dest);
            } else {
                copy($backupPath, $tempPath);
            }

            // Validate it's a real SQLite database
            $header = file_get_contents($tempPath, false, null, 0, 16);
            if (! str_starts_with($header, 'SQLite format 3')) {
                unlink($tempPath);
                $this->error('Invalid backup file — not a SQLite database.');

                return self::FAILURE;
            }

            if (! $this->confirm('This will replace your current database. Are you sure?')) {
                unlink($tempPath);
                $this->info('Restore cancelled.');

                return self::SUCCESS;
            }

            // Close existing connections
            \DB::disconnect();

            // Remove WAL/SHM files if they exist
            foreach (['-wal', '-shm'] as $suffix) {
                if (file_exists($dbPath . $suffix)) {
                    unlink($dbPath . $suffix);
                }
            }

            rename($tempPath, $dbPath);

            $this->info('Database restored successfully from: ' . basename($backupPath));

            return self::SUCCESS;
        } catch (\Exception $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            $this->error('Restore failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
