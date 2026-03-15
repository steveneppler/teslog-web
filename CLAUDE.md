# Teslog Web — Development Notes

## Livewire + JavaScript (Leaflet/Chart.js) Pattern

When a Livewire component contains a JavaScript-rendered element (Leaflet map, Chart.js chart, etc.):

1. **Never use inline `<script>` tags** inside conditionally-rendered Blade markup. They won't re-execute on Livewire re-renders.
2. **Always use `@script` blocks** with `$wire.on('event-name', ...)` to initialize/update JS elements.
3. **Dispatch events from `render()`** in the component: `$this->dispatch('event-name', data: $data)`.
4. **Use `wire:ignore`** on the container div of any JS-managed element (maps, charts) that should persist across Livewire re-renders. Without it, Livewire will replace the DOM element and destroy the JS instance.
5. **Destroy old instances** before reinitializing when the element is recreated (e.g., store map instances in `window.__mapInstances` and call `.remove()` before creating new ones).

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
- Laravel 11, Livewire 3, Tailwind CSS, Vite
- Leaflet.js (maps), Chart.js (charts)
- SQLite default, queued jobs via `database` driver
- Tesla Fleet Telemetry (streaming), Fleet API (commands)

## Key Commands
- `php artisan teslog:process-states --vehicle=ID [--force]` — Reprocess vehicle states into drives/charges
- `php artisan teslog:match-places --vehicle=ID` — Match drives/charges to saved places by GPS proximity
- `php artisan teslog:geocode --vehicle=ID` — Geocode drive/charge addresses via Nominatim
- `php artisan teslog:refresh-tokens` — Refresh expiring Tesla OAuth tokens (runs hourly)
- `php artisan teslog:record-battery-health` — Record daily battery health snapshots (scheduled at 3 AM)
- `php artisan teslog:backfill-battery-health [--vehicle=ID]` — Backfill battery health from historical vehicle states (>=70% SOC)
- `php artisan teslog:backfill-firmware-history [--vehicle=ID]` — Backfill firmware history from software_version changes in vehicle states

## Timezone Handling
- Database stores all timestamps in UTC
- Display timestamps converted to user's timezone: `$timestamp->tz($userTz)->format(...)`
- Pass `$userTz = Auth::user()->timezone ?? 'UTC'` from component to view
- Group drives by date in user's timezone, not UTC
- Week navigation boundaries must be computed in user's timezone then converted to UTC for queries
