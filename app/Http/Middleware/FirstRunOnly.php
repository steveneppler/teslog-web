<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FirstRunOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::count() > 0) {
            return response()->json(['error' => 'Registration is disabled'], 403);
        }

        return $next($request);
    }
}
