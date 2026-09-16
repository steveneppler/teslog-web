<?php

// Map basemap tiles. CARTO basemaps now require an API key
// (https://carto.com/basemaps/apikey), so the keyless Esri gray canvas is the
// default. Set TESLOG_MAP_PROVIDER, or just set TESLOG_CARTO_API_KEY to use CARTO.
$cartoApiKey = env('TESLOG_CARTO_API_KEY');
$mapProvider = env('TESLOG_MAP_PROVIDER', $cartoApiKey ? 'carto' : 'esri');

$mapProviders = [
    // Keyless. Tiles are only served up to z16, so Leaflet upscales beyond that.
    'esri' => [
        'light' => 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
        'dark' => 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}',
        'subdomains' => 'abc',
        'max_zoom' => 19,
        'max_native_zoom' => 16,
        'attribution' => '&copy; Esri, HERE, Garmin, &copy; OpenStreetMap contributors',
    ],
    // Requires TESLOG_CARTO_API_KEY.
    'carto' => [
        'light' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?api_key=' . rawurlencode((string) $cartoApiKey),
        'dark' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?api_key=' . rawurlencode((string) $cartoApiKey),
        'subdomains' => 'abcd',
        'max_zoom' => 20,
        'max_native_zoom' => 20,
        'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
    ],
    // Keyless, but has no dark variant.
    'osm' => [
        'light' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'dark' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'subdomains' => 'abc',
        'max_zoom' => 19,
        'max_native_zoom' => 19,
        'attribution' => '&copy; OpenStreetMap contributors',
    ],
];

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

    'map_tiles' => $mapProviders[$mapProvider] ?? $mapProviders['esri'],

    'telemetry' => [
        'raw_retention_days' => env('TESLOG_RAW_RETENTION_DAYS', 90),
        'state_sample_interval_seconds' => env('TESLOG_STATE_SAMPLE_INTERVAL', 30),
    ],
];
