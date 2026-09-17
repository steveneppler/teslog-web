<?php

namespace Tests\Feature\Web;

use App\Livewire\Settings;
use App\Models\User;
use App\Support\MapTiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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

        $user = User::factory()->create(['map_provider' => null, 'map_api_keys' => null]);

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

    public function test_a_users_key_is_used_once_they_select_that_provider(): void
    {
        $user = User::factory()->create([
            'map_provider' => 'carto',
            'map_api_keys' => ['carto' => 'secret'],
        ]);

        $tiles = $user->mapTiles();
        $this->assertSame('carto', $tiles['provider']);
        $this->assertStringContainsString('?key=secret', $tiles['light']);
    }

    /** A key belonging to another provider must not be sent to the selected one. */
    public function test_only_the_selected_providers_key_is_used(): void
    {
        $user = User::factory()->create([
            'map_provider' => 'maptiler',
            'map_api_keys' => ['carto' => 'carto-key', 'maptiler' => 'maptiler-key'],
        ]);

        $tiles = $user->mapTiles();
        $this->assertSame('maptiler', $tiles['provider']);
        $this->assertStringContainsString('key=maptiler-key', $tiles['light']);
        $this->assertStringNotContainsString('carto-key', $tiles['light']);
    }

    public function test_selecting_a_provider_with_no_stored_key_falls_back(): void
    {
        $user = User::factory()->create([
            'map_provider' => 'stadia',
            'map_api_keys' => ['carto' => 'carto-key'],
        ]);

        $this->assertSame('esri', $user->mapTiles()['provider']);
    }

    /**
     * Unlike the env path, which auto-selects CARTO from a bare key, picking the
     * server default on the settings page means exactly that — a key left over from
     * an earlier choice is kept for reuse, not treated as a provider choice.
     */
    public function test_a_stored_key_does_not_override_the_server_default(): void
    {
        config(['teslog.map_tiles' => ['provider' => 'esri', 'light' => 'https://server.arcgisonline.com/x']]);

        $user = User::factory()->create(['map_provider' => null, 'map_api_keys' => ['carto' => 'secret']]);

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

    public function test_saving_a_keyed_provider_without_a_key_is_rejected_rather_than_silently_downgraded(): void
    {
        $this->actingAsUser();

        foreach (MapTiles::keyedNames() as $provider) {
            Livewire::test(Settings::class)
                ->set('map_provider', $provider)
                ->set('map_api_key', '   ')
                ->call('save')
                ->assertHasErrors('map_api_key');

            $this->assertNull(User::first()->map_provider, "$provider was saved without a key");
        }
    }

    public function test_every_keyed_provider_can_be_saved_with_a_key(): void
    {
        $this->actingAsUser();

        foreach (MapTiles::keyedNames() as $provider) {
            Livewire::test(Settings::class)
                ->set('map_provider', $provider)
                ->set('map_api_key', "key-for-$provider")
                ->call('save')
                ->assertHasNoErrors();

            $user = User::first()->fresh();
            $this->assertSame($provider, $user->map_provider);
            $this->assertSame($provider, $user->mapTiles()['provider']);
            $this->assertStringContainsString("key-for-$provider", $user->mapTiles()['light']);
        }
    }

    /**
     * A key of "0" is falsy in PHP but is a key like any other; the env path
     * deliberately preserves it, so the settings form must not drop it and
     * silently fall back to Esri.
     */
    public function test_a_falsy_api_key_is_saved_rather_than_treated_as_absent(): void
    {
        $this->actingAsUser();

        Livewire::test(Settings::class)
            ->set('map_provider', 'carto')
            ->set('map_api_key', '0')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::first()->fresh();
        $this->assertSame('0', $user->mapApiKey('carto'));
        $this->assertSame('carto', $user->mapTiles()['provider']);
        $this->assertStringContainsString('?key=0', $user->mapTiles()['light']);
    }

    /** Switching providers must not discard the key for the one left behind. */
    public function test_keys_are_kept_per_provider(): void
    {
        $this->actingAsUser();

        Livewire::test(Settings::class)
            ->set('map_provider', 'carto')
            ->set('map_api_key', 'carto-key')
            ->call('save')
            ->assertHasNoErrors()
            ->set('map_provider', 'maptiler')
            // Selecting another provider shows that provider's key, not the last one.
            ->assertSet('map_api_key', '')
            ->set('map_api_key', 'maptiler-key')
            ->call('save')
            ->assertHasNoErrors()
            // Coming back offers the original key again, without retyping.
            ->set('map_provider', 'carto')
            ->assertSet('map_api_key', 'carto-key');

        $this->assertSame(
            ['carto' => 'carto-key', 'maptiler' => 'maptiler-key'],
            User::first()->fresh()->map_api_keys
        );
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

        $this->actingAsUser(['map_provider' => 'carto', 'map_api_keys' => ['carto' => 'secret']]);

        Livewire::test(Settings::class)
            ->set('map_provider', '')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::first()->fresh();
        $this->assertNull($user->map_provider);
        $this->assertSame('esri', $user->mapTiles()['provider']);

        // The key survives so switching back to CARTO does not mean retyping it.
        $this->assertSame('secret', $user->mapApiKey('carto'));
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

    public function test_stored_keys_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create(['map_api_keys' => ['carto' => 'secret']]);

        $stored = DB::table('users')->where('id', $user->id)->value('map_api_keys');

        $this->assertStringNotContainsString('secret', (string) $stored);
        $this->assertSame('secret', $user->fresh()->mapApiKey('carto'));
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

        $page = Livewire::test(Settings::class);

        foreach (MapTiles::names() as $provider) {
            $page->set('map_provider', $provider);

            if (MapTiles::requiresApiKey($provider)) {
                $page->assertSee(MapTiles::label($provider).' API key')
                    ->assertSee(MapTiles::apiKeyUrl($provider), false);
            } else {
                $page->assertDontSee('wire:model="map_api_key"', false);
            }
        }
    }

    public function test_the_layout_ships_the_users_own_tiles_to_the_browser(): void
    {
        $this->actingAsUser(['map_provider' => 'osm']);

        $this->get(route('settings'))
            ->assertOk()
            ->assertSee('tile.openstreetmap.org', false);
    }

    /**
     * The migration writes Crypt::encryptString(json_encode(...)). The
     * encrypted:array cast must read exactly that representation, or keys
     * carried over on upgrade would be lost.
     */
    public function test_the_cast_reads_the_format_the_migration_writes(): void
    {
        $user = User::factory()->create();

        DB::table('users')->where('id', $user->id)->update([
            'map_api_keys' => Crypt::encryptString(json_encode(['carto' => 'migrated-key'])),
            'map_provider' => 'carto',
        ]);

        $user = $user->fresh();
        $this->assertSame(['carto' => 'migrated-key'], $user->map_api_keys);
        $this->assertSame('migrated-key', $user->mapApiKey('carto'));
        $this->assertStringContainsString('?key=migrated-key', $user->mapTiles()['light']);
    }

    public function test_stored_keys_are_not_serialized_with_the_user(): void
    {
        $user = User::factory()->create(['map_api_keys' => ['carto' => 'secret']]);

        $this->assertArrayNotHasKey('map_api_keys', $user->toArray());
        $this->assertStringNotContainsString('secret', json_encode($user->toArray()));
    }
}
