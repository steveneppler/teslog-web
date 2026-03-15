<?php

namespace App\Jobs;

use App\Models\Idle;
use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Services\GeocodingService;
use App\Services\PlaceMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIdleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $vehicleId,
        public \DateTimeInterface $endedAt,
    ) {}

    public function handle(GeocodingService $geocoding, PlaceMatchingService $placeMatching): void
    {
        $vehicle = Vehicle::find($this->vehicleId);
        if (! $vehicle) {
            return;
        }

        // Find the most recent idle state at or before endedAt
        $lastIdle = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('state', 'idle')
            ->where('timestamp', '<=', $this->endedAt)
            ->orderByDesc('timestamp')
            ->first();

        if (! $lastIdle) {
            return;
        }

        // Find the last non-idle state before this idle block
        $lastNonIdle = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('timestamp', '<', $lastIdle->timestamp)
            ->where('state', '!=', 'idle')
            ->orderByDesc('timestamp')
            ->first();

        $lookbackStart = $lastNonIdle?->timestamp ?? $lastIdle->timestamp->copy()->subHours(24);

        $idleStates = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('state', 'idle')
            ->where('timestamp', '>', $lookbackStart)
            ->where('timestamp', '<=', $this->endedAt)
            ->orderBy('timestamp')
            ->get();

        if ($idleStates->count() < 2) {
            return;
        }

        $first = $idleStates->first();
        $last = $idleStates->last();

        // Vampire drain calculation (% per hour)
        $vampireDrain = null;
        $duration = $first->timestamp->diffInHours($last->timestamp);
        if ($duration > 0 && $first->battery_level !== null && $last->battery_level !== null) {
            $vampireDrain = ($first->battery_level - $last->battery_level) / $duration;
        }

        $address = $geocoding->reverse($first->latitude, $first->longitude);
        $place = $placeMatching->findMatch($vehicle->user_id, $first->latitude, $first->longitude);

        Idle::create([
            'vehicle_id' => $this->vehicleId,
            'started_at' => $first->timestamp,
            'ended_at' => $this->endedAt,
            'latitude' => $first->latitude,
            'longitude' => $first->longitude,
            'address' => $address,
            'place_id' => $place?->id,
            'start_battery_level' => $first->battery_level,
            'end_battery_level' => $last->battery_level,
            'vampire_drain_rate' => $vampireDrain,
            'sentry_mode_active' => $idleStates->contains(fn ($s) => $s->sentry_mode),
        ]);
    }
}
