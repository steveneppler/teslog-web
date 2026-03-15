<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Sentinel\Sentinel;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function ($request) {
            if (! config('teslog.horizon_enabled', true)) {
                return false;
            }

            return app()->environment('local') || $request->user() !== null;
        });

        Sentinel::extend('horizon', function () {
            return new class(fn () => app()) extends \Laravel\Sentinel\Drivers\Driver {
                public function authorize(\Illuminate\Http\Request $request): bool
                {
                    return true;
                }
            };
        });
    }
}
