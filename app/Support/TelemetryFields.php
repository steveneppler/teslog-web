<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TelemetryFields
{
    public const LOCATION_FIELDS = ['Location', 'OriginLocation', 'DestinationLocation', 'DestinationName', 'RouteLine', 'GpsState', 'GpsHeading'];

    public static function catalog(): array
    {
        return json_decode(file_get_contents(resource_path('data/telemetry-fields.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Preserve the original selection and cadence for existing installations. */
    public static function defaults(): array
    {
        $names = [
            'BatteryLevel', 'ChargeState', 'ChargeLimitSoc', 'ACChargingPower',
            'DCChargingPower', 'ChargerVoltage', 'ChargeAmps', 'EnergyRemaining',
            'IdealBatteryRange', 'RatedRange', 'VehicleSpeed', 'Odometer', 'Gear',
            'Location', 'GpsHeading', 'InsideTemp', 'OutsideTemp', 'Locked',
            'SentryMode', 'HvacACEnabled', 'Version',
        ];

        return array_fill_keys($names, ['interval_seconds' => 30]);
    }

    /** Validate stored preferences as well as user input before sending to Tesla. */
    public static function validate(array $fields): array
    {
        $catalog = self::catalog();
        Validator::make(['names' => array_keys($fields), 'fields' => $fields], [
            'names' => 'array',
            'names.*' => [Rule::in(array_keys($catalog))],
            'fields' => 'array',
            'fields.*' => 'array:interval_seconds,minimum_delta',
            'fields.*.interval_seconds' => 'required|integer|min:1|max:86400',
            'fields.*.minimum_delta' => 'sometimes|numeric|min:0',
        ])->validate();

        foreach ($fields as $name => $options) {
            if (isset($options['minimum_delta'])) {
                Validator::make(['minimum_delta' => $options['minimum_delta']], [
                    'minimum_delta' => Rule::prohibitedIf(! in_array($catalog[$name]['type'], ['real', 'integer']) && $name !== 'Location'),
                ])->validate();
            }
        }

        return array_map(function ($options) {
            $result = ['interval_seconds' => (int) $options['interval_seconds']];
            if (isset($options['minimum_delta'])) {
                $result['minimum_delta'] = (float) $options['minimum_delta'];
            }

            return $result;
        }, $fields);
    }

    public static function forVehicle(?array $preferences, bool $includeLocation = true): array
    {
        $fields = self::validate($preferences ?? self::defaults());

        return $includeLocation ? $fields : array_diff_key($fields, array_flip(self::LOCATION_FIELDS));
    }
}
