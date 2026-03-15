<?php

return [
    'telemetry_secret' => env('TESLOG_TELEMETRY_SECRET'),
    'horizon_enabled' => env('TESLOG_HORIZON_ENABLED', true),
    'setup_complete' => env('TESLOG_SETUP_COMPLETE', false),

    'defaults' => [
        'distance_unit' => env('TESLOG_DISTANCE_UNIT', 'mi'),
        'temperature_unit' => env('TESLOG_TEMPERATURE_UNIT', 'F'),
        'currency' => env('TESLOG_CURRENCY', 'USD'),
        'timezone' => env('TESLOG_TIMEZONE', 'UTC'),
    ],

    'geocoding' => [
        'provider' => 'nominatim',
        'user_agent' => env('TESLOG_GEOCODING_USER_AGENT', 'Teslog/1.0'),
        'rate_limit_ms' => 1000,
    ],

    'weather' => [
        'provider' => 'open-meteo',
    ],

    'rate_limits' => [
        'api' => env('TESLOG_API_RATE_LIMIT', 60),
        'commands_per_vehicle' => env('TESLOG_COMMAND_RATE_LIMIT', 10),
    ],

    'telemetry' => [
        'raw_retention_days' => env('TESLOG_RAW_RETENTION_DAYS', 90),
        'state_sample_interval_seconds' => env('TESLOG_STATE_SAMPLE_INTERVAL', 30),
    ],
];
