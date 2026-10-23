<?php

namespace Tests\Feature;

use App\Console\Commands\MqttTelemetrySubscriber;
use App\Http\Controllers\Api\TelemetryIngestController;
use App\Jobs\ProcessTelemetryBatch;
use App\Livewire\TelemetrySettings;
use App\Models\TelemetryRaw;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TeslaAuthService;
use App\Support\TelemetryFields;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class TelemetryPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'tesla.client_id' => 'test', 'tesla.client_secret' => 'test',
            'tesla.redirect_uri' => 'https://example.test/callback',
            'tesla.fleet_telemetry.ca_cert_path' => __FILE__,
        ]);
        Http::preventStrayRequests();
    }

    public function test_catalog_covers_every_published_field_including_advanced_and_structured_signals(): void
    {
        $catalog = TelemetryFields::catalog();
        $this->assertCount(253, $catalog);
        foreach (['ModuleTempMin', 'ModuleTempMax', 'PackVoltage', 'PackCurrent', 'NominalFullPackEnergyKwh', 'SelfDrivingMilesSinceReset', 'DiTorqueActualF', 'RouteLine', 'DoorState'] as $name) {
            $this->assertArrayHasKey($name, $catalog);
        }
        $this->assertEmpty(array_diff_key(TelemetryFields::defaults(), $catalog));
    }

    public function test_default_selection_is_unchanged_and_no_location_filters_every_restricted_field(): void
    {
        $this->assertCount(21, TelemetryFields::forVehicle(null));
        $this->assertSame(['interval_seconds' => 30], TelemetryFields::defaults()['BatteryLevel']);
        $all = array_fill_keys(array_keys(TelemetryFields::catalog()), ['interval_seconds' => 60]);
        $filtered = TelemetryFields::forVehicle($all, false);
        foreach (TelemetryFields::LOCATION_FIELDS as $name) {
            $this->assertArrayNotHasKey($name, $filtered);
        }
        $this->assertCount(count($all) - count(TelemetryFields::LOCATION_FIELDS), $filtered);
    }

    public function test_invalid_configs_cannot_be_sent(): void
    {
        foreach ([
            ['UnknownField' => ['interval_seconds' => 60]],
            ['PackVoltage' => ['interval_seconds' => 0]],
            ['PackVoltage' => ['interval_seconds' => 1.5]],
            ['PackVoltage' => ['interval_seconds' => 86401]],
            ['PackVoltage' => ['interval_seconds' => 60, 'minimum_delta' => -1]],
            ['Locked' => ['interval_seconds' => 60, 'minimum_delta' => 1]],
            ['PackVoltage' => ['interval_seconds' => 60, 'include_fields' => ['Location']]],
        ] as $fields) {
            try {
                TelemetryFields::validate($fields);
                $this->fail('Invalid configuration accepted: '.json_encode($fields));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_owner_can_toggle_legacy_and_new_fields_without_contacting_tesla_until_apply(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        Livewire::test(TelemetrySettings::class)
            ->assertSee('253')
            ->assertSet('fields.PackVoltage.enabled', false)
            ->set('fields.PackVoltage.enabled', true)
            ->set('fields.PackVoltage.interval_seconds', 300)
            ->set('fields.PackVoltage.minimum_delta', 0.1)
            ->set('fields.SentryMode.enabled', false)
            ->call('save')->assertHasNoErrors();
        $fields = $vehicle->fresh()->telemetry_fields;
        $this->assertSame(['interval_seconds' => 300, 'minimum_delta' => 0.1], $fields['PackVoltage']);
        $this->assertArrayNotHasKey('SentryMode', $fields);
        Http::assertNothingSent();
    }

    public function test_all_catalog_fields_can_be_selected_and_saved(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        $rows = array_fill_keys(array_keys(TelemetryFields::catalog()), [
            'enabled' => true, 'interval_seconds' => 60, 'minimum_delta' => '',
        ]);
        Livewire::test(TelemetrySettings::class)->set('fields', $rows)->call('save')->assertHasNoErrors();
        $this->assertCount(253, $vehicle->fresh()->telemetry_fields);
    }

    public function test_unknown_signal_names_are_rejected_even_when_disabled(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        Livewire::test(TelemetrySettings::class)
            ->set('fields.UnknownField', ['enabled' => false, 'interval_seconds' => 60, 'minimum_delta' => ''])
            ->call('save')->assertHasErrors('fields');
        $this->assertNull($vehicle->fresh()->telemetry_fields);
    }

    public function test_apply_sends_saved_selection_and_does_not_silently_save_unsaved_edits(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'user_id' => $user->id, 'tesla_access_token' => 'test-token',
            'telemetry_fields' => ['ModuleTempMin' => ['interval_seconds' => 120]],
        ]);
        $this->actingAs($user);
        Http::fake(['*' => Http::response(['response' => ['updated_vehicles' => 1]])]);
        Livewire::test(TelemetrySettings::class)
            ->set('fields.ModuleTempMin.enabled', false)
            ->call('apply')->assertHasNoErrors()->assertSee('Saved preferences sent');
        Http::assertSent(fn ($request) => $request['config']['fields'] === [
            'ModuleTempMin' => ['interval_seconds' => 120],
        ]);
    }

    public function test_mqtt_preserves_structured_location_and_long_signal_values(): void
    {
        Queue::fake();
        $vehicle = Vehicle::factory()->create();
        $subscriber = new MqttTelemetrySubscriber;
        $subscriber->setOutput(new OutputStyle(
            new ArrayInput([]),
            new BufferedOutput,
        ));
        $process = new \ReflectionMethod($subscriber, 'processMessage');
        $process->invoke($subscriber, "teslog/{$vehicle->vin}/v/Location", '{"latitude":42.3,"longitude":-7.8}');
        $process->invoke($subscriber, "teslog/{$vehicle->vin}/v/RouteLine", str_repeat('route', 20000));
        (new \ReflectionMethod($subscriber, 'flush'))->invoke($subscriber);
        $this->assertDatabaseHas('telemetry_raw', ['field_name' => 'Latitude', 'value_numeric' => 42.3]);
        $this->assertDatabaseHas('telemetry_raw', ['field_name' => 'Location', 'value_string' => '{"latitude":42.3,"longitude":-7.8}']);
        $this->assertSame(100000, strlen(TelemetryRaw::where('field_name', 'RouteLine')->firstOrFail()->value_string));
    }

    public function test_preferences_are_isolated_per_vehicle_and_other_users_cannot_select_them(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id, 'telemetry_fields' => []]);
        $other = Vehicle::factory()->create();
        $this->actingAs($user);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(TelemetrySettings::class)
            ->assertSet('fields.BatteryLevel.enabled', false)
            ->set('vehicleId', (string) $other->id);
        $this->assertSame([], $vehicle->fresh()->telemetry_fields);
        $this->assertNull($other->fresh()->telemetry_fields);
    }

    public function test_configure_and_repair_paths_use_persisted_selection(): void
    {
        $vehicle = Vehicle::factory()->create(['telemetry_fields' => [
            'PackVoltage' => ['interval_seconds' => 300, 'minimum_delta' => 0.1],
            'RouteLine' => ['interval_seconds' => 60],
        ]]);
        Http::fake(['*' => Http::response(['response' => ['updated_vehicles' => 1]])]);
        app(TeslaAuthService::class)->configureFleetTelemetry('token', $vehicle->vin, false);
        Http::assertSent(fn ($request) => $request['config']['fields'] === [
            'PackVoltage' => ['interval_seconds' => 300, 'minimum_delta' => 0.1],
        ]);
    }

    public function test_all_disabled_removes_telemetry_instead_of_restoring_defaults(): void
    {
        $vehicle = Vehicle::factory()->create(['telemetry_fields' => []]);
        Http::fake(['*' => Http::response(['response' => true])]);
        app(TeslaAuthService::class)->configureFleetTelemetry('token', $vehicle->vin);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), $vehicle->vin.'/fleet_telemetry_config'));
        Http::assertSentCount(1);
    }

    public function test_tesla_rejection_is_not_reported_as_success(): void
    {
        $vehicle = Vehicle::factory()->create();
        Http::fake(['*' => Http::response(['response' => ['skipped_vehicles' => ['missing_key' => [$vehicle->vin]]]])]);
        $this->expectException(\RuntimeException::class);
        app(TeslaAuthService::class)->configureFleetTelemetry('token', $vehicle->vin);
    }

    public function test_structured_and_long_raw_values_are_preserved_for_export(): void
    {
        Queue::fake();
        $vehicle = Vehicle::factory()->create();
        $value = ['doors' => ['front_left' => true], 'route' => str_repeat('abcd', 1000)];
        $request = Request::create('/api/telemetry/ingest', 'POST', ['data' => [[
            'vin' => $vehicle->vin, 'created_at' => now()->toIso8601String(),
            'data' => [['key' => 'DoorState', 'value' => $value], ['key' => 'Locked', 'value' => true]],
        ]]]);
        app(TelemetryIngestController::class)->ingest($request);
        $raw = TelemetryRaw::where('field_name', 'DoorState')->firstOrFail();
        $this->assertSame($value, json_decode($raw->value_string, true));
        $this->assertSame('true', TelemetryRaw::where('field_name', 'Locked')->firstOrFail()->value_string);
        Queue::assertPushed(ProcessTelemetryBatch::class);
    }
}
