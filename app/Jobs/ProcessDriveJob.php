<?php

namespace App\Jobs;

use App\Events\DriveCompleted;
use App\Models\Drive;
use App\Models\DrivePoint;
use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Services\ElevationService;
use App\Services\GeocodingService;
use App\Services\PlaceMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $vehicleId,
        public \DateTimeInterface $endedAt,
    ) {}

    public function handle(GeocodingService $geocoding, PlaceMatchingService $placeMatching, ElevationService $elevation): void
    {
        $vehicle = Vehicle::find($this->vehicleId);
        if (! $vehicle) {
            return;
        }

        // Find the most recent driving state at or before endedAt
        $lastDriving = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('state', 'driving')
            ->where('timestamp', '<=', $this->endedAt)
            ->orderByDesc('timestamp')
            ->first();

        if (! $lastDriving) {
            return;
        }

        // Find the last non-driving state before this driving block
        $lastNonDriving = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('timestamp', '<', $lastDriving->timestamp)
            ->where('state', '!=', 'driving')
            ->orderByDesc('timestamp')
            ->first();

        $lookbackStart = $lastNonDriving?->timestamp ?? $lastDriving->timestamp->copy()->subHours(24);

        // Get all driving states in this session
        $states = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('state', 'driving')
            ->where('timestamp', '>', $lookbackStart)
            ->where('timestamp', '<=', $this->endedAt)
            ->orderBy('timestamp')
            ->get();

        if ($states->count() < 2) {
            return;
        }

        $first = $states->first();
        $last = $states->last();

        // Look up the nearest non-driving state before the drive for true start location
        $preDriveState = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('timestamp', '<', $first->timestamp)
            ->where('state', '!=', 'driving')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('timestamp')
            ->first();

        // Look up the settled end location: use the LAST idle state within a window
        // after the drive ends (before the next driving segment or 5 minutes, whichever
        // comes first). The first idle state often has unsettled GPS from the car still
        // rolling to a stop.
        $windowEnd = $last->timestamp->copy()->addMinutes(5);
        $nextDrivingStart = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('timestamp', '>', $last->timestamp)
            ->where('state', 'driving')
            ->orderBy('timestamp')
            ->value('timestamp');
        if ($nextDrivingStart && $nextDrivingStart->lt($windowEnd)) {
            $windowEnd = $nextDrivingStart;
        }

        $postDriveState = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('timestamp', '>', $last->timestamp)
            ->where('timestamp', '<', $windowEnd)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('state', '!=', 'driving')
            ->orderByDesc('timestamp')
            ->first();

        // Use pre/post states for location if available and within 10 minutes
        $startState = $preDriveState && $first->timestamp->diffInMinutes($preDriveState->timestamp) <= 10
            ? $preDriveState : $first;
        $endState = $postDriveState && $last->timestamp->diffInMinutes($postDriveState->timestamp) <= 10
            ? $postDriveState : $last;

        // Calculate distance from odometer
        $distance = null;
        if ($first->odometer && $last->odometer) {
            $distance = $last->odometer - $first->odometer;
        }

        // Calculate energy used
        $energyUsed = null;
        if ($first->energy_remaining && $last->energy_remaining) {
            $energyUsed = $first->energy_remaining - $last->energy_remaining;
        }

        // Calculate efficiency (Wh/mi)
        $efficiency = null;
        if ($energyUsed && $distance && $distance > 0) {
            $efficiency = ($energyUsed * 1000) / $distance;
        }

        // Geocode addresses
        $startAddress = $geocoding->reverse($startState->latitude, $startState->longitude);
        $endAddress = $geocoding->reverse($endState->latitude, $endState->longitude);

        // Match places
        $startPlace = $placeMatching->findMatch($vehicle->user_id, $startState->latitude, $startState->longitude);
        $endPlace = $placeMatching->findMatch($vehicle->user_id, $endState->latitude, $endState->longitude);

        // Auto-tag from places
        $tag = $startPlace?->auto_tag ?? $endPlace?->auto_tag;

        $drive = Drive::create([
            'vehicle_id' => $this->vehicleId,
            'started_at' => $first->timestamp,
            'ended_at' => $this->endedAt,
            'distance' => $distance,
            'energy_used_kwh' => $energyUsed,
            'efficiency' => $efficiency,
            'start_latitude' => $startState->latitude,
            'start_longitude' => $startState->longitude,
            'end_latitude' => $endState->latitude,
            'end_longitude' => $endState->longitude,
            'start_address' => $startAddress,
            'end_address' => $endAddress,
            'start_place_id' => $startPlace?->id,
            'end_place_id' => $endPlace?->id,
            'start_battery_level' => $first->battery_level,
            'end_battery_level' => $last->battery_level,
            'start_rated_range' => $first->rated_range,
            'end_rated_range' => $last->rated_range,
            'start_odometer' => $first->odometer,
            'end_odometer' => $last->odometer,
            'max_speed' => $states->max('speed'),
            'avg_speed' => $states->avg('speed'),
            'outside_temp_avg' => $states->avg('outside_temp'),
            'tag' => $tag,
        ]);

        // Create GPS breadcrumbs — include pre/post drive states for complete route
        $allPoints = collect();

        if ($startState->id !== $first->id) {
            $allPoints->push($startState);
        }
        $allPoints = $allPoints->concat($states);
        if ($endState->id !== $last->id) {
            $allPoints->push($endState);
        }

        $points = $allPoints
            ->filter(fn ($s) => $s->latitude && $s->longitude)
            ->map(fn ($s) => [
                'drive_id' => $drive->id,
                'timestamp' => $s->timestamp,
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
                'altitude' => $s->elevation,
                'speed' => $s->speed,
                'power' => $s->power,
                'battery_level' => $s->battery_level,
                'heading' => $s->heading,
            ])->values()->all();

        foreach (array_chunk($points, 500) as $chunk) {
            DrivePoint::insert($chunk);
        }

        // Backfill elevation from Open-Meteo if not provided by telemetry
        $drivePoints = DrivePoint::where('drive_id', $drive->id)
            ->whereNull('altitude')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($drivePoints->isNotEmpty()) {
            $coordinates = $drivePoints->map(fn ($p) => [$p->latitude, $p->longitude])->all();
            $elevations = $elevation->lookup($coordinates);

            foreach ($drivePoints as $i => $point) {
                if (($elevations[$i] ?? null) !== null) {
                    $point->altitude = $elevations[$i];
                    $point->save();
                }
            }
        }

        DriveCompleted::dispatch($vehicle, $drive);
    }
}
