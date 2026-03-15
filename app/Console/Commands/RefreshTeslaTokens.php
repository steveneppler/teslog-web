<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\TeslaAuthService;
use Illuminate\Console\Command;

class RefreshTeslaTokens extends Command
{
    protected $signature = 'teslog:refresh-tokens';

    protected $description = 'Refresh Tesla access tokens that are expiring soon';

    public function handle(TeslaAuthService $teslaAuth): int
    {
        // Find vehicles with tokens expiring in the next hour
        $vehicles = Vehicle::whereNotNull('tesla_refresh_token')
            ->where('tesla_token_expires_at', '<', now()->addHour())
            ->where('is_active', true)
            ->get();

        if ($vehicles->isEmpty()) {
            $this->info('No tokens need refreshing.');

            return self::SUCCESS;
        }

        foreach ($vehicles as $vehicle) {
            try {
                $tokens = $teslaAuth->refreshToken($vehicle->tesla_refresh_token);

                $vehicle->update([
                    'tesla_access_token' => $tokens['access_token'],
                    'tesla_refresh_token' => $tokens['refresh_token'],
                    'tesla_token_expires_at' => now()->addSeconds($tokens['expires_in']),
                ]);

                $this->info("Refreshed token for {$vehicle->name} (expires in {$tokens['expires_in']}s)");
            } catch (\Exception $e) {
                $this->error("Failed to refresh token for {$vehicle->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
