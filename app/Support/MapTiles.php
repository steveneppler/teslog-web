<?php

namespace App\Support;

use App\Models\User;

/**
 * Basemap tile providers and the resolution rules that pick one.
 *
 * Provider choice used to be env-only, which meant a container restart to change
 * basemaps. The catalog lives here so the settings UI, the per-user preference
 * and the env-based default all resolve through the same rules.
 *
 * URL templates, zoom limits and attribution strings follow the leaflet-providers
 * definitions (https://github.com/leaflet-extras/leaflet-providers), which track
 * provider endpoint changes — Thunderforest, for one, has moved off its old
 * {s}.tile.thunderforest.com subdomains.
 */
class MapTiles
{
    public const DEFAULT_PROVIDER = 'esri';

    /**
     * Tile URLs may contain an `{api_key}` placeholder, substituted (URL encoded)
     * by resolve(). Leaflet would otherwise try to interpolate it as a tile
     * coordinate, so the substitution has to happen before the URL reaches JS.
     *
     * `{r}` is Leaflet's own retina placeholder and is left for it to handle.
     */
    private const PROVIDERS = [
        // Keyless. Tiles are only served up to z16, so Leaflet upscales beyond that.
        'esri' => [
            'label' => 'Esri Gray Canvas',
            'description' => 'No API key needed. Muted basemap with a dark variant; detail is upscaled past zoom 16.',
            'api_key' => false,
            'light' => 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
            'dark' => 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}',
            'subdomains' => 'abc',
            'max_zoom' => 19,
            'max_native_zoom' => 16,
            'attribution' => 'Tiles &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener">Esri</a> &mdash; Esri, HERE, Garmin, &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        ],
        // Keyless aerial imagery. Photography has no dark variant.
        'esri-satellite' => [
            'label' => 'Esri World Imagery (satellite)',
            'description' => 'No API key needed. Aerial and satellite photography; the same imagery is used in dark mode.',
            'api_key' => false,
            'light' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            'dark' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            'subdomains' => 'abc',
            'max_zoom' => 20,
            'max_native_zoom' => 19,
            'attribution' => 'Tiles &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener">Esri</a> &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        ],
        // Keyless, but has no dark variant.
        'osm' => [
            'label' => 'OpenStreetMap',
            'description' => 'No API key needed. Full-colour standard OSM tiles; the same tiles are used in dark mode.',
            'api_key' => false,
            'light' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'dark' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'subdomains' => 'abc',
            'max_zoom' => 19,
            'max_native_zoom' => 19,
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        ],
        'carto' => [
            'label' => 'CARTO Positron / Dark Matter',
            'description' => 'Sharpest basemap, full detail to zoom 20, with matched light and dark styles. Requires a free API key.',
            'api_key' => true,
            'api_key_url' => 'https://carto.com/basemaps/apikey',
            'light' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?key={api_key}',
            'dark' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key={api_key}',
            'subdomains' => 'abcd',
            'max_zoom' => 20,
            'max_native_zoom' => 20,
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>',
        ],
        'stadia' => [
            'label' => 'Stadia Alidade Smooth',
            'description' => 'Muted basemap close to CARTO, with a matched dark style. Requires a free API key and registering your domain.',
            'api_key' => true,
            'api_key_url' => 'https://client.stadiamaps.com/signup/',
            'light' => 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png?api_key={api_key}',
            'dark' => 'https://tiles.stadiamaps.com/tiles/alidade_smooth_dark/{z}/{x}/{y}{r}.png?api_key={api_key}',
            'subdomains' => 'abc',
            'max_zoom' => 20,
            'max_native_zoom' => 20,
            'attribution' => '&copy; <a href="https://www.stadiamaps.com/" target="_blank" rel="noopener">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        ],
        // MapTiler serves 512px raster tiles, hence the tile size and zoom offset.
        'maptiler' => [
            'label' => 'MapTiler Dataviz',
            'description' => 'Clean data-focused basemap with a matched dark style, detailed to zoom 21. Requires a free API key.',
            'api_key' => true,
            'api_key_url' => 'https://cloud.maptiler.com/account/keys/',
            'light' => 'https://api.maptiler.com/maps/dataviz-light/{z}/{x}/{y}{r}.png?key={api_key}',
            'dark' => 'https://api.maptiler.com/maps/dataviz-dark/{z}/{x}/{y}{r}.png?key={api_key}',
            'subdomains' => 'abc',
            'max_zoom' => 21,
            'max_native_zoom' => 21,
            'tile_size' => 512,
            'zoom_offset' => -1,
            'attribution' => '<a href="https://www.maptiler.com/copyright/" target="_blank" rel="noopener">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">&copy; OpenStreetMap contributors</a>',
        ],
        'thunderforest' => [
            'label' => 'Thunderforest Transport',
            'description' => 'Road and transit focused basemap with a matched dark style, detailed to zoom 22. Requires a free API key.',
            'api_key' => true,
            'api_key_url' => 'https://www.thunderforest.com/pricing/',
            'light' => 'https://api.thunderforest.com/transport/{z}/{x}/{y}{r}.png?apikey={api_key}',
            'dark' => 'https://api.thunderforest.com/transport-dark/{z}/{x}/{y}{r}.png?apikey={api_key}',
            'subdomains' => 'abc',
            'max_zoom' => 22,
            'max_native_zoom' => 22,
            'attribution' => '&copy; <a href="https://www.thunderforest.com/" target="_blank" rel="noopener">Thunderforest</a>, &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        ],
    ];

    /** Only the providers that serve oversized tiles need to override these. */
    private const TILE_DEFAULTS = [
        'tile_size' => null,
        'zoom_offset' => null,
    ];

    /** The keys resolve() copies through to the browser. */
    private const TILE_KEYS = [
        'light', 'dark', 'subdomains', 'max_zoom', 'max_native_zoom', 'attribution', 'tile_size', 'zoom_offset',
    ];

    /** Provider names, in the order the settings page offers them. */
    public static function names(): array
    {
        return array_keys(self::PROVIDERS);
    }

    /** Catalog entries for the settings UI, without the tile URLs. */
    public static function options(): array
    {
        return array_map(static function (array $provider): array {
            return [
                'label' => $provider['label'],
                'description' => $provider['description'],
                'api_key' => $provider['api_key'],
                'api_key_url' => $provider['api_key_url'] ?? null,
            ];
        }, self::PROVIDERS);
    }

    public static function requiresApiKey(?string $provider): bool
    {
        return (bool) (self::PROVIDERS[(string) $provider]['api_key'] ?? false);
    }

    /** The providers a user can hold a saved key for. */
    public static function keyedNames(): array
    {
        return array_values(array_filter(self::names(), static fn (string $name): bool => self::requiresApiKey($name)));
    }

    public static function label(?string $provider): ?string
    {
        return self::PROVIDERS[(string) $provider]['label'] ?? null;
    }

    public static function apiKeyUrl(?string $provider): ?string
    {
        return self::PROVIDERS[(string) $provider]['api_key_url'] ?? null;
    }

    /**
     * A missing, blank or whitespace-only value counts as unset. Laravel already
     * reads a bare `KEY=`, `KEY=null` and `KEY=(null)` as null; anything else is
     * taken literally, including `false` and `0`, so an unrecognized provider name
     * falls back to the default rather than triggering automatic selection.
     */
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // env() coerces `KEY=false` to a boolean, which must still read as the
        // literal (unrecognized) provider name rather than as an unset value.
        $value = is_bool($value) ? ($value ? 'true' : 'false') : trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * The tiles a user sees: their own choice, or the env-configured default until
     * they make one.
     *
     * A user picks a provider explicitly on the settings page, so an unset provider
     * means "follow the server default" even when keys from earlier choices are
     * still stored — those are kept only so re-selecting a provider does not mean
     * retyping its key. (The env path below still auto-selects from a bare CARTO
     * key, where there is no UI to choose with.)
     */
    public static function forUser(?User $user): array
    {
        $provider = $user ? self::normalize($user->map_provider) : null;

        if ($provider === null) {
            return config('teslog.map_tiles');
        }

        return self::resolve($provider, $user->mapApiKey($provider));
    }

    /**
     * Resolve a provider name and API key into a tile definition. Returns the
     * effective provider under `provider`, which differs from the requested one
     * whenever a fallback kicked in.
     */
    public static function resolve(mixed $provider, mixed $apiKey): array
    {
        $provider = self::normalize($provider);
        $apiKey = self::normalize($apiKey);

        // A key on its own is enough to opt in to the provider that needs one.
        $provider ??= $apiKey !== null ? 'carto' : self::DEFAULT_PROVIDER;

        // Selecting a keyed provider without a key would emit a blank key parameter
        // and reproduce the very failure this fallback exists to avoid.
        if (! isset(self::PROVIDERS[$provider]) || (self::requiresApiKey($provider) && $apiKey === null)) {
            $provider = self::DEFAULT_PROVIDER;
            $apiKey = null;
        }

        $entry = self::PROVIDERS[$provider] + self::TILE_DEFAULTS;
        $tiles = ['provider' => $provider];

        foreach (self::TILE_KEYS as $key) {
            $tiles[$key] = $entry[$key];
        }

        if ($apiKey !== null) {
            $encoded = rawurlencode($apiKey);
            $tiles['light'] = str_replace('{api_key}', $encoded, $tiles['light']);
            $tiles['dark'] = str_replace('{api_key}', $encoded, $tiles['dark']);
        }

        return $tiles;
    }
}
