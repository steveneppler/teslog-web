<?php

namespace Tests\Unit\Services;

use App\Services\StateDetectionService;
use PHPUnit\Framework\TestCase;

class StateDetectionServiceTest extends TestCase
{
    private StateDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StateDetectionService();
    }

    public function test_detect_driving_by_speed(): void
    {
        $state = $this->service->detectState(['speed' => 30], 'idle');
        $this->assertEquals('driving', $state);
    }

    public function test_detect_driving_by_gear_d(): void
    {
        $state = $this->service->detectState(['speed' => 0, 'gear' => 'D'], 'idle');
        $this->assertEquals('driving', $state);
    }

    public function test_detect_driving_by_gear_r(): void
    {
        $state = $this->service->detectState(['speed' => 0, 'gear' => 'R'], 'idle');
        $this->assertEquals('driving', $state);
    }

    public function test_detect_charging_state(): void
    {
        $state = $this->service->detectState(['speed' => 0, 'charge_state' => 'Charging'], 'idle');
        $this->assertEquals('charging', $state);
    }

    public function test_detect_charging_fleet_telemetry_enable(): void
    {
        $state = $this->service->detectState(['speed' => 0, 'charge_state' => 'Enable'], 'idle');
        $this->assertEquals('charging', $state);
    }

    public function test_transition_from_driving_to_idle(): void
    {
        $state = $this->service->detectState(['speed' => 0], 'driving');
        $this->assertEquals('idle', $state);
    }

    public function test_transition_from_charging_to_idle(): void
    {
        $state = $this->service->detectState(['speed' => 0, 'charge_state' => 'Complete'], 'charging');
        $this->assertEquals('idle', $state);
    }

    public function test_transition_from_sleeping_to_idle(): void
    {
        $state = $this->service->detectState(['speed' => 0], 'sleeping');
        $this->assertEquals('idle', $state);
    }

    public function test_remain_idle(): void
    {
        $state = $this->service->detectState(['speed' => 0], 'idle');
        $this->assertEquals('idle', $state);
    }

    public function test_driving_takes_priority_over_charging(): void
    {
        $state = $this->service->detectState([
            'speed' => 60,
            'charge_state' => 'Charging',
        ], 'idle');
        $this->assertEquals('driving', $state);
    }

    public function test_empty_snapshot_defaults_to_idle(): void
    {
        $state = $this->service->detectState([], 'unknown');
        $this->assertEquals('idle', $state);
    }

    public function test_non_numeric_speed_treated_as_zero(): void
    {
        $state = $this->service->detectState(['speed' => 'invalid'], 'idle');
        $this->assertEquals('idle', $state);
    }

    public function test_detect_charging_by_power_when_charge_state_is_idle(): void
    {
        // Tesla Fleet Telemetry reports charge_state='Idle' during the bulk of
        // an active Supercharger session, while charger_power stays >100 kW.
        // The real-time path must not trust charge_state alone.
        $state = $this->service->detectState([
            'speed' => 0,
            'gear' => 'P',
            'charge_state' => 'Idle',
            'charger_power' => 139,
        ], 'driving');
        $this->assertEquals('charging', $state);
    }

    public function test_detect_charging_by_power_with_no_charge_state(): void
    {
        $state = $this->service->detectState([
            'speed' => 0,
            'charger_power' => 50,
        ], 'driving');
        $this->assertEquals('charging', $state);
    }

    public function test_low_charger_power_not_charging(): void
    {
        // Idle-bus noise (~0.04 kW) must not be misread as charging.
        $state = $this->service->detectState([
            'speed' => 0,
            'charge_state' => 'Idle',
            'charger_power' => 0.04,
        ], 'driving');
        $this->assertEquals('idle', $state);
    }

    public function test_driving_still_wins_over_charger_power(): void
    {
        $state = $this->service->detectState([
            'speed' => 60,
            'charger_power' => 50,
        ], 'idle');
        $this->assertEquals('driving', $state);
    }
}
