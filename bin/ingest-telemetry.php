#!/usr/bin/env php
<?php
/**
 * Reads fleet-telemetry JSON log lines from stdin and POSTs them to the Laravel ingest endpoint.
 * Usage: bin/fleet-telemetry -config config.json 2>&1 | php bin/ingest-telemetry.php
 */

$ingestUrl = $argv[1] ?? 'http://localhost:8080/api/telemetry/ingest';
$secret = $argv[2] ?? getenv('TESLOG_TELEMETRY_SECRET') ?: null;

if (!$secret) {
    fwrite(STDERR, "ERROR: Telemetry secret is required. Pass as argument or set TESLOG_TELEMETRY_SECRET env var.\n");
    exit(1);
}

$batch = [];
$lastFlush = time();
$batchSize = 10;
$flushInterval = 2; // seconds

fwrite(STDERR, "Telemetry ingester → {$ingestUrl}\n");

while ($line = fgets(STDIN)) {
    $line = trim($line);
    if (empty($line)) continue;

    $json = json_decode($line, true);
    if (!$json || ($json['msg'] ?? '') !== 'record_payload') continue;

    $vin = $json['vin'] ?? null;
    $data = $json['data'] ?? [];
    if (!$vin || empty($data)) continue;

    $timestamp = $data['CreatedAt'] ?? date('c');
    unset($data['CreatedAt'], $data['Vin'], $data['IsResend']);

    $fields = [];
    foreach ($data as $key => $value) {
        if ($key === 'Location' && is_array($value)) {
            if (isset($value['latitude'])) {
                $fields[] = ['key' => 'Latitude', 'value' => $value['latitude']];
            }
            if (isset($value['longitude'])) {
                $fields[] = ['key' => 'Longitude', 'value' => $value['longitude']];
            }
            continue;
        }
        $fields[] = ['key' => $key, 'value' => $value];
    }

    if (!empty($fields)) {
        $batch[] = [
            'vin' => $vin,
            'created_at' => $timestamp,
            'data' => $fields,
        ];
    }

    // Flush when batch is full or interval elapsed
    if (count($batch) >= $batchSize || (time() - $lastFlush) >= $flushInterval) {
        if (!empty($batch)) {
            flush_batch($ingestUrl, $secret, $batch);
            $batch = [];
        }
        $lastFlush = time();
    }
}

// Final flush
if (!empty($batch)) {
    flush_batch($ingestUrl, $secret, $batch);
}

function flush_batch(string $url, string $secret, array $batch): void
{
    $payload = json_encode(['data' => $batch]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            "X-Telemetry-Secret: {$secret}",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $count = count($batch);
    $fieldCount = array_sum(array_map(fn($r) => count($r['data']), $batch));
    if ($code === 200) {
        fwrite(STDERR, date('H:i:s') . " Ingested {$fieldCount} fields from {$count} records\n");
    } else {
        fwrite(STDERR, date('H:i:s') . " ERROR {$code}: {$response}\n");
    }
}
