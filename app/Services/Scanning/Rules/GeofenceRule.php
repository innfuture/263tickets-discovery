<?php

declare(strict_types=1);

namespace App\Services\Scanning\Rules;

use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

/**
 * Warns when the scanner's GPS reading is too far from the event
 * venue. Tickets at festivals get screenshotted and shared in WhatsApp
 * groups; a scan attempt 200 km from the venue is almost certainly a
 * remote test or fraud.
 *
 * Geofence radius is configurable via config('scanning.geofence_radius_km').
 * Skips when either side lacks coordinates (don't penalise venues
 * without lat/lng on file).
 */
class GeofenceRule implements FraudRule
{
    public function id(): string
    {
        return 'geofence';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        $event = $context->event;
        if ($event === null || $context->clientLat === null || $context->clientLng === null) {
            return FraudVerdict::allow($this->id());
        }
        if ($event->latitude === null || $event->longitude === null) {
            return FraudVerdict::allow($this->id());
        }

        $radius = (float) config('scanning.geofence_radius_km', 5.0);
        if ($radius <= 0) {
            return FraudVerdict::allow($this->id());
        }

        $distance = $this->haversineKm(
            (float) $event->latitude, (float) $event->longitude,
            $context->clientLat, $context->clientLng,
        );

        if ($distance <= $radius) {
            return FraudVerdict::allow($this->id());
        }

        $km = round($distance, 1);
        $severity = $distance > $radius * 10 ? 5 : 2;
        $outcome = $distance > $radius * 10 ? 'deny' : 'warn';

        if ($outcome === 'deny') {
            return FraudVerdict::deny(
                'far_from_venue',
                "Scan device is {$km} km from the event venue (allowed radius {$radius} km).",
                severity: $severity,
                rule: $this->id(),
            );
        }

        return FraudVerdict::warn(
            'far_from_venue',
            "Scan device is {$km} km from the event venue.",
            severity: $severity,
            rule: $this->id(),
        );
    }

    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
