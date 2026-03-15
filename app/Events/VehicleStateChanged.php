<?php

namespace App\Events;

use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VehicleStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
        public VehicleState $state,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('vehicle.' . $this->vehicle->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'vehicle_id' => $this->vehicle->id,
            'state' => $this->state->state,
            'battery_level' => $this->state->battery_level,
            'rated_range' => $this->state->rated_range,
            'speed' => $this->state->speed,
            'latitude' => $this->state->latitude,
            'longitude' => $this->state->longitude,
            'locked' => $this->state->locked,
            'sentry_mode' => $this->state->sentry_mode,
            'inside_temp' => $this->state->inside_temp,
            'outside_temp' => $this->state->outside_temp,
            'timestamp' => $this->state->timestamp->toIso8601String(),
        ];
    }
}
