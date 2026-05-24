<?php

declare(strict_types=1);

use App\Models\Distributor;
use App\Services\Distribution\GeofenceChecker;

function makeGeoDistributor(array $overrides = []): Distributor
{
    $d = new Distributor();
    $d->forceFill(array_merge([
        'name' => 'test',
        'slug' => 'test',
        'organization_id' => 1,
        'status' => Distributor::STATUS_ACTIVE,
        'type' => Distributor::TYPE_PARENT,
    ], $overrides));

    return $d;
}

it('returns true when the distributor has no geofence declared', function () {
    $checker = new GeofenceChecker();
    expect($checker->contains(makeGeoDistributor(), 0.0, 0.0))->toBeTrue();
});

it('returns true for a point inside a declared polygon', function () {
    $checker = new GeofenceChecker();
    // 1° x 1° square at the equator.
    $d = makeGeoDistributor([
        'geofence_polygon' => [
            'type' => 'Polygon',
            'coordinates' => [[
                [0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0], [0.0, 0.0],
            ]],
        ],
    ]);

    expect($checker->contains($d, 0.5, 0.5))->toBeTrue();
});

it('returns false for a point outside a declared polygon', function () {
    $checker = new GeofenceChecker();
    $d = makeGeoDistributor([
        'geofence_polygon' => [
            'type' => 'Polygon',
            'coordinates' => [[
                [0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0], [0.0, 0.0],
            ]],
        ],
    ]);

    expect($checker->contains($d, 5.0, 5.0))->toBeFalse();
});

it('falls back to centroid + radius when no polygon is set', function () {
    $checker = new GeofenceChecker();
    $d = makeGeoDistributor([
        'centroid_lat' => -17.8252,
        'centroid_lng' => 31.0335,
        'geofence_radius_meters' => 1000, // 1km
    ]);

    // Same point - inside.
    expect($checker->contains($d, -17.8252, 31.0335))->toBeTrue();
    // ~10km away - outside.
    expect($checker->contains($d, -17.9, 31.1))->toBeFalse();
});
