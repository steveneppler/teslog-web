<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('vehicle-commands', function ($request) {
            $vehicle = $request->route('vehicle');
            $vehicleId = is_object($vehicle) ? $vehicle->id : $vehicle;

            return Limit::perMinute(config('teslog.rate_limits.commands_per_vehicle', 10))
                ->by('vehicle-cmd:' . $vehicleId);
        });
    }
}
