<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FirmwareController extends Controller
{
    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        if ($vehicle->user_id !== $request->user()->id) {
            abort(403);
        }

        $history = $vehicle->firmwareHistory()
            ->orderByDesc('detected_at')
            ->get();

        return response()->json($history);
    }
}
