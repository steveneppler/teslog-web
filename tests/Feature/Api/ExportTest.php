<?php

namespace Tests\Feature\Api;

use App\Models\Charge;
use App\Models\Drive;
use App\Models\User;
use App\Models\Vehicle;
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
}
