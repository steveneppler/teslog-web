<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Services\ChargeCostService;
use Illuminate\Console\Command;

class BackfillChargeCosts extends Command
{
    protected $signature = 'teslog:backfill-charge-costs
        {--vehicle= : Specific vehicle ID}
        {--force : Recalculate even if cost is already set}';

    protected $description = 'Calculate charge costs from place electricity rates (flat or ToU)';

    public function handle(ChargeCostService $chargeCost): int
    {
        $query = Charge::whereNotNull('place_id')
            ->whereNotNull('energy_added_kwh')
            ->where('energy_added_kwh', '>', 0);

        if (! $this->option('force')) {
            $query->whereNull('cost');
        }

        if ($vehicleId = $this->option('vehicle')) {
            $query->where('vehicle_id', $vehicleId);
        }

        $total = $query->count();
        $this->info("Calculating costs for {$total} charges...");

        $updated = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->with('place.touRates', 'vehicle.user')->chunkById(100, function ($charges) use ($chargeCost, &$updated, &$skipped, $bar) {
            foreach ($charges as $charge) {
                $userTz = $charge->vehicle?->user?->timezone ?? 'UTC';
                $cost = $chargeCost->calculateCost($charge, $userTz);

                if ($cost !== null) {
                    $updated++;
                } else {
                    $skipped++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated {$updated} charges, skipped {$skipped} (no pricing configured).");

        return self::SUCCESS;
    }
}
