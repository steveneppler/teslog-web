<?php

namespace App\Events;

use App\Models\Drive;
use App\Models\Vehicle;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriveCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
        public Drive $drive,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('vehicle.' . $this->vehicle->id)];
    }
}
