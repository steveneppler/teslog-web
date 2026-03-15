<?php

namespace App\Console\Commands;

use App\Models\DrivePoint;
use App\Services\ElevationService;
use Illuminate\Console\Command;

class BackfillElevation extends Command
{
    protected $signature = 'teslog:backfill-elevation
        {--drive= : Specific drive ID (processes all if omitted)}
        {--force : Re-fetch even if altitude already set}';

    protected $description = 'Backfill drive point altitude from Open-Meteo Elevation API';

    public function handle(ElevationService $elevation): int
    {
        $query = DrivePoint::whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (! $this->option('force')) {
            $query->whereNull('altitude');
        }

        if ($driveId = $this->option('drive')) {
            $query->where('drive_id', $driveId);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No drive points need elevation data.');

            return self::SUCCESS;
        }

        $this->info("Backfilling elevation for {$total} drive points...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        // Process in chunks of 100 (matches Open-Meteo batch limit)
        $query->orderBy('id')->chunk(100, function ($points) use ($elevation, $bar, &$updated) {
            $coordinates = $points->map(fn ($p) => [$p->latitude, $p->longitude])->all();

            $elevations = $elevation->lookup($coordinates);

            foreach ($points as $i => $point) {
                $alt = $elevations[$i] ?? null;
                if ($alt !== null) {
                    $point->altitude = $alt;
                    $point->save();
                    $updated++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done! Updated {$updated} of {$total} points.");

        return self::SUCCESS;
    }
}
