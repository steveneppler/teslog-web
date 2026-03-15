<?php

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserConversionTest extends TestCase
{
    public function test_convert_temp_celsius(): void
    {
        $user = new User(['temperature_unit' => 'C']);
        $this->assertEquals(25.0, $user->convertTemp(25.0));
    }

    public function test_convert_temp_fahrenheit(): void
    {
        $user = new User(['temperature_unit' => 'F']);
        $this->assertEquals(77.0, $user->convertTemp(25.0));
        $this->assertEquals(32.0, $user->convertTemp(0.0));
        $this->assertEquals(212.0, $user->convertTemp(100.0));
    }

    public function test_convert_temp_null(): void
    {
        $user = new User(['temperature_unit' => 'F']);
        $this->assertNull($user->convertTemp(null));
    }

    public function test_temp_unit_labels(): void
    {
        $this->assertEquals('°C', (new User(['temperature_unit' => 'C']))->tempUnit());
        $this->assertEquals('°F', (new User(['temperature_unit' => 'F']))->tempUnit());
        $this->assertEquals('°C', (new User())->tempUnit());
    }

    public function test_format_temp(): void
    {
        $user = new User(['temperature_unit' => 'F']);
        $this->assertEquals('77°F', $user->formatTemp(25.0));
        $this->assertNull($user->formatTemp(null));
    }

    public function test_convert_distance_miles(): void
    {
        $user = new User(['distance_unit' => 'mi']);
        $this->assertEquals(100.0, $user->convertDistance(100.0));
    }

    public function test_convert_distance_km(): void
    {
        $user = new User(['distance_unit' => 'km']);
        $this->assertEqualsWithDelta(160.934, $user->convertDistance(100.0), 0.01);
    }

    public function test_convert_distance_null(): void
    {
        $user = new User(['distance_unit' => 'km']);
        $this->assertNull($user->convertDistance(null));
    }

    public function test_convert_speed(): void
    {
        $user = new User(['distance_unit' => 'km']);
        $this->assertEqualsWithDelta(96.56, $user->convertSpeed(60.0), 0.01);
    }

    public function test_convert_elevation_feet(): void
    {
        $user = new User(['elevation_unit' => 'ft']);
        $this->assertEqualsWithDelta(328.084, $user->convertElevation(100.0), 0.01);
    }

    public function test_convert_elevation_meters(): void
    {
        $user = new User(['elevation_unit' => 'm']);
        $this->assertEquals(100.0, $user->convertElevation(100.0));
    }

    public function test_convert_elevation_null(): void
    {
        $this->assertNull((new User(['elevation_unit' => 'ft']))->convertElevation(null));
    }

    public function test_convert_efficiency_miles(): void
    {
        $user = new User(['distance_unit' => 'mi']);
        $this->assertEquals(250.0, $user->convertEfficiency(250.0));
    }

    public function test_convert_efficiency_km(): void
    {
        $user = new User(['distance_unit' => 'km']);
        $this->assertEqualsWithDelta(155.34, $user->convertEfficiency(250.0), 0.1);
    }

    public function test_convert_efficiency_null(): void
    {
        $this->assertNull((new User(['distance_unit' => 'km']))->convertEfficiency(null));
    }

    public function test_unit_labels(): void
    {
        $mi = new User(['distance_unit' => 'mi']);
        $km = new User(['distance_unit' => 'km']);

        $this->assertEquals('mi', $mi->distanceUnit());
        $this->assertEquals('km', $km->distanceUnit());
        $this->assertEquals('mph', $mi->speedUnit());
        $this->assertEquals('km/h', $km->speedUnit());
        $this->assertEquals('Wh/mi', $mi->efficiencyUnit());
        $this->assertEquals('Wh/km', $km->efficiencyUnit());
        $this->assertEquals('mi/kWh', $mi->efficiencyUnitAlt());
        $this->assertEquals('km/kWh', $km->efficiencyUnitAlt());
    }

    public function test_elevation_unit_labels(): void
    {
        $this->assertEquals('ft', (new User(['elevation_unit' => 'ft']))->elevationUnit());
        $this->assertEquals('m', (new User(['elevation_unit' => 'm']))->elevationUnit());
    }

    public function test_uses_km_defaults_to_miles(): void
    {
        $this->assertFalse((new User())->usesKm());
    }

    public function test_uses_feet_defaults_to_feet(): void
    {
        $this->assertTrue((new User())->usesFeet());
    }
}
