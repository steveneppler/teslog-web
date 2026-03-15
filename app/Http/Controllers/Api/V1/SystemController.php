<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'version' => '1.0.0',
            'setup_complete' => User::count() > 0,
        ]);
    }

    public function setupStatus(): JsonResponse
    {
        $hasUser = User::count() > 0;

        return response()->json([
            'has_user' => $hasUser,
            'registration_open' => ! $hasUser,
            'tesla_configured' => ! empty(config('tesla.client_id')),
            'telemetry_configured' => ! empty(config('teslog.telemetry_secret')),
        ]);
    }
}
