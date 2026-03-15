<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TeslogSetup extends Command
{
    protected $signature = 'teslog:setup';

    protected $description = 'Interactive setup wizard for Teslog';

    public function handle(): int
    {
        $this->displayWelcome();

        if (! $this->setupTeslaDeveloperAccount()) {
            return Command::FAILURE;
        }

        if (! $this->setupAppUrl()) {
            return Command::FAILURE;
        }

        if (! $this->setupCommandSigningKey()) {
            return Command::FAILURE;
        }

        if (! $this->setupFleetTelemetry()) {
            return Command::FAILURE;
        }

        $this->setEnvValue('TESLOG_SETUP_COMPLETE', 'true');

        $this->displaySummary();

        return Command::SUCCESS;
    }

    protected function displayWelcome(): void
    {
        $this->newLine();
        $this->line('  ______          __            ');
        $this->line(' /_  __/__  _____/ /___  ____ _ ');
        $this->line('  / / / _ \\/ ___/ / __ \\/ __ `/ ');
        $this->line(' / / /  __(__  ) / /_/ / /_/ /  ');
        $this->line('/_/  \\___/____/_/\\____/\\__, /   ');
        $this->line('                      /____/    ');
        $this->newLine();
        $this->info('Teslog Setup Wizard');
        $this->line('Self-hosted Tesla vehicle data logging and fleet telemetry.');
        $this->newLine();
        $this->line('This wizard will configure:');
        $this->line('  1. Tesla Developer API credentials');
        $this->line('  2. Application URL');
        $this->line('  3. Command signing key pair (EC P-256)');
        $this->line('  4. Fleet Telemetry connection');
        $this->newLine();
        $this->line('You will need a Tesla Developer account: <href=https://developer.tesla.com>https://developer.tesla.com</>');
        $this->newLine();
    }

    protected function setupTeslaDeveloperAccount(): bool
    {
        $this->info('Step 1: Tesla Developer Credentials');
        $this->line('Enter the Client ID and Client Secret from your Tesla Developer app.');
        $this->newLine();

        $clientId = $this->ask('Tesla Client ID');
        if (empty($clientId)) {
            $this->error('Client ID is required.');

            return false;
        }

        $clientSecret = $this->secret('Tesla Client Secret');
        if (empty($clientSecret)) {
            $this->error('Client Secret is required.');

            return false;
        }

        $this->setEnvValue('TESLA_CLIENT_ID', $clientId);
        $this->setEnvValue('TESLA_CLIENT_SECRET', $clientSecret);

        $this->info('Tesla credentials saved.');
        $this->newLine();

        return true;
    }

    protected function setupAppUrl(): bool
    {
        $this->info('Step 2: Application URL');
        $this->line('The public URL where Teslog is hosted. Tesla will redirect here after authentication.');
        $this->newLine();

        $currentUrl = env('APP_URL', 'http://localhost');
        $url = $this->ask('Public URL', $currentUrl);

        if (empty($url)) {
            $this->error('Application URL is required.');

            return false;
        }

        $url = rtrim($url, '/');
        $redirectUri = $url.'/auth/tesla/callback';

        $this->setEnvValue('APP_URL', $url);
        $this->setEnvValue('TESLA_REDIRECT_URI', $redirectUri);

        $this->info("App URL set to: {$url}");
        $this->line("OAuth redirect URI: {$redirectUri}");
        $this->newLine();

        return true;
    }

    protected function setupCommandSigningKey(): bool
    {
        $this->info('Step 3: Command Signing Key');
        $this->line('An EC P-256 key pair is required for signing vehicle commands.');
        $this->newLine();

        $privateKeyPath = storage_path('app/tesla/private-key.pem');
        $publicKeyPath = storage_path('app/tesla/public-key.pem');
        $wellKnownPath = public_path('.well-known/appspecific/com.tesla.3p.public-key.pem');

        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            if (! $this->confirm('Key pair already exists. Regenerate?', false)) {
                $this->line('Keeping existing key pair.');
                $this->setEnvValue('TESLA_PRIVATE_KEY_PATH', $privateKeyPath);
                $this->newLine();

                return true;
            }
        }

        // Ensure directories exist
        $this->ensureDirectoryExists(dirname($privateKeyPath));
        $this->ensureDirectoryExists(dirname($wellKnownPath));

        // Generate EC private key
        $privateKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($privateKey === false) {
            $this->error('Failed to generate EC key pair. Ensure the OpenSSL extension is available.');

            return false;
        }

        // Export private key
        if (! openssl_pkey_export($privateKey, $privateKeyPem)) {
            $this->error('Failed to export private key.');

            return false;
        }

        // Export public key
        $keyDetails = openssl_pkey_get_details($privateKey);
        if ($keyDetails === false) {
            $this->error('Failed to extract public key details.');

            return false;
        }
        $publicKeyPem = $keyDetails['key'];

        // Write key files
        file_put_contents($privateKeyPath, $privateKeyPem);
        chmod($privateKeyPath, 0600);

        file_put_contents($publicKeyPath, $publicKeyPem);
        chmod($publicKeyPath, 0644);

        // Copy public key to .well-known location for Tesla
        file_put_contents($wellKnownPath, $publicKeyPem);
        chmod($wellKnownPath, 0644);

        $this->setEnvValue('TESLA_PRIVATE_KEY_PATH', $privateKeyPath);

        $this->info('Key pair generated:');
        $this->line("  Private key: {$privateKeyPath}");
        $this->line("  Public key:  {$publicKeyPath}");
        $this->line("  Well-known:  {$wellKnownPath}");
        $this->newLine();

        return true;
    }

    protected function setupFleetTelemetry(): bool
    {
        $this->info('Step 4: Fleet Telemetry');
        $this->line('Fleet Telemetry receives real-time data streams from your vehicles.');
        $this->newLine();

        $currentHost = env('FLEET_TELEMETRY_HOST', 'fleet-telemetry');
        $hostname = $this->ask('Fleet Telemetry public hostname (the domain vehicles connect to)', $currentHost);

        if (empty($hostname)) {
            $this->error('Fleet Telemetry hostname is required.');

            return false;
        }

        $this->setEnvValue('FLEET_TELEMETRY_HOST', $hostname);

        // Generate TLS certificates if they don't exist
        $certPath = storage_path('app/tesla/fleet-telemetry.crt');
        $keyPath = storage_path('app/tesla/fleet-telemetry.key');

        if (! file_exists($certPath) || ! file_exists($keyPath)) {
            if ($this->confirm('Generate self-signed TLS certificate for Fleet Telemetry?', true)) {
                $this->ensureDirectoryExists(dirname($certPath));

                $dn = [
                    'commonName' => $hostname,
                ];

                $config = [
                    'private_key_type' => OPENSSL_KEYTYPE_RSA,
                    'private_key_bits' => 2048,
                ];

                $tlsKey = openssl_pkey_new($config);
                if ($tlsKey === false) {
                    $this->error('Failed to generate TLS private key.');

                    return false;
                }

                $csr = openssl_csr_new($dn, $tlsKey, ['digest_alg' => 'sha256']);
                if ($csr === false) {
                    $this->error('Failed to generate certificate signing request.');

                    return false;
                }

                $cert = openssl_csr_sign($csr, null, $tlsKey, 365, ['digest_alg' => 'sha256']);
                if ($cert === false) {
                    $this->error('Failed to sign certificate.');

                    return false;
                }

                openssl_x509_export($cert, $certPem);
                openssl_pkey_export($tlsKey, $tlsKeyPem);

                file_put_contents($certPath, $certPem);
                chmod($certPath, 0644);

                file_put_contents($keyPath, $tlsKeyPem);
                chmod($keyPath, 0600);

                $this->info('Self-signed TLS certificate generated.');
                $this->warn('For production, replace with a real certificate (e.g., Let\'s Encrypt).');
                $this->line("  Certificate: {$certPath}");
                $this->line("  Private key: {$keyPath}");
            }
        } else {
            $this->line('TLS certificates already exist, skipping generation.');
        }

        // Generate telemetry secret if not set
        $currentSecret = env('TESLOG_TELEMETRY_SECRET');
        if (empty($currentSecret)) {
            $secret = Str::random(64);
            $this->setEnvValue('TESLOG_TELEMETRY_SECRET', $secret);
            $this->info('Telemetry secret generated.');
        } else {
            $this->line('Telemetry secret already configured, keeping existing value.');
        }

        $this->newLine();

        return true;
    }

    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info('Setup Complete!');
        $this->newLine();

        $this->table(
            ['Setting', 'Value'],
            [
                ['App URL', env('APP_URL', $this->getEnvValue('APP_URL'))],
                ['Tesla Client ID', $this->maskValue($this->getEnvValue('TESLA_CLIENT_ID'))],
                ['Tesla Client Secret', '********'],
                ['OAuth Redirect URI', $this->getEnvValue('TESLA_REDIRECT_URI')],
                ['Private Key Path', $this->getEnvValue('TESLA_PRIVATE_KEY_PATH')],
                ['Fleet Telemetry Host', $this->getEnvValue('FLEET_TELEMETRY_HOST')],
                ['Fleet Telemetry Port', $this->getEnvValue('FLEET_TELEMETRY_PORT') ?: '4443'],
                ['Telemetry Secret', '********'],
            ]
        );

        $this->newLine();
        $this->warn('Next steps:');
        $this->line('  1. Register your public key domain at <href=https://developer.tesla.com>https://developer.tesla.com</>');
        $this->line('     Your public key is hosted at: {APP_URL}/.well-known/appspecific/com.tesla.3p.public-key.pem');
        $this->line('  2. Set up DNS for your Fleet Telemetry hostname to point to this server');
        $this->line('  3. Run <comment>docker compose up</comment> to start all services');
        $this->newLine();
    }

    /**
     * Set a value in the .env file, replacing if it exists or appending if it doesn't.
     */
    protected function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $contents = file_get_contents($envPath);

        // Quote values that contain spaces or special characters
        $escapedValue = $value;
        if (preg_match('/[\s#"\'\\\\]/', $value) || $value === '') {
            $escapedValue = '"'.addcslashes($value, '"\\').'"';
        }

        $pattern = '/^'.preg_quote($key, '/').'=.*/m';

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, "{$key}={$escapedValue}", $contents);
        } else {
            $contents = rtrim($contents, "\n")."\n{$key}={$escapedValue}\n";
        }

        file_put_contents($envPath, $contents);
    }

    /**
     * Get a value from the .env file directly (not from config cache).
     */
    protected function getEnvValue(string $key): string
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return '';
        }

        $contents = file_get_contents($envPath);
        $pattern = '/^'.preg_quote($key, '/').'=(.*)$/m';

        if (preg_match($pattern, $contents, $matches)) {
            $value = trim($matches[1]);

            // Remove surrounding quotes
            if (preg_match('/^"(.*)"$/', $value, $quoted)) {
                return stripcslashes($quoted[1]);
            }
            if (preg_match("/^'(.*)'$/", $value, $quoted)) {
                return $quoted[1];
            }

            return $value;
        }

        return '';
    }

    protected function maskValue(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4).'...'.substr($value, -4);
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
