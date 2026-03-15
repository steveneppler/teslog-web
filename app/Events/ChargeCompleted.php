<?php

namespace App\Events;

use App\Models\Charge;
use App\Models\Vehicle;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChargeCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
        public Charge $charge,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('vehicle.' . $this->vehicle->id)];
    }
}
