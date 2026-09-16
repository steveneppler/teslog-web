<?php

namespace App\Support;

use App\Models\User;

/**
 * Basemap tile providers and the resolution rules that pick one.
 *
 * Provider choice used to be env-only, which meant a container restart to change
 * basemaps. The catalog lives here so the settings UI, the per-user preference
 * and the env-based default all resolve through the same rules.
 */
class MapTiles
{
    public const DEFAULT_PROVIDER = 'esri';

    /**
     * Tile URLs may contain an `{api_key}` placeholder, substituted (URL encoded)
     * by resolve(). Leaflet would otherwise try to interpolate it as a tile
     * coordinate, so the substitution has to happen before the URL reaches JS.
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
        // Requires an API key from https://carto.com/basemaps/apikey.
        'carto' => [
            'label' => 'CARTO Positron / Dark Matter',
            'description' => 'Sharpest basemap, full detail to zoom 20, with matched light and dark styles. Requires a free API key.',
            'api_key' => true,
            'api_key_url' => 'https://carto.com/basemaps/apikey',
            'light' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?api_key={api_key}',
            'dark' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?api_key={api_key}',
            'subdomains' => 'abcd',
            'max_zoom' => 20,
            'max_native_zoom' => 20,
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>',
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
    ];

    /** The keys resolve() copies through to the browser. */
    private const TILE_KEYS = ['light', 'dark', 'subdomains', 'max_zoom', 'max_native_zoom', 'attribution'];

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

    public static function label(?string $provider): ?string
    {
        return self::PROVIDERS[(string) $provider]['label'] ?? null;
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
     * means "follow the server default" even when a key from an earlier choice is
     * still stored — the key is kept only so re-selecting that provider does not
     * mean retyping it. (The env path below still auto-selects from a bare key,
     * where there is no UI to choose with.)
     */
    public static function forUser(?User $user): array
    {
        if (! $user || self::normalize($user->map_provider) === null) {
            return config('teslog.map_tiles');
        }

        return self::resolve($user->map_provider, $user->carto_api_key);
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

        // Selecting a keyed provider without a key would emit `?api_key=` and
        // reproduce the very failure this fallback exists to avoid.
        if (! isset(self::PROVIDERS[$provider]) || (self::requiresApiKey($provider) && $apiKey === null)) {
            $provider = self::DEFAULT_PROVIDER;
            $apiKey = null;
        }

        $tiles = ['provider' => $provider];

        foreach (self::TILE_KEYS as $key) {
            $tiles[$key] = self::PROVIDERS[$provider][$key];
        }

        if ($apiKey !== null) {
            $encoded = rawurlencode($apiKey);
            $tiles['light'] = str_replace('{api_key}', $encoded, $tiles['light']);
            $tiles['dark'] = str_replace('{api_key}', $encoded, $tiles['dark']);
        }

        return $tiles;
    }
}
