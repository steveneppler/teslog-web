<?php

namespace Tests\Feature\Api;

use App\Models\Charge;
use App\Models\Drive;
use App\Models\TelemetryRaw;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);
    }

    /**
     * Parse streamed CSV content into rows using proper CSV parsing.
     */
    private function parseCsv(string $content): array
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    public function test_export_drives_returns_csv(): void
    {
        Drive::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_address' => 'Home',
            'end_address' => 'Work',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/export/drives');

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="teslog-drives.csv"');

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $rows = $this->parseCsv($response->streamedContent());
        $headers = $rows[0];

        $this->assertContains('Date', $headers);
        $this->assertContains('Vehicle', $headers);
        $this->assertContains('From', $headers);
        $this->assertContains('To', $headers);
        $this->assertContains('Energy (kWh)', $headers);
        $this->assertContains('Tag', $headers);
        $this->assertContains('Notes', $headers);
        // Header + 1 data row
        $this->assertCount(2, $rows);
    }

    public function test_export_charges_returns_csv(): void
    {
        Charge::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'address' => 'Supercharger',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/export/charges');

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="teslog-charges.csv"');

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $rows = $this->parseCsv($response->streamedContent());
        $headers = $rows[0];

        $this->assertContains('Date', $headers);
        $this->assertContains('Vehicle', $headers);
        $this->assertContains('Location', $headers);
        $this->assertContains('Type', $headers);
        $this->assertContains('Energy Added (kWh)', $headers);
        $this->assertContains('Cost', $headers);
        $this->assertCount(2, $rows);
    }

    public function test_export_drives_date_range_filtering(): void
    {
        Drive::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'started_at' => '2024-06-15 10:00:00',
            'start_address' => 'A',
            'end_address' => 'B',
        ]);
        Drive::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'started_at' => '2024-01-01 10:00:00',
            'start_address' => 'C',
            'end_address' => 'D',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/export/drives?from=2024-06-01&to=2024-06-30');

        $rows = $this->parseCsv($response->streamedContent());

        // Header + 1 matching drive
        $this->assertCount(2, $rows);
    }

    public function test_export_drives_requires_auth(): void
    {
        $this->getJson('/api/v1/export/drives')->assertStatus(401);
    }

    public function test_export_charges_requires_auth(): void
    {
        $this->getJson('/api/v1/export/charges')->assertStatus(401);
    }

    public function test_export_drives_excludes_other_users(): void
    {
        Drive::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_address' => 'Home',
            'end_address' => 'Work',
        ]);
        Drive::factory()->create(); // another user's drive

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/export/drives');

        $rows = $this->parseCsv($response->streamedContent());

        // Header + 1 own drive
        $this->assertCount(2, $rows);
    }

    private function debugUser(): User
    {
        return User::factory()->create(['debug_mode' => true]);
    }

    public function test_export_debug_streams_envelope_with_both_tables(): void
    {
        $user = $this->debugUser();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => '2025-01-15 12:00:00',
        ]);
        TelemetryRaw::create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => '2025-01-15 12:00:05',
            'field_name' => 'BatteryLevel',
            'value_numeric' => 87.0,
            'processed' => true,
        ]);

        $response = $this->actingAs($user)->get('/export/debug?'.http_build_query([
            'vehicle_id' => $vehicle->id,
            'from' => '2025-01-15T00:00:00Z',
            'to' => '2025-01-15T23:59:59Z',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression(
            '/attachment; filename=teslog-debug-[A-Za-z0-9_-]+-\d{8}-\d{6}\.json/',
            $response->headers->get('Content-Disposition')
        );

        $payload = json_decode($response->streamedContent(), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertEqualsCanonicalizing(
            ['exported_at', 'app_version', 'filters', 'vehicle', 'processed_states', 'raw_telemetry'],
            array_keys($payload)
        );
        $this->assertSame($vehicle->id, $payload['filters']['vehicle_id']);
        $this->assertSame($vehicle->id, $payload['vehicle']['id']);
        $this->assertCount(1, $payload['processed_states']);
        $this->assertCount(1, $payload['raw_telemetry']);
        $this->assertSame('BatteryLevel', $payload['raw_telemetry'][0]['field_name']);
    }

    public function test_export_debug_filters_by_date_range(): void
    {
        $user = $this->debugUser();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => '2025-01-15 12:00:00',
        ]);
        VehicleState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => '2024-06-01 12:00:00',
        ]);
        TelemetryRaw::create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => '2025-01-15 12:00:01',
            'field_name' => 'F',
            'processed' => true,
        ]);
        TelemetryRaw::create([
            'vehicle_id' => $vehicle->id,
            'timestamp' => '2024-06-01 12:00:01',
            'field_name' => 'F',
            'processed' => true,
        ]);

        $response = $this->actingAs($user)->get('/export/debug?'.http_build_query([
            'vehicle_id' => $vehicle->id,
            'from' => '2025-01-01T00:00:00Z',
            'to' => '2025-01-31T23:59:59Z',
        ]));

        $payload = json_decode($response->streamedContent(), true);
        $this->assertCount(1, $payload['processed_states']);
        $this->assertCount(1, $payload['raw_telemetry']);
    }

    public function test_export_debug_excludes_other_users_data(): void
    {
        $user = $this->debugUser();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        VehicleState::factory()->create(['vehicle_id' => $vehicle->id, 'timestamp' => '2025-01-15 12:00:00']);

        // Another user's vehicle + state should not appear when no vehicle_id filter is set
        $otherVehicle = Vehicle::factory()->create();
        VehicleState::factory()->create(['vehicle_id' => $otherVehicle->id, 'timestamp' => '2025-01-15 12:00:00']);

        $response = $this->actingAs($user)->get('/export/debug?'.http_build_query([
            'from' => '2025-01-01T00:00:00Z',
            'to' => '2025-01-31T23:59:59Z',
        ]));

        $payload = json_decode($response->streamedContent(), true);
        $this->assertCount(1, $payload['processed_states']);
        $this->assertSame($vehicle->id, $payload['processed_states'][0]['vehicle_id']);
    }

    public function test_export_debug_rejects_unknown_vehicle_id(): void
    {
        $user = $this->debugUser();
        $otherVehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->get('/export/debug?vehicle_id='.$otherVehicle->id)
            ->assertNotFound();
    }

    public function test_export_debug_requires_debug_mode(): void
    {
        $user = User::factory()->create(['debug_mode' => false]);

        $this->actingAs($user)->get('/export/debug')->assertForbidden();
    }

    public function test_export_debug_requires_auth(): void
    {
        $this->get('/export/debug')->assertRedirect(route('login'));
    }

    public function test_export_debug_parses_naive_datetime_in_user_timezone(): void
    {
        // EST user picks "12:00–13:00" on the page; that wall-clock window
        // corresponds to 17:00–18:00 UTC. The stored row at 17:30 UTC must be
        // included; the row at 12:30 UTC (= 07:30 EST) must not be.
        $user = User::factory()->create(['debug_mode' => true, 'timezone' => 'America/New_York']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        VehicleState::factory()->create(['vehicle_id' => $vehicle->id, 'timestamp' => '2025-01-15 17:30:00']);
        VehicleState::factory()->create(['vehicle_id' => $vehicle->id, 'timestamp' => '2025-01-15 12:30:00']);

        $response = $this->actingAs($user)->get('/export/debug?'.http_build_query([
            'vehicle_id' => $vehicle->id,
            'from' => '2025-01-15T12:00',
            'to' => '2025-01-15T13:00',
        ]));

        $payload = json_decode($response->streamedContent(), true);
        $this->assertCount(1, $payload['processed_states']);
        $this->assertSame('2025-01-15T17:30:00.000000Z', $payload['processed_states'][0]['timestamp']);
    }
}
