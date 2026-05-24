<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\Distributor;

/**
 * Tells whether a (lat, lng) point falls inside a distributor's
 * declared sales territory. Falls back to a centroid-radius check
 * when only the radius is set; falls back to "always true" when no
 * geofence is declared at all (distributor has no territorial
 * restriction).
 */
class GeofenceChecker
{
    public function contains(Distributor $distributor, float $lat, float $lng): bool
    {
        $polygon = $distributor->geofence_polygon;
        if (is_array($polygon) && $this->isValidPolygon($polygon)) {
            return $this->pointInPolygon($lat, $lng, $polygon);
        }

        if ($distributor->geofence_radius_meters && $distributor->centroid_lat !== null && $distributor->centroid_lng !== null) {
            return $this->haversineMeters(
                (float) $distributor->centroid_lat,
                (float) $distributor->centroid_lng,
                $lat,
                $lng,
            ) <= $distributor->geofence_radius_meters;
        }

        return true;
    }

    /**
     * Ray-casting point-in-polygon. Polygon is the GeoJSON convention:
     * [[lng, lat], [lng, lat], ...], first == last.
     *
     * @param  array<int, array<int, float>>|array{type: string, coordinates: array<int, array<int, array<int, float>>>}  $polygon
     */
    protected function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        // Accept both raw ring + full GeoJSON envelope.
        $ring = $polygon['coordinates'][0] ?? $polygon;
        if (! is_array($ring)) {
            return true;
        }

        $inside = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = [$ring[$i][0] ?? 0.0, $ring[$i][1] ?? 0.0];
            [$xj, $yj] = [$ring[$j][0] ?? 0.0, $ring[$j][1] ?? 0.0];

            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  array<mixed>  $polygon
     */
    protected function isValidPolygon(array $polygon): bool
    {
        $ring = $polygon['coordinates'][0] ?? $polygon;

        return is_array($ring) && count($ring) >= 3;
    }

    protected function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth * $c;
    }
}
