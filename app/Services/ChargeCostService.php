<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Place;
use Illuminate\Support\Carbon;

class ChargeCostService
{
    /**
     * Calculate and save the cost for a charge based on its matched place.
     * Uses ToU rates if available, otherwise falls back to flat rate.
     */
    public function calculateCost(Charge $charge, ?string $userTz = 'UTC'): ?float
    {
        if (! $charge->place_id || ! $charge->energy_added_kwh || $charge->energy_added_kwh <= 0) {
            return null;
        }

        $place = $charge->place()->with('touRates')->first();
        if (! $place) {
            return null;
        }

        $touRates = $place->touRates;

        if ($touRates->isNotEmpty()) {
            $cost = $this->calculateTouCost($charge, $touRates, $userTz);
        } elseif ($place->electricity_cost_per_kwh) {
            $cost = round($charge->energy_added_kwh * $place->electricity_cost_per_kwh, 2);
        } else {
            return null;
        }

        $charge->update(['cost' => $cost]);

        return $cost;
    }

    /**
     * Calculate cost using time-of-use rates by splitting the charge
     * duration across rate windows. Assumes constant power delivery.
     */
    private function calculateTouCost(Charge $charge, $touRates, string $userTz): float
    {
        $start = $charge->started_at->copy()->tz($userTz);
        $end = $charge->ended_at->copy()->tz($userTz);
        $totalSeconds = max(1, $start->diffInSeconds($end));
        $totalCost = 0;
        $coveredSeconds = 0;

        // Walk through the charge in minute increments, checking which rate applies
        $cursor = $start->copy();
        while ($cursor->lt($end)) {
            $nextMinute = $cursor->copy()->addMinute();
            if ($nextMinute->gt($end)) {
                $nextMinute = $end->copy();
            }

            $segmentSeconds = $cursor->diffInSeconds($nextMinute);
            $dayOfWeek = (int) $cursor->dayOfWeek; // 0=Sunday, 6=Saturday
            $timeStr = $cursor->format('H:i');

            // Find matching ToU rate for this minute
            $rate = $touRates->first(function ($r) use ($dayOfWeek, $timeStr) {
                return (int) $r->day_of_week === $dayOfWeek
                    && $timeStr >= substr($r->start_time, 0, 5)
                    && $timeStr <= substr($r->end_time, 0, 5);
            });

            if ($rate) {
                $fractionOfCharge = $segmentSeconds / $totalSeconds;
                $segmentEnergy = $charge->energy_added_kwh * $fractionOfCharge;
                $totalCost += $segmentEnergy * $rate->rate_per_kwh;
                $coveredSeconds += $segmentSeconds;
            }

            $cursor = $nextMinute;
        }

        // If some time wasn't covered by any ToU rate, use flat rate as fallback
        if ($coveredSeconds < $totalSeconds) {
            $place = $charge->place;
            $uncoveredFraction = ($totalSeconds - $coveredSeconds) / $totalSeconds;
            $fallbackRate = $place->electricity_cost_per_kwh ?? 0;
            $totalCost += $charge->energy_added_kwh * $uncoveredFraction * $fallbackRate;
        }

        return round($totalCost, 2);
    }
}
