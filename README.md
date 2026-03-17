# Teslog

A self-hosted Tesla vehicle data logging and analytics platform. Teslog captures real-time telemetry from your Tesla via Fleet Telemetry streaming, logs drives, charges, and idle sessions, and presents everything in a clean web dashboard.

## Getting Started

You'll need:

- Docker and Docker Compose
- A domain name with DNS pointed to your server
- A [Tesla Developer Account](https://developer.tesla.com/) with a registered application

### 1. Tesla Developer App

1. Go to [developer.tesla.com](https://developer.tesla.com/) and sign in
2. Create a new application with these settings:
   - **Allowed Origin:** `https://yourdomain.com` (your Teslog URL, no trailing slash)
   - **Allowed Redirect URI:** `https://yourdomain.com/auth/tesla/callback`
3. Note the **Client ID** and **Client Secret** — you'll need these for `.env`

> **Tip:** The app hostname and fleet telemetry hostname can be the same domain. Fleet telemetry uses port 4443 with its own mTLS, so there is no conflict with the web app on port 443.

> **Warning:** If you run multiple Teslog instances (e.g., production + demo), each must use its own Tesla Developer App with a separate Client ID. Partner registration is per Client ID — running `--register-partner` from a second instance will overwrite the first, breaking fleet telemetry on the original instance.

### 2. Clone and configure

```bash
git clone https://github.com/steveneppler/teslog-web.git
cd teslog-web
cp .env.example .env
```

Generate your encryption key and Reverb secret:

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "REVERB_APP_SECRET=$(openssl rand -base64 32)" >> .env
```

> **Important:** `APP_KEY` encrypts Tesla OAuth tokens. If you lose it, tokens become unrecoverable and you'll need to re-authenticate. Back it up.

Edit `.env` with your settings:

```env
APP_URL=https://yourdomain.com
APP_ENV=production
APP_DEBUG=false

# Tesla Developer App credentials (from developer.tesla.com)
TESLA_CLIENT_ID=your-client-id
TESLA_CLIENT_SECRET=your-client-secret

# Fleet Telemetry — public hostname your vehicle connects to
# Can be the same as your app hostname (uses port 4443, not 443)
FLEET_TELEMETRY_HOSTNAME=yourdomain.com

# Frontend WebSocket — must be just the hostname (no https://)
VITE_REVERB_HOST=yourdomain.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

> **Note:** `TESLA_REDIRECT_URI` defaults to `${APP_URL}/auth/tesla/callback` — no need to set it explicitly unless your callback URL differs from your `APP_URL`.

### 3. Start the containers

```bash
docker compose up -d
```

On first start, the app entrypoint automatically:
- Generates Vehicle Command Proxy key pairs (fleet key + TLS key) and publishes the public key to `.well-known`
- Generates Fleet Telemetry mTLS certificates and config
- Creates the SQLite database and runs migrations
- Caches config, routes, and views in production
- Starts PHP-FPM, Nginx, Horizon (queue), scheduler, Reverb, and MQTT subscriber

All generated keys and certificates persist in Docker volumes across restarts.

### 4. Set up a reverse proxy

A reverse proxy with TLS termination is required in front of the app container. WebSocket connections to `/app` are proxied internally by the app container's Nginx to Reverb — no separate port or location block needed.

Fleet Telemetry listens on port 4443 with its own mTLS certificates. It does **not** go through the reverse proxy — just ensure port 4443 is open in your firewall.

#### Caddy (recommended)

[Caddy](https://caddyserver.com/) is the simplest option — it handles TLS certificates automatically via Let's Encrypt.

```bash
# Install Caddy (Debian/Ubuntu)
apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt update && apt install caddy
```

Create `/etc/caddy/Caddyfile`:

```
yourdomain.com {
    reverse_proxy localhost:8080
}
```

```bash
systemctl restart caddy
```

> **Note:** Ports 80 and 443 must be open for Caddy's ACME challenge and HTTPS. Port 4443 must be open for Fleet Telemetry.

#### Nginx (alternative)

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

### 5. Create your account

Visit your Teslog URL and register. Registration is automatically locked after the first user is created.

### 6. Connect your Tesla

1. Go to **Settings** in the web dashboard
2. Click **Connect Tesla Account**
3. Sign in with your Tesla account and authorize Teslog
4. Select your vehicles — Teslog will automatically register as a Fleet API partner and configure telemetry streaming

### 7. Pair your public key with your vehicle

Tesla requires your app's public key to be paired with each vehicle. The setup wizard shows this link after connecting, but you can also do it manually:

1. Verify your public key is accessible at `https://yourdomain.com/.well-known/appspecific/com.tesla.3p.public-key.pem`
2. On your phone (near the vehicle), open: `https://tesla.com/_ak/yourdomain.com`
3. The Tesla app will prompt you to approve the key — tap **Approve** on the vehicle's touchscreen

> **Note:** If you see a `missing_key` error during setup, complete this step first, then re-connect your Tesla account in Settings to retry telemetry configuration.

## Features

- **Real-time dashboard** — Live vehicle status with battery, range, temperature, lock state, and sentry mode via WebSocket
- **Drive logging** — Automatic trip detection with route maps, speed/elevation charts, efficiency stats, and GPS breadcrumbs
- **Charge logging** — Charge curve visualization, energy tracking, cost calculation with time-of-use rates
- **Idle/sleep tracking** — Vampire drain monitoring and sentry mode usage
- **Places** — Named locations with geofence matching, auto-tagging drives and charges
- **Vehicle commands** — Lock/unlock, HVAC, charge control, sentry mode, and more via the Tesla Vehicle Command Proxy
- **Week-based drive history** — Browse drives by week with daily groupings, summary stats, and route maps
- **TeslaFi import** — Migrate historical data from TeslaFi CSV exports
- **Temperature & unit preferences** — Fahrenheit/Celsius, miles/kilometers per user
- **Theme switching** — Auto (follows OS), Light, and Dark modes
- **Mobile-friendly** — Responsive layout with slide-in sidebar navigation
- **Companion iOS app** — Native SwiftUI app for on-the-go monitoring ([separate repo](../teslog-app))

## Architecture

```
Tesla Vehicle ──(mTLS WebSocket)──► Fleet Telemetry container
                                        │
                                   MQTT (reliable ack)
                                        │
                                        ▼
                                   Mosquitto broker
                                        │
                                        ▼
                                   Laravel app (MQTT subscriber) ──► Horizon (queue) ──► Database
                                     │    │                                             │
                              Livewire    REST API + WebSocket                          │
                            web dashboard        │                                      │
                                          iOS/macOS companion app ◄─────────────────────┘
```

Five containers:

| Container | Role |
|-----------|------|
| **app** | PHP-FPM, Nginx, Horizon (queue), scheduler, Reverb, MQTT subscriber |
| **fleet-telemetry** | Tesla's Go binary, receives vehicle telemetry via mTLS, publishes to MQTT |
| **mosquitto** | Eclipse Mosquitto MQTT broker for reliable telemetry delivery |
| **tesla-http-proxy** | Tesla Vehicle Command Proxy for signed vehicle commands |
| **redis** | Queue broker and cache |

Fleet Telemetry publishes decoded vehicle data to the Mosquitto MQTT broker with reliable acks enabled. The vehicle only marks a message as delivered after fleet-telemetry confirms the MQTT publish succeeded, providing at-least-once delivery with a ~5000 message buffer on the vehicle side.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Livewire 4, Blade, Tailwind CSS
- **Charts:** Chart.js
- **Maps:** Leaflet.js with CartoDB tiles (theme-aware)
- **Real-time:** Laravel Reverb (WebSocket)
- **Database:** SQLite (default), MySQL/PostgreSQL optional
- **Queue/Cache:** Redis
- **Telemetry:** Tesla Fleet Telemetry (Go binary)
- **Auth:** Laravel Sanctum

## Environment Variables

### Required

| Variable | Description |
|----------|-------------|
| `APP_URL` | Public URL of your Teslog instance |
| `APP_KEY` | Laravel encryption key (see step 2 for generation) |
| `TESLA_CLIENT_ID` | Tesla Developer app client ID |
| `TESLA_CLIENT_SECRET` | Tesla Developer app client secret |
| `TESLA_REDIRECT_URI` | OAuth callback URL (defaults to `{APP_URL}/auth/tesla/callback`) |
| `FLEET_TELEMETRY_HOSTNAME` | Public hostname for Fleet Telemetry — can match your app hostname or use a subdomain (e.g., `telemetry.yourdomain.com`) |

### Optional

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_PORT` | `8080` | Host port for the web app |
| `FLEET_TELEMETRY_PORT` | `4443` | Host port for Fleet Telemetry |
| `DB_CONNECTION` | `sqlite` | Database driver (`sqlite`, `mysql`, `pgsql`) |
| `TESLOG_DISTANCE_UNIT` | `mi` | Default distance unit (`mi` or `km`) |
| `TESLOG_TEMPERATURE_UNIT` | `F` | Default temperature unit (`F` or `C`) |
| `TESLOG_CURRENCY` | `USD` | Default currency for cost tracking |
| `TESLOG_TIMEZONE` | `UTC` | Default timezone |
| `TESLOG_API_RATE_LIMIT` | `60` | API requests per minute |
| `TESLOG_COMMAND_RATE_LIMIT` | `10` | Vehicle commands per minute per vehicle |
| `TESLOG_RAW_RETENTION_DAYS` | `90` | Days to retain raw telemetry data |

## Artisan Commands

| Command | Description |
|---------|-------------|
| `teslog:fleet-telemetry` | Register as Tesla partner and configure vehicle telemetry streaming |
| `teslog:mqtt-subscribe` | Subscribe to MQTT broker for telemetry ingestion (runs under Supervisor) |
| `teslog:process-states` | Process vehicle states into drives, charges, and idles |
| `teslog:check-health` | Check telemetry pipeline health and auto-fix issues |
| `teslog:geocode` | Geocode missing addresses on drives and charges (with Place matching and coordinate cache) |
| `teslog:match-places` | Match existing drives and charges to saved places |
| `teslog:teslafi-import` | Import historical data from TeslaFi CSV exports |
| `teslog:tail-telemetry` | Tail Fleet Telemetry logs for debugging |
| `teslog:record-battery-health` | Record daily battery health snapshots for active vehicles (scheduled at 3 AM) |
| `teslog:backfill-battery-health` | Backfill battery health from historical vehicle states (>=70% SOC). Use `--vehicle=ID` to target one vehicle |
| `teslog:backfill-firmware-history` | Backfill firmware history from software_version changes in vehicle states. Use `--vehicle=ID` to target one vehicle |
| `teslog:backfill-efficiency` | Backfill energy_used_kwh and efficiency for drives missing these values (e.g. TeslaFi imports) |
| `teslog:backfill-elevation` | Backfill elevation data for drive points missing altitude |
| `teslog:backfill-charge-costs` | Calculate charge costs from place electricity rates (flat or ToU). Use `--force` to recalculate |
| `teslog:backfill-charge-stats` | Backfill charge stats (energy used, voltage, current) from vehicle states |
| `teslog:refresh-tokens` | Refresh expiring Tesla OAuth tokens (runs hourly via scheduler) |
| `teslog:backup` | Create a compressed database backup |
| `teslog:restore {path}` | Restore from a backup file |

## Importing from TeslaFi

If you're migrating from TeslaFi, export your raw data as CSV files and import via the web UI (**Settings > Import**) or CLI:

```bash
docker compose exec app php artisan teslog:teslafi-import raw /path/to/teslafi-raw.csv \
  --vehicle=1 --timezone=America/Chicago
```

Teslog will import the raw vehicle states and then automatically process them into drives and charges.

## Local Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Start all dev services concurrently
composer dev
```

This runs the Laravel dev server, queue worker, Pail log viewer, and Vite dev server.

### Fleet Telemetry (local)

To receive live vehicle telemetry locally, you need the Fleet Telemetry binary running alongside the Laravel app.

**1. Download the binary**

Download the `fleet-telemetry` binary for your platform from the [Tesla Fleet Telemetry releases](https://github.com/teslamotors/fleet-telemetry/releases) and place it at `bin/fleet-telemetry`:

```bash
chmod +x bin/fleet-telemetry
```

**2. Generate TLS certificates**

Fleet Telemetry requires mTLS. Generate a self-signed CA and server certificate:

```bash
mkdir -p docker/fleet-telemetry/certs
cd docker/fleet-telemetry/certs

# CA key and cert
openssl ecparam -name prime256v1 -genkey -noout -out ca.key
openssl req -new -x509 -key ca.key -out ca.crt -days 3650 -subj "/CN=Teslog CA"

# Server key and cert (use your telemetry hostname)
openssl ecparam -name prime256v1 -genkey -noout -out server.key
openssl req -new -key server.key -out server.csr -subj "/CN=telemetry.yourdomain.com"
openssl x509 -req -in server.csr -CA ca.crt -CAkey ca.key -CAcreateserial \
  -out server.crt -days 825 -sha256
rm server.csr
```

**3. Configure**

Copy and edit the config:

```bash
cp docker/fleet-telemetry/config.json.example docker/fleet-telemetry/config.json
```

Update `config.json` with absolute paths to the certs. For local development you can use the logger dispatcher with the pipe-based ingestion script, or use MQTT with a local Mosquitto instance:

**Option A: MQTT (recommended, matches production)**

Install Mosquitto locally (`brew install mosquitto` / `apt install mosquitto`) and run it on port 1883, then configure:

```json
{
  "tls": {
    "server_cert": "/full/path/to/docker/fleet-telemetry/certs/server.crt",
    "server_key": "/full/path/to/docker/fleet-telemetry/certs/server.key"
  },
  "reliable_ack": true,
  "transmit_decoded_records": true,
  "reliable_ack_sources": { "V": "mqtt" },
  "records": {
    "alerts": ["logger"],
    "errors": ["logger"],
    "V": ["mqtt"]
  },
  "dispatchers": {
    "mqtt": {
      "broker": "localhost:1883",
      "client_id": "fleet-telemetry",
      "topic_base": "teslog",
      "qos": 1,
      "publish_timeout_ms": 5000
    }
  }
}
```

Then start the MQTT subscriber alongside the dev server:

```bash
php artisan teslog:mqtt-subscribe --broker=localhost:1883
```

**Option B: Logger pipe (fallback)**

```json
{
  "tls": {
    "server_cert": "/full/path/to/docker/fleet-telemetry/certs/server.crt",
    "server_key": "/full/path/to/docker/fleet-telemetry/certs/server.key"
  },
  "records": {
    "alerts": ["logger"],
    "errors": ["logger"],
    "V": ["logger"]
  },
  "dispatchers": {}
}
```

**4. Start Fleet Telemetry**

```bash
bin/start-telemetry.sh
```

With the logger option, this pipes fleet-telemetry stdout through `bin/ingest-telemetry.php`, which batches and POSTs records to `http://localhost:8000/api/telemetry/ingest`.

**5. Register with Tesla**

Your telemetry hostname (e.g., `telemetry.yourdomain.com:4443`) must be publicly accessible with valid TLS. Once it's reachable:

```bash
php artisan teslog:fleet-telemetry --register-partner
php artisan teslog:fleet-telemetry
```

## Security

- **Single-user lock** — Registration is disabled after the first account is created
- **Encrypted tokens** — Tesla OAuth tokens are encrypted at rest
- **Command signing** — EC private key stored on the filesystem, never exposed via API
- **Telemetry isolation** — MQTT broker is container-to-container only, not exposed to the host network
- **WebSocket auth** — `REVERB_APP_SECRET` signs server-side broadcast requests (generate a random value for production)
- **Rate limiting** — Configurable per-API and per-vehicle command limits

## License

[MIT](LICENSE)
