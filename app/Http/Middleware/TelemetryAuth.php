<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelemetryAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('teslog.telemetry_secret');

        if (! $secret || $request->header('X-Telemetry-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
