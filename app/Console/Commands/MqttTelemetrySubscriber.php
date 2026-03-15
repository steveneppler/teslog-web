<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTelemetryBatch;
use App\Models\TelemetryRaw;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttTelemetrySubscriber extends Command
{
    protected $signature = 'teslog:mqtt-subscribe
        {--broker=mosquitto:1883 : MQTT broker host:port}
        {--topic=teslog/+/v/# : MQTT topic to subscribe to}';

    protected $description = 'Subscribe to MQTT broker for telemetry ingestion (runs under Supervisor)';

    private array $batch = [];
    private float $lastFlush;
    private int $batchSize = 10;
    private int $flushInterval = 2;

    public function handle(): int
    {
        $brokerParts = explode(':', $this->option('broker'));
        $host = $brokerParts[0];
        $port = (int) ($brokerParts[1] ?? 1883);
        $topic = $this->option('topic');

        $this->info("Connecting to MQTT broker at {$host}:{$port}...");

        $client = new MqttClient($host, $port, 'teslog-subscriber-' . getmypid());

        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval(60)
            ->setConnectTimeout(10)
            ->setReconnectAutomatically(true);

        $client->connect($settings);
        $this->info("Connected. Subscribing to {$topic}...");

        $this->lastFlush = microtime(true);

        $client->subscribe($topic, function (string $topic, string $message) {
            $this->processMessage($topic, $message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $this->info('Listening for telemetry messages...');

        $client->registerLoopEventHandler(function () {
            $elapsed = microtime(true) - $this->lastFlush;
            if (! empty($this->batch) && $elapsed >= $this->flushInterval) {
                $this->flush();
            }
        });

        $client->loop(true);

        return self::SUCCESS;
    }

    /**
     * Process a single MQTT message.
     *
     * Topic format: teslog/{VIN}/v/{FieldName}
     * Payload: raw value (e.g., "63.7", "true", "SentryModeStateOff")
     * Location is special: teslog/{VIN}/v/Location with JSON payload {"latitude":39.0,"longitude":-108.5}
     */
    private function processMessage(string $topic, string $message): void
    {
        // Parse topic: teslog/{VIN}/v/{FieldName}
        $parts = explode('/', $topic);
        if (count($parts) < 4 || $parts[2] !== 'v') {
            return;
        }

        $vin = $parts[1];
        $fieldName = $parts[3];
        $timestamp = now()->toIso8601String();

        // Location arrives as JSON with latitude/longitude
        if ($fieldName === 'Location') {
            $location = json_decode($message, true);
            if (is_array($location)) {
                if (isset($location['latitude'])) {
                    $this->batch[] = [
                        'vin' => $vin,
                        'timestamp' => $timestamp,
                        'field_name' => 'Latitude',
                        'value' => $location['latitude'],
                    ];
                }
                if (isset($location['longitude'])) {
                    $this->batch[] = [
                        'vin' => $vin,
                        'timestamp' => $timestamp,
                        'field_name' => 'Longitude',
                        'value' => $location['longitude'],
                    ];
                }
            }
        } else {
            // MQTT payloads may arrive JSON-encoded (e.g., quoted strings like "\"2024.44.25\"")
            $decoded = json_decode($message);
            $value = (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) ? $decoded : $message;

            $this->batch[] = [
                'vin' => $vin,
                'timestamp' => $timestamp,
                'field_name' => $fieldName,
                'value' => $value,
            ];
        }

        if (count($this->batch) >= $this->batchSize) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        if (empty($this->batch)) {
            return;
        }

        $rows = [];
        $vehicleIds = [];
        $now = now();

        $vins = array_unique(array_column($this->batch, 'vin'));
        $vehicles = Vehicle::whereIn('vin', $vins)->pluck('id', 'vin');

        foreach ($this->batch as $field) {
            $vehicleId = $vehicles[$field['vin']] ?? null;
            if (! $vehicleId) {
                continue;
            }

            $vehicleIds[$vehicleId] = true;
            $value = $field['value'];

            $rows[] = [
                'vehicle_id' => $vehicleId,
                'timestamp' => $field['timestamp'],
                'field_name' => $field['field_name'],
                'value_numeric' => is_numeric($value) ? (float) $value : null,
                'value_string' => ! is_numeric($value) ? (string) $value : null,
                'processed' => false,
                'created_at' => $now,
            ];
        }

        if (! empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                TelemetryRaw::insert($chunk);
            }

            foreach (array_keys($vehicleIds) as $vehicleId) {
                ProcessTelemetryBatch::dispatch($vehicleId);
            }

            $fieldCount = count($rows);
            $this->line(date('H:i:s') . " Ingested {$fieldCount} fields");
        }

        $this->batch = [];
        $this->lastFlush = microtime(true);
    }
}
