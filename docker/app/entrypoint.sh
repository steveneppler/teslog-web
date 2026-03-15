#!/bin/sh
set -e

# ---------- Auto-generate secrets and keys if missing ----------

# Require APP_KEY — encrypted data (Tesla tokens) is unrecoverable without it
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "ERROR: APP_KEY is not set."
    echo "Generate one and add it to your .env file:"
    echo ""
    echo "  echo \"APP_KEY=base64:\$(openssl rand -base64 32)\" >> .env"
    echo ""
    exit 1
fi

# Generate TESLOG_TELEMETRY_SECRET if not set
if [ -z "$TESLOG_TELEMETRY_SECRET" ]; then
    TESLOG_TELEMETRY_SECRET=$(head -c 32 /dev/urandom | base64 | tr -d '/+=' | head -c 32)
    export TESLOG_TELEMETRY_SECRET
    echo "Generated TESLOG_TELEMETRY_SECRET (set in env to persist across restarts)"
fi

# ---------- Vehicle Command Proxy keys ----------

PROXY_KEY_DIR="/var/www/html/docker/tesla-http-proxy"
WELLKNOWN_DIR="/var/www/html/public/.well-known/appspecific"

if [ ! -f "$PROXY_KEY_DIR/fleet-key.pem" ]; then
    echo "Generating Vehicle Command Proxy keys..."
    mkdir -p "$PROXY_KEY_DIR"

    # Fleet key — EC key for signing vehicle commands (registered with Tesla)
    openssl ecparam -name prime256v1 -genkey -noout -out "$PROXY_KEY_DIR/fleet-key.pem"

    # Separate TLS key/cert for the HTTPS server
    openssl ecparam -name prime256v1 -genkey -noout -out "$PROXY_KEY_DIR/tls-key.pem"
    openssl req -new -x509 -key "$PROXY_KEY_DIR/tls-key.pem" \
        -out "$PROXY_KEY_DIR/tls-cert.pem" -days 3650 -subj "/CN=localhost"

    chmod 644 "$PROXY_KEY_DIR"/*.pem
fi

# Always ensure the public key is at the well-known path
if [ ! -f "$WELLKNOWN_DIR/com.tesla.3p.public-key.pem" ]; then
    echo "Extracting public key to .well-known path..."
    mkdir -p "$WELLKNOWN_DIR"
    openssl ec -in "$PROXY_KEY_DIR/fleet-key.pem" -pubout \
        -out "$WELLKNOWN_DIR/com.tesla.3p.public-key.pem" 2>/dev/null
fi

# ---------- Fleet Telemetry certs ----------

FT_CERT_DIR="/var/www/html/docker/fleet-telemetry/certs"
FT_CONFIG="/var/www/html/docker/fleet-telemetry/config.json"

if [ ! -f "$FT_CERT_DIR/server.crt" ]; then
    echo "Generating Fleet Telemetry TLS certificates..."
    mkdir -p "$FT_CERT_DIR"

    FT_HOSTNAME="${FLEET_TELEMETRY_HOSTNAME:-telemetry.localhost}"

    # Generate CA
    openssl ecparam -name prime256v1 -genkey -noout -out "$FT_CERT_DIR/ca.key"
    openssl req -new -x509 -key "$FT_CERT_DIR/ca.key" \
        -out "$FT_CERT_DIR/ca.crt" -days 3650 -subj "/CN=Teslog Fleet Telemetry CA"

    # Generate server cert signed by CA
    openssl ecparam -name prime256v1 -genkey -noout -out "$FT_CERT_DIR/server.key"
    openssl req -new -key "$FT_CERT_DIR/server.key" \
        -out "$FT_CERT_DIR/server.csr" -subj "/CN=$FT_HOSTNAME"

    cat > "$FT_CERT_DIR/ext.cnf" <<EXTEOF
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage=digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=DNS:$FT_HOSTNAME
EXTEOF

    openssl x509 -req -in "$FT_CERT_DIR/server.csr" \
        -CA "$FT_CERT_DIR/ca.crt" -CAkey "$FT_CERT_DIR/ca.key" -CAcreateserial \
        -out "$FT_CERT_DIR/server.crt" -days 3650 \
        -extfile "$FT_CERT_DIR/ext.cnf"

    rm -f "$FT_CERT_DIR/server.csr" "$FT_CERT_DIR/ext.cnf" "$FT_CERT_DIR/ca.srl"
    chmod 644 "$FT_CERT_DIR"/*.crt "$FT_CERT_DIR"/*.key
    echo "Fleet Telemetry certs generated for: $FT_HOSTNAME"
fi

# ---------- Fleet Telemetry config ----------

if [ ! -f "$FT_CONFIG" ]; then
    echo "Generating Fleet Telemetry config..."
    cat > "$FT_CONFIG" <<FTEOF
{
  "host": "0.0.0.0",
  "port": 4443,
  "log_level": "info",
  "json_log_enable": true,
  "namespace": "teslog",
  "reliable_ack": true,
  "transmit_decoded_records": true,
  "reliable_ack_sources": {
    "V": "mqtt"
  },
  "monitoring": {
    "prometheus_metrics_port": 9090,
    "profiler_port": 0,
    "profiling_path": ""
  },
  "records": {
    "alerts": ["logger"],
    "errors": ["logger"],
    "V": ["mqtt"]
  },
  "tls": {
    "server_cert": "/etc/fleet-telemetry/certs/server.crt",
    "server_key": "/etc/fleet-telemetry/certs/server.key",
    "ca_file": "/etc/fleet-telemetry/certs/ca.crt"
  },
  "mqtt": {
    "broker": "mosquitto:1883",
    "client_id": "fleet-telemetry",
    "topic_base": "teslog",
    "qos": 1,
    "publish_timeout_ms": 5000
  }
}
FTEOF
fi

# ---------- Database ----------

# Use /data volume for SQLite persistence (keeps migrations dir from git intact)
if [ "$DB_CONNECTION" = "sqlite" ]; then
    if [ -d "/data" ]; then
        # Volume mounted — use /data for the database file
        if [ ! -f /data/database.sqlite ]; then
            touch /data/database.sqlite
        fi
        ln -sf /data/database.sqlite database/database.sqlite
    elif [ ! -f database/database.sqlite ]; then
        touch database/database.sqlite
    fi
fi

php artisan migrate --force

# ---------- Production caching ----------

if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# ---------- Final setup ----------

php artisan storage:link 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache database
[ -d /data ] && chown -R www-data:www-data /data

exec "$@"
