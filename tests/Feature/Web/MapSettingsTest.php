<?php

namespace Tests\Feature\Web;

use App\Livewire\Settings;
use App\Models\User;
use App\Support\MapTiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MapSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Reloaded so the preference columns carry their database defaults. */
    private function actingAsUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes)->fresh();
        $this->actingAs($user);

        return $user;
    }

    public function test_a_user_without_a_choice_gets_the_env_configured_default(): void
    {
        config(['teslog.map_tiles' => ['provider' => 'osm', 'light' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png']]);

        $user = User::factory()->create(['map_provider' => null, 'carto_api_key' => null]);

        $this->assertSame('osm', $user->mapTiles()['provider']);
    }

    public function test_a_users_choice_overrides_the_env_default(): void
    {
        config(['teslog.map_tiles' => ['provider' => 'esri']]);

        $user = User::factory()->create(['map_provider' => 'osm']);

        $tiles = $user->mapTiles();
        $this->assertSame('osm', $tiles['provider']);
        $this->assertStringContainsString('tile.openstreetmap.org', $tiles['light']);
    }

    public function test_a_users_carto_key_is_used_once_they_select_carto(): void
    {
        $user = User::factory()->create(['map_provider' => 'carto', 'carto_api_key' => 'secret']);

        $tiles = $user->mapTiles();
        $this->assertSame('carto', $tiles['provider']);
        $this->assertStringContainsString('api_key=secret', $tiles['light']);
    }

    /**
     * Unlike the env path, which auto-selects CARTO from a bare key, picking the
     * server default on the settings page means exactly that — a key left over from
     * an earlier choice is kept for reuse, not treated as a provider choice.
     */
    public function test_a_stored_key_does_not_override_the_server_default(): void
    {
        config(['teslog.map_tiles' => ['provider' => 'esri', 'light' => 'https://server.arcgisonline.com/x']]);

        $user = User::factory()->create(['map_provider' => null, 'carto_api_key' => 'secret']);

        $this->assertSame('esri', $user->mapTiles()['provider']);
    }

    public function test_saving_a_provider_persists_it_and_asks_the_page_to_reload(): void
    {
        $this->actingAsUser(['map_provider' => null]);

        Livewire::test(Settings::class)
            ->set('map_provider', 'osm')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('map-settings-changed');

        $this->assertSame('osm', User::first()->map_provider);
    }

    public function test_saving_carto_without_a_key_is_rejected_rather_than_silently_downgraded(): void
    {
        $this->actingAsUser();

        Livewire::test(Settings::class)
            ->set('map_provider', 'carto')
            ->set('carto_api_key', '   ')
            ->call('save')
            ->assertHasErrors('carto_api_key');

        $this->assertNull(User::first()->map_provider);
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->actingAsUser();

        Livewire::test(Settings::class)
            ->set('map_provider', 'not-a-provider')
            ->call('save')
            ->assertHasErrors('map_provider');

        $this->assertNull(User::first()->map_provider);
    }

    public function test_clearing_the_provider_falls_back_to_the_server_default(): void
    {
        config(['teslog.map_tiles' => ['provider' => 'esri', 'light' => 'https://server.arcgisonline.com/x']]);

        $this->actingAsUser(['map_provider' => 'carto', 'carto_api_key' => 'secret']);

        Livewire::test(Settings::class)
            ->set('map_provider', '')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::first();
        $this->assertNull($user->map_provider);
        $this->assertSame('esri', $user->mapTiles()['provider']);

        // The key survives so switching back to CARTO does not mean retyping it.
        $this->assertSame('secret', $user->carto_api_key);
    }

    public function test_saving_unrelated_preferences_does_not_reload_the_page(): void
    {
        $this->actingAsUser(['map_provider' => 'osm']);

        Livewire::test(Settings::class)
            ->set('currency', 'EUR')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotDispatched('map-settings-changed');
    }

    public function test_the_carto_key_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create(['carto_api_key' => 'secret']);

        $stored = \DB::table('users')->where('id', $user->id)->value('carto_api_key');

        $this->assertNotSame('secret', $stored);
        $this->assertStringNotContainsString('secret', (string) $stored);
        $this->assertSame('secret', $user->fresh()->carto_api_key);
    }

    public function test_the_maps_section_offers_every_provider(): void
    {
        $this->actingAsUser();

        $page = Livewire::test(Settings::class)
            ->assertSee('Maps')
            ->assertSee('Basemap provider');

        foreach (MapTiles::options() as $option) {
            $page->assertSee($option['label']);
        }
    }

    public function test_the_api_key_field_appears_only_for_a_provider_that_needs_one(): void
    {
        $this->actingAsUser();

        Livewire::test(Settings::class)
            ->set('map_provider', 'osm')
            ->assertDontSee('CARTO API key')
            ->set('map_provider', 'carto')
            ->assertSee('CARTO API key')
            ->assertSee('carto.com/basemaps/apikey');
    }

    public function test_the_layout_ships_the_users_own_tiles_to_the_browser(): void
    {
        $this->actingAsUser(['map_provider' => 'osm']);

        $this->get(route('settings'))
            ->assertOk()
            ->assertSee('tile.openstreetmap.org', false);
    }

    public function test_the_carto_key_is_not_serialized_with_the_user(): void
    {
        $user = User::factory()->create(['carto_api_key' => 'secret']);

        $this->assertArrayNotHasKey('carto_api_key', $user->toArray());
    }
}
