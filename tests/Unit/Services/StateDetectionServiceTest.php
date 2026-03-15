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
}
