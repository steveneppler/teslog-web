<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Drive;
use App\Models\Place;
use Illuminate\Console\Command;

class MatchPlaces extends Command
{
    protected $signature = 'teslog:match-places
        {--vehicle= : Vehicle ID (processes all if omitted)}';

    protected $description = 'Match drives and charges to saved places by GPS proximity';

    public function handle(): int
    {
        $places = Place::all();

        if ($places->isEmpty()) {
            $this->info('No places defined. Skipping.');

            return self::SUCCESS;
        }

        $this->info("Matching against {$places->count()} places...");

        $driveQuery = Drive::whereNotNull('start_latitude');
        $chargeQuery = Charge::whereNotNull('latitude');

        if ($vehicleId = $this->option('vehicle')) {
            $driveQuery->where('vehicle_id', $vehicleId);
            $chargeQuery->where('vehicle_id', $vehicleId);
        }

        $drives = $driveQuery->get();
        $charges = $chargeQuery->get();

        $driveMatches = 0;
        foreach ($drives as $drive) {
            $changed = false;

            $startPlace = $this->findMatchingPlace($places, $drive->start_latitude, $drive->start_longitude);
            if ($startPlace?->id !== $drive->start_place_id) {
                $drive->start_place_id = $startPlace?->id;
                $changed = true;
            }

            $endPlace = $this->findMatchingPlace($places, $drive->end_latitude, $drive->end_longitude);
            if ($endPlace?->id !== $drive->end_place_id) {
                $drive->end_place_id = $endPlace?->id;
                $changed = true;
            }

            if ($changed) {
                $drive->save();
                $driveMatches++;
            }
        }

        $chargeMatches = 0;
        foreach ($charges as $charge) {
            $place = $this->findMatchingPlace($places, $charge->latitude, $charge->longitude);
            if ($place?->id !== $charge->place_id) {
                $charge->place_id = $place?->id;
                $charge->save();
                $chargeMatches++;
            }
        }

        $this->info("Updated {$driveMatches} drives, {$chargeMatches} charges.");

        return self::SUCCESS;
    }

    private function findMatchingPlace($places, ?float $lat, ?float $lng): ?Place
    {
        if (! $lat || ! $lng) {
            return null;
        }

        $best = null;
        $bestDist = PHP_FLOAT_MAX;

        foreach ($places as $place) {
            $dist = $this->haversineMeters($lat, $lng, $place->latitude, $place->longitude);
            if ($dist <= $place->radius_meters && $dist < $bestDist) {
                $best = $place;
                $bestDist = $dist;
            }
        }

        return $best;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
