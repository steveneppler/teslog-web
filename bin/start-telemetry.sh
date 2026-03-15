#!/bin/bash
# Starts fleet-telemetry and pipes output to the ingest script
cd "$(dirname "$0")/.."
exec bin/fleet-telemetry -config docker/fleet-telemetry/config.json 2>&1 | php bin/ingest-telemetry.php
