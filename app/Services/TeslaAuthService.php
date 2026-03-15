<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TeslaAuthService
{
    protected string $authUrl;
    protected string $apiUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected array $scopes;

    public function __construct()
    {
        $this->authUrl = config('tesla.auth_url');
        $this->apiUrl = config('tesla.api_url');
        $this->clientId = config('tesla.client_id');
        $this->clientSecret = config('tesla.client_secret');
        $this->redirectUri = config('tesla.redirect_uri');
        $this->scopes = config('tesla.scopes');
    }

    /**
     * Build the Tesla OAuth authorization URL with PKCE.
     */
    public function getAuthorizationUrl(string $state): string
    {
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // Store code_verifier in cache keyed by state (expires in 10 minutes)
        Cache::put("tesla_pkce_{$state}", $codeVerifier, now()->addMinutes(10));

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ]);

        return "{$this->authUrl}/authorize?{$params}";
    }

    /**
     * Exchange an authorization code for access and refresh tokens.
     */
    public function exchangeCode(string $code, string $state): array
    {
        $codeVerifier = Cache::pull("tesla_pkce_{$state}");

        if (! $codeVerifier) {
            throw new \RuntimeException('PKCE code verifier not found or expired for the given state.');
        }

        $response = Http::post("{$this->authUrl}/token", [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $codeVerifier,
            'audience' => config('tesla.audience'),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Tesla token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'],
        ];
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = Http::post("{$this->authUrl}/token", [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Tesla token refresh failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'],
        ];
    }

    /**
     * Get a partner authentication token using client_credentials grant.
     */
    public function getPartnerToken(): string
    {
        $response = Http::asForm()->post("{$this->authUrl}/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => implode(' ', $this->scopes),
            'audience' => config('tesla.audience'),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to get partner token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Register this application as a partner with the Tesla Fleet API.
     * Must be called once per region before making any Fleet API calls.
     * Uses a partner token (client_credentials), not a user token.
     */
    public function registerPartner(): array
    {
        $partnerToken = $this->getPartnerToken();

        $response = Http::withToken($partnerToken)
            ->post("{$this->apiUrl}/api/1/partner_accounts", [
                'domain' => parse_url(config('app.url'), PHP_URL_HOST),
            ]);

        if ($response->failed()) {
            $body = $response->json();
            if (($body['error'] ?? '') === 'already_registered' || $response->status() === 409) {
                return ['status' => 'already_registered'];
            }
            throw new \RuntimeException('Partner registration failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * List the user's vehicles from the Tesla Fleet API.
     */
    public function getVehicles(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->apiUrl}/api/1/vehicles");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch Tesla vehicles: ' . $response->body());
        }

        return $response->json('response', []);
    }

    /**
     * Configure fleet telemetry for a vehicle.
     */
    public function configureFleetTelemetry(string $accessToken, string $vin, bool $includeLocation = true): array
    {
        $hostname = config('tesla.fleet_telemetry.hostname');
        $port = (int) config('tesla.fleet_telemetry.port');

        $fields = [
            'BatteryLevel',
            'ChargeState',
            'ChargeLimitSoc',
            'ACChargingPower',
            'DCChargingPower',
            'ChargerVoltage',
            'ChargeAmps',
            'EnergyRemaining',
            'IdealBatteryRange',
            'RatedRange',
            'VehicleSpeed',
            'Odometer',
            'Gear',
            'Location',
            'GpsHeading',
            'InsideTemp',
            'OutsideTemp',
            'Locked',
            'SentryMode',
            'HvacACEnabled',
            'Version',
        ];

        if (! $includeLocation) {
            $fields = array_filter($fields, fn ($f) => ! in_array($f, ['Location', 'GpsHeading']));
        }

        $fieldConfig = collect($fields)->mapWithKeys(function (string $field) {
            return [$field => ['interval_seconds' => 30]];
        })->toArray();

        // Read the CA certificate for mTLS
        $caPath = config('tesla.fleet_telemetry.ca_cert_path');
        if (! $caPath || ! file_exists($caPath)) {
            throw new \RuntimeException('Fleet Telemetry CA certificate not found at: ' . ($caPath ?: 'not configured'));
        }
        $caCert = file_get_contents($caPath);

        // Route through the Vehicle Command Proxy which signs the request
        $proxyUrl = config('tesla.command_proxy_url', 'https://localhost:4430');

        $response = Http::withToken($accessToken)
            ->withOptions(['verify' => false]) // Self-signed cert on localhost proxy
            ->post("{$proxyUrl}/api/1/vehicles/fleet_telemetry_config", [
                'vins' => [$vin],
                'config' => [
                    'hostname' => $hostname,
                    'port' => $port,
                    'ca' => $caCert,
                    'fields' => $fieldConfig,
                    'alert_types' => ['service'],
                    'delivery_policy' => 'latest',
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to configure fleet telemetry: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Disable fleet telemetry for a vehicle.
     */
    public function disableFleetTelemetry(string $accessToken, string $vin): array
    {
        $response = Http::withToken($accessToken)
            ->delete("{$this->apiUrl}/api/1/vehicles/{$vin}/fleet_telemetry_config");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to disable fleet telemetry: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Generate a random PKCE code verifier (128 characters, URL-safe).
     */
    protected function generateCodeVerifier(): string
    {
        return Str::random(128);
    }

    /**
     * Generate a PKCE code challenge from the verifier (S256).
     */
    protected function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }
}
