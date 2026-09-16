<?php

namespace Tests\Unit;

use App\Support\MapTiles;
use PHPUnit\Framework\TestCase;

class MapTilesConfigTest extends TestCase
{
    private const KEYS = ['TESLOG_MAP_PROVIDER', 'TESLOG_CARTO_API_KEY', 'TESLOG_MAP_API_KEY'];

    protected function tearDown(): void
    {
        $this->withEnv([]);

        parent::tearDown();
    }

    /** Re-reads the config file so provider selection runs against the given env. */
    private function mapTiles(array $env = []): array
    {
        $this->withEnv($env);

        return (require dirname(__DIR__, 2).'/config/teslog.php')['map_tiles'];
    }

    private function withEnv(array $env): void
    {
        foreach (self::KEYS as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }

    public function test_defaults_to_the_keyless_esri_basemap(): void
    {
        $tiles = $this->mapTiles();

        $this->assertStringContainsString('arcgisonline.com', $tiles['light']);
        $this->assertStringContainsString('arcgisonline.com', $tiles['dark']);
        $this->assertNotSame($tiles['light'], $tiles['dark']);
    }

    public function test_carto_api_key_alone_selects_carto(): void
    {
        $tiles = $this->mapTiles(['TESLOG_CARTO_API_KEY' => 'secret']);

        $this->assertStringContainsString('cartocdn.com', $tiles['light']);
        $this->assertStringContainsString('api_key=secret', $tiles['light']);
        $this->assertStringContainsString('api_key=secret', $tiles['dark']);
    }

    public function test_blank_provider_still_allows_automatic_carto_selection(): void
    {
        $tiles = $this->mapTiles([
            'TESLOG_MAP_PROVIDER' => '',
            'TESLOG_CARTO_API_KEY' => 'secret',
        ]);

        $this->assertStringContainsString('cartocdn.com', $tiles['light']);
    }

    public function test_blank_carto_api_key_falls_back_to_esri(): void
    {
        $tiles = $this->mapTiles(['TESLOG_CARTO_API_KEY' => '']);

        $this->assertStringContainsString('arcgisonline.com', $tiles['light']);
    }

    public function test_carto_api_key_is_url_encoded(): void
    {
        $tiles = $this->mapTiles(['TESLOG_CARTO_API_KEY' => 'a b&c']);

        $this->assertStringContainsString('api_key=a%20b%26c', $tiles['light']);
    }

    public function test_explicit_provider_wins_over_the_carto_key(): void
    {
        $tiles = $this->mapTiles([
            'TESLOG_MAP_PROVIDER' => 'osm',
            'TESLOG_CARTO_API_KEY' => 'secret',
        ]);

        $this->assertStringContainsString('tile.openstreetmap.org', $tiles['light']);
        $this->assertSame($tiles['light'], $tiles['dark'], 'OSM has no dark variant');
    }

    public function test_unknown_provider_falls_back_to_esri(): void
    {
        $tiles = $this->mapTiles(['TESLOG_MAP_PROVIDER' => 'not-a-provider']);

        $this->assertStringContainsString('arcgisonline.com', $tiles['light']);
    }

    public function test_falsy_provider_string_is_not_mistaken_for_unset(): void
    {
        $tiles = $this->mapTiles([
            'TESLOG_MAP_PROVIDER' => '0',
            'TESLOG_CARTO_API_KEY' => 'secret',
        ]);

        $this->assertStringContainsString('arcgisonline.com', $tiles['light'], '"0" is an unknown provider, not an unset one');
    }

    public function test_whitespace_provider_is_treated_as_unset(): void
    {
        $tiles = $this->mapTiles([
            'TESLOG_MAP_PROVIDER' => '  ',
            'TESLOG_CARTO_API_KEY' => 'secret',
        ]);

        $this->assertStringContainsString('cartocdn.com', $tiles['light']);
    }

    public function test_carto_without_an_api_key_falls_back_to_esri(): void
    {
        $tiles = $this->mapTiles(['TESLOG_MAP_PROVIDER' => 'carto']);

        $this->assertStringContainsString('arcgisonline.com', $tiles['light']);
        $this->assertStringNotContainsString('api_key=', $tiles['light']);
    }

    public function test_tile_urls_never_carry_an_empty_api_key(): void
    {
        foreach (array_merge(['', 'bogus'], MapTiles::names()) as $provider) {
            $tiles = $this->mapTiles(['TESLOG_MAP_PROVIDER' => $provider]);

            foreach (['light', 'dark'] as $variant) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(api_key|apikey|key)=(&|$)/',
                    $tiles[$variant],
                    "provider '$provider' emits a blank api_key on the $variant tiles"
                );
            }
        }
    }

    public function test_esri_upscales_past_its_native_zoom(): void
    {
        $tiles = $this->mapTiles();

        $this->assertSame(16, $tiles['max_native_zoom']);
        $this->assertGreaterThan($tiles['max_native_zoom'], $tiles['max_zoom']);
    }

    /**
     * Every provider's terms require a visible, linked credit, and Leaflet does not
     * linkify plain attribution strings.
     */
    public function test_every_provider_links_its_attribution(): void
    {
        // A keyed provider needs a key here, or the fallback would quietly return
        // Esri's tiles and this would never exercise that provider's attribution.
        foreach (MapTiles::names() as $provider) {
            $tiles = MapTiles::resolve($provider, MapTiles::requiresApiKey($provider) ? 'secret' : null);

            $this->assertSame($provider, $tiles['provider'], "$provider fell back instead of resolving");
            $this->assertStringContainsString('<a href="http', $tiles['attribution'], "$provider attribution is not linked");
        }

        $carto = $this->mapTiles(['TESLOG_MAP_PROVIDER' => 'carto', 'TESLOG_CARTO_API_KEY' => 'secret']);
        $this->assertStringContainsString('cartocdn.com', $carto['light'], 'the CARTO case must not fall back to Esri');
        $this->assertStringContainsString('carto.com/attributions', $carto['attribution']);

        $osm = $this->mapTiles(['TESLOG_MAP_PROVIDER' => 'osm']);
        $this->assertStringContainsString('openstreetmap.org/copyright', $osm['attribution']);
    }

    public function test_boolean_like_provider_is_not_mistaken_for_unset(): void
    {
        $tiles = $this->mapTiles([
            'TESLOG_MAP_PROVIDER' => 'false',
            'TESLOG_CARTO_API_KEY' => 'secret',
        ]);

        $this->assertStringContainsString('arcgisonline.com', $tiles['light'], '"false" is an unknown provider, not an unset one');
    }

    /**
     * Laravel reads `KEY=null` as "no value", so a null-like provider is unset
     * rather than an unknown provider name. This pins that as deliberate.
     */
    public function test_null_like_provider_is_treated_as_unset(): void
    {
        foreach (['null', '(null)'] as $value) {
            $tiles = $this->mapTiles([
                'TESLOG_MAP_PROVIDER' => $value,
                'TESLOG_CARTO_API_KEY' => 'secret',
            ]);

            $this->assertStringContainsString('cartocdn.com', $tiles['light'], "'$value' should read as unset");
        }
    }

    public function test_falsy_carto_api_key_still_selects_carto(): void
    {
        $tiles = $this->mapTiles(['TESLOG_CARTO_API_KEY' => '0']);

        $this->assertStringContainsString('cartocdn.com', $tiles['light']);
        $this->assertStringContainsString('api_key=0', $tiles['light']);
    }

    public function test_resolved_tiles_name_the_effective_provider(): void
    {
        $this->assertSame('osm', MapTiles::resolve('osm', null)['provider']);
        $this->assertSame('carto', MapTiles::resolve('carto', 'secret')['provider']);

        // The fallbacks have to be visible, or the settings page cannot tell the
        // user their choice did not take effect.
        $this->assertSame('esri', MapTiles::resolve('carto', null)['provider']);
        $this->assertSame('esri', MapTiles::resolve('not-a-provider', null)['provider']);
    }

    public function test_tile_urls_never_leak_an_unsubstituted_placeholder(): void
    {
        foreach ([[null, null], ['carto', 'secret'], ['carto', null], ['osm', null], ['bogus', 'secret']] as [$provider, $key]) {
            $tiles = MapTiles::resolve($provider, $key);

            foreach (['light', 'dark'] as $variant) {
                $this->assertStringNotContainsString('{api_key}', $tiles[$variant]);
            }
        }
    }

    public function test_the_keyless_providers_need_no_api_key(): void
    {
        foreach (['esri', 'esri-satellite', 'osm'] as $provider) {
            $this->assertFalse(MapTiles::requiresApiKey($provider), "$provider should not need a key");
            $this->assertSame($provider, MapTiles::resolve($provider, null)['provider']);
        }

        foreach (['carto', 'stadia', 'maptiler', 'thunderforest'] as $provider) {
            $this->assertTrue(MapTiles::requiresApiKey($provider), "$provider should need a key");
            $this->assertSame('esri', MapTiles::resolve($provider, null)['provider'], "$provider should fall back without one");
        }

        $this->assertSame(
            ['carto', 'stadia', 'maptiler', 'thunderforest'],
            MapTiles::keyedNames()
        );

        // The settings page asks about the "use the server default" choice too.
        $this->assertFalse(MapTiles::requiresApiKey(''));
        $this->assertFalse(MapTiles::requiresApiKey(null));
    }

    /**
     * Each provider spells its key parameter differently, and Leaflet only
     * interpolates its own placeholders — so a missed substitution would send the
     * literal text "{api_key}" to the tile server.
     */
    public function test_each_keyed_provider_carries_the_key_in_its_own_parameter(): void
    {
        $expected = [
            'carto' => 'api_key=secret',
            'stadia' => 'api_key=secret',
            'maptiler' => 'key=secret',
            'thunderforest' => 'apikey=secret',
        ];

        foreach ($expected as $provider => $parameter) {
            $tiles = MapTiles::resolve($provider, 'secret');

            foreach (['light', 'dark'] as $variant) {
                $this->assertStringContainsString($parameter, $tiles[$variant], "$provider $variant tiles");
            }
        }
    }

    /**
     * MapTiler serves 512px tiles; without the matching zoom offset every map
     * renders one zoom level too far in.
     */
    public function test_oversized_tiles_carry_a_matching_zoom_offset(): void
    {
        $maptiler = MapTiles::resolve('maptiler', 'secret');
        $this->assertSame(512, $maptiler['tile_size']);
        $this->assertSame(-1, $maptiler['zoom_offset']);

        foreach (['esri', 'osm', 'carto', 'stadia', 'thunderforest'] as $provider) {
            $tiles = MapTiles::resolve($provider, MapTiles::requiresApiKey($provider) ? 'secret' : null);

            $this->assertNull($tiles['tile_size'], "$provider should use Leaflet's default tile size");
            $this->assertNull($tiles['zoom_offset']);
        }
    }

    /** Only the two photographic/plain styles reuse one basemap in dark mode. */
    public function test_providers_with_a_dark_style_use_a_different_url_for_it(): void
    {
        foreach (['esri', 'carto', 'stadia', 'maptiler', 'thunderforest'] as $provider) {
            $tiles = MapTiles::resolve($provider, MapTiles::requiresApiKey($provider) ? 'secret' : null);

            $this->assertNotSame($tiles['light'], $tiles['dark'], "$provider has no distinct dark style");
        }

        foreach (['osm', 'esri-satellite'] as $provider) {
            $tiles = MapTiles::resolve($provider, null);

            $this->assertSame($tiles['light'], $tiles['dark'], "$provider has no dark variant to offer");
        }
    }

    public function test_the_generic_env_key_serves_any_keyed_provider(): void
    {
        $tiles = $this->mapTiles([
            'TESLOG_MAP_PROVIDER' => 'maptiler',
            'TESLOG_MAP_API_KEY' => 'secret',
        ]);

        $this->assertSame('maptiler', $tiles['provider']);
        $this->assertStringContainsString('key=secret', $tiles['light']);
    }

    /**
     * The legacy variable names CARTO specifically, so handing it to another
     * provider would send a CARTO credential to that provider's tile servers.
     */
    public function test_the_legacy_carto_key_is_never_sent_to_another_provider(): void
    {
        foreach (array_diff(MapTiles::keyedNames(), ['carto']) as $provider) {
            $tiles = $this->mapTiles([
                'TESLOG_MAP_PROVIDER' => $provider,
                'TESLOG_CARTO_API_KEY' => 'carto-secret',
            ]);

            $this->assertSame('esri', $tiles['provider'], "$provider accepted the legacy CARTO key");
            $this->assertStringNotContainsString('carto-secret', $tiles['light']);
            $this->assertStringNotContainsString('carto-secret', $tiles['dark']);
        }
    }

    public function test_the_generic_env_key_still_serves_a_keyless_provider_choice(): void
    {
        // The legacy key is scoped to CARTO, but must not disturb a keyless pick.
        $tiles = $this->mapTiles([
            'TESLOG_MAP_PROVIDER' => 'osm',
            'TESLOG_CARTO_API_KEY' => 'carto-secret',
        ]);

        $this->assertSame('osm', $tiles['provider']);
        $this->assertStringNotContainsString('carto-secret', $tiles['light']);
    }

    public function test_the_generic_env_key_wins_over_the_legacy_carto_one(): void
    {
        $tiles = $this->mapTiles([
            'TESLOG_MAP_PROVIDER' => 'carto',
            'TESLOG_MAP_API_KEY' => 'newer',
            'TESLOG_CARTO_API_KEY' => 'older',
        ]);

        $this->assertStringContainsString('api_key=newer', $tiles['light']);
    }

    public function test_every_offered_provider_is_selectable_and_labelled(): void
    {
        $options = MapTiles::options();

        $this->assertSame(MapTiles::names(), array_keys($options));

        foreach ($options as $name => $option) {
            $this->assertNotSame('', $option['label'], "$name has no label");
            $this->assertNotSame('', $option['description'], "$name has no description");

            // A keyed provider with no signup link would be a dead end in the UI.
            if ($option['api_key']) {
                $this->assertStringStartsWith('http', (string) $option['api_key_url']);
            }

            $key = $option['api_key'] ? 'secret' : null;
            $this->assertSame($name, MapTiles::resolve($name, $key)['provider']);
        }

        $this->assertArrayNotHasKey('light', $options['esri'], 'options() must not ship tile URLs to the settings UI');
    }

    public function test_options_never_expose_a_configured_api_key(): void
    {
        $encoded = json_encode(MapTiles::options());

        $this->assertStringNotContainsString('api_key=', $encoded);
    }
}
