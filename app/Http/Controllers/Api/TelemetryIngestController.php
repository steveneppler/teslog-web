<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelemetryBatch;
use App\Models\TelemetryRaw;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryIngestController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $data = $request->input('data', []);

        if (empty($data)) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        $rows = [];
        $vehicleIds = [];
        $now = now();

        foreach ($data as $record) {
            $vin = $record['vin'] ?? null;
            if (! $vin) {
                continue;
            }

            $vehicle = Vehicle::where('vin', $vin)->first();
            if (! $vehicle) {
                continue;
            }

            $vehicleIds[$vehicle->id] = true;
            $timestamp = $record['created_at'] ?? $now->toIso8601String();

            foreach ($record['data'] ?? [] as $field) {
                // Tesla sends literal "null" string when a field has no value — skip it
                if (! isset($field['value']) || $field['value'] === 'null') {
                    continue;
                }

                $value = $field['value'];
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_THROW_ON_ERROR);
                } elseif (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                $rows[] = [
                    'vehicle_id' => $vehicle->id,
                    'timestamp' => $timestamp,
                    'field_name' => $field['key'] ?? '',
                    'value_numeric' => is_numeric($value) ? (float) $value : null,
                    'value_string' => ! is_numeric($value) ? (string) $value : null,
                    'processed' => false,
                    'created_at' => $now,
                ];
            }
        }

        if (! empty($rows)) {
            // Batch insert for performance
            foreach (array_chunk($rows, 500) as $chunk) {
                TelemetryRaw::insert($chunk);
            }

            // Dispatch processing for each vehicle
            foreach (array_keys($vehicleIds) as $vehicleId) {
                ProcessTelemetryBatch::dispatch($vehicleId);
            }
        }

        return response()->json([
            'received' => count($rows),
        ]);
    }
}
