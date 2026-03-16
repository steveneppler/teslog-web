# Teslog Web — Development Notes

## Livewire + JavaScript (Leaflet/Chart.js) Pattern

When a Livewire component contains a JavaScript-rendered element (Leaflet map, Chart.js chart, etc.):

1. **Never use inline `<script>` tags** inside conditionally-rendered Blade markup. They won't re-execute on Livewire re-renders.
2. **Always use `@script` blocks** with `$wire.on('event-name', ...)` to initialize/update JS elements.
3. **Dispatch events from `render()`** in the component: `$this->dispatch('event-name', data: $data)`.
4. **Use `wire:ignore`** on the container div of any JS-managed element (maps, charts) that should persist across Livewire re-renders. Without it, Livewire will replace the DOM element and destroy the JS instance.
5. **Destroy old instances** before reinitializing when the element is recreated (e.g., store map instances in `window.__mapInstances` and call `.remove()` before creating new ones).
6. **Avoid arrow functions (`fn () =>`) inside `@json()` directives** in Blade templates — Blade's parser confuses `=>` with PHP array syntax. Prepare data in the Livewire component and pass it as a variable instead.

### Example pattern:
```php
// Component render()
$this->dispatch('map-updated', mapData: $data);
```
```blade
<div wire:ignore>
    <div id="my-map"></div>
</div>

@script
<script>
    var __map = null;
    $wire.on('map-updated', function(params) {
        setTimeout(function() {
            // init or update map using params.mapData
        }, 100);
    });
</script>
@endscript
```

## Tech Stack
- Laravel 12, Livewire 4, Tailwind CSS, Vite
- Leaflet.js (maps), Chart.js (charts)
- SQLite default, queued jobs via Redis + Laravel Horizon
- Tesla Fleet Telemetry (streaming via MQTT), Fleet API (commands)
- Laravel Reverb (WebSocket for real-time updates)
- Mosquitto MQTT broker for telemetry delivery

## Architecture
- Five Docker containers: app (PHP-FPM, Nginx, Horizon, scheduler, Reverb, MQTT subscriber), fleet-telemetry, mosquitto, tesla-http-proxy, redis
- Fleet Telemetry publishes to MQTT; Laravel MQTT subscriber ingests and dispatches ProcessTelemetryBatch jobs
- Vehicle's `latest_state_id` is cached on the vehicles table for fast dashboard loading — update it when creating new VehicleState records

## Key Commands
- `php artisan teslog:process-states --vehicle=ID [--force]` — Reprocess vehicle states into drives/charges
- `php artisan teslog:match-places --vehicle=ID` — Match drives/charges to saved places by GPS proximity
- `php artisan teslog:geocode --vehicle=ID` — Geocode drive/charge addresses via Nominatim (with Place matching and ~50m coordinate cache)
- `php artisan teslog:refresh-tokens` — Refresh expiring Tesla OAuth tokens (runs every 15 min via scheduler)
- `php artisan teslog:check-health` — Check telemetry pipeline health, auto-reconfigure fleet telemetry if cleared
- `php artisan teslog:record-battery-health` — Record daily battery health snapshots (scheduled at 3 AM)
- `php artisan teslog:backfill-battery-health [--vehicle=ID]` — Backfill battery health from historical vehicle states (>=70% SOC)
- `php artisan teslog:backfill-firmware-history [--vehicle=ID]` — Backfill firmware history from software_version changes
- `php artisan teslog:backfill-efficiency [--vehicle=ID]` — Backfill energy_used_kwh and efficiency for drives
- `php artisan teslog:backfill-charge-costs [--force]` — Calculate charge costs from place electricity rates (flat or ToU)
- `php artisan teslog:backfill-charge-stats` — Backfill charge stats from vehicle states
- `php artisan teslog:backfill-elevation [--vehicle=ID]` — Backfill elevation data for drive points

## Timezone Handling
- Database stores all timestamps in UTC
- Display timestamps converted to user's timezone: `$timestamp->tz($userTz)->format(...)`
- Use `Auth::user()->userTz()` (defined on User model) instead of inline `Auth::user()->timezone ?? 'UTC'`
- Group drives by date in user's timezone, not UTC
- Week navigation boundaries must be computed in user's timezone then converted to UTC for queries

## Performance Notes
- The `vehicle_states` table can grow to millions of rows — avoid full table scans
- Use `Vehicle.latest_state_id` (BelongsTo) instead of querying `MAX(timestamp)` on vehicle_states
- Dashboard sparkline aggregates hourly averages in the DB query, not in PHP
- `chunkById()` instead of `chunk()` when updating rows that match the query filter (avoids skipping rows)
- Geocode command caches Nominatim results on a ~50m coordinate grid and checks saved Places first
