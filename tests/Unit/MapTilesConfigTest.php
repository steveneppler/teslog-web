<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MapTilesConfigTest extends TestCase
{
    private const KEYS = ['TESLOG_MAP_PROVIDER', 'TESLOG_CARTO_API_KEY'];

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
        foreach (['esri', 'carto', 'osm'] as $provider) {
            $tiles = $this->mapTiles(['TESLOG_MAP_PROVIDER' => $provider]);

            $this->assertStringContainsString('<a href="http', $tiles['attribution'], "$provider attribution is not linked");
        }

        $osm = $this->mapTiles(['TESLOG_MAP_PROVIDER' => 'osm']);
        $this->assertStringContainsString('openstreetmap.org/copyright', $osm['attribution']);
    }
}
