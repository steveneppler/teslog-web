<?php

namespace App\Services;

use App\Models\Place;

class PlaceMatchingService
{
    public function findMatch(int $userId, ?float $lat, ?float $lng): ?Place
    {
        if (! $lat || ! $lng) {
            return null;
        }

        $places = Place::where('user_id', $userId)->get();

        $closest = null;
        $closestDistance = PHP_FLOAT_MAX;

        foreach ($places as $place) {
            $distance = $this->haversineDistance($lat, $lng, $place->latitude, $place->longitude);

            if ($distance <= $place->radius_meters && $distance < $closestDistance) {
                $closest = $place;
                $closestDistance = $distance;
            }
        }

        return $closest;
    }

    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
