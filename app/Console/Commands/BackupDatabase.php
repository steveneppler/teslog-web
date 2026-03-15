<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupDatabase extends Command
{
    protected $signature = 'teslog:backup
        {path? : Output path for the backup file}
        {--no-compress : Skip gzip compression}';

    protected $description = 'Create a backup of the database';

    public function handle(): int
    {
        $driver = config('database.default');

        if ($driver !== 'sqlite') {
            $this->error("Backup command only supports SQLite. Current driver: {$driver}");
            $this->line('For MySQL/PostgreSQL, use mysqldump or pg_dump directly.');

            return self::FAILURE;
        }

        $dbPath = database_path('database.sqlite');
        if (! file_exists($dbPath)) {
            $this->error("Database file not found at {$dbPath}");

            return self::FAILURE;
        }

        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $compress = ! $this->option('no-compress');
        $extension = $compress ? '.sqlite.gz' : '.sqlite';

        $outputPath = $this->argument('path')
            ?? $backupDir . '/teslog-backup-' . $timestamp . $extension;

        // Checkpoint WAL to ensure all data is in the main database file
        DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');

        $this->info('Creating backup...');

        if ($compress) {
            $source = fopen($dbPath, 'rb');
            $dest = gzopen($outputPath, 'wb9');

            if (! $source || ! $dest) {
                $this->error('Failed to open files for backup.');

                return self::FAILURE;
            }

            while (! feof($source)) {
                gzwrite($dest, fread($source, 524288)); // 512KB chunks
            }

            fclose($source);
            gzclose($dest);
        } else {
            copy($dbPath, $outputPath);
        }

        $size = filesize($outputPath);
        $this->info("Backup saved to: {$outputPath}");
        $this->info('Size: ' . $this->formatBytes($size));

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1024, 1) . ' KB';
    }
}
