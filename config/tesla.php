<?php

return [
    'client_id' => env('TESLA_CLIENT_ID'),
    'client_secret' => env('TESLA_CLIENT_SECRET'),
    'redirect_uri' => env('TESLA_REDIRECT_URI'),
    'audience' => env('TESLA_AUDIENCE', 'https://fleet-api.prd.na.vn.cloud.tesla.com'),
    'auth_url' => 'https://auth.tesla.com/oauth2/v3',
    'api_url' => env('TESLA_API_URL', 'https://fleet-api.prd.na.vn.cloud.tesla.com'),
    'scopes' => [
        'openid',
        'offline_access',
        'vehicle_device_data',
        'vehicle_location',
        'vehicle_cmds',
        'vehicle_charging_cmds',
    ],

    'fleet_telemetry' => [
        'hostname' => env('FLEET_TELEMETRY_HOSTNAME', 'telemetry.example.com'),
        'host' => env('FLEET_TELEMETRY_HOST', 'fleet-telemetry'),
        'port' => env('FLEET_TELEMETRY_PORT', 4443),
        'ca_cert_path' => env('FLEET_TELEMETRY_CA_CERT_PATH', base_path('docker/fleet-telemetry/certs/ca.crt')),
    ],

    'command_proxy_url' => env('TESLA_COMMAND_PROXY_URL', 'https://tesla-http-proxy:4430'),

    'command_signing' => [
        'private_key_path' => env('TESLA_PRIVATE_KEY_PATH', storage_path('app/tesla/private-key.pem')),
    ],
];
