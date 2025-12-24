<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Domain\ValueObject\Location;

/**
 * Finds the nearest location with wave height (Hm0) measurements.
 *
 * Wave height (Hm0) is only measured at certain stations with wave sensors.
 * This service finds the nearest station that has Hm0 data available,
 * allowing the application to fetch wave height from a nearby location
 * when the primary location doesn't have wave measurements.
 *
 * Distance is calculated using the Haversine formula for accurate great-circle
 * distance between two points on Earth given their latitude and longitude.
 */
final readonly class NearestBuoyFinder
{
    private const EARTH_RADIUS_KM = 6371.0;
    private const WAVE_HEIGHT_GROOTHEID = 'Hm0';

    /**
     * Find the nearest location with wave height (Hm0) measurements.
     *
     * @param Location   $location     The location to find the nearest wave station for
     * @param Location[] $allLocations All available RWS locations
     *
     * @return array{location: Location, distanceKm: float}|null The nearest wave station and distance, or null if none found
     */
    public function findNearest(Location $location, array $allLocations): ?array
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($allLocations as $candidate) {
            // Skip the same location
            if ($candidate->getId() === $location->getId()) {
                continue;
            }

            // Only consider locations with wave height data
            if (!\in_array(self::WAVE_HEIGHT_GROOTHEID, $candidate->getGrootheden(), true)) {
                continue;
            }

            $distance = $this->calculateDistance(
                $location->getLatitude(),
                $location->getLongitude(),
                $candidate->getLatitude(),
                $candidate->getLongitude()
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $candidate;
            }
        }

        if (null === $nearest) {
            return null;
        }

        return [
            'location' => $nearest,
            'distanceKm' => round($minDistance, 2),
        ];
    }

    /**
     * Calculate the distance between two points using the Haversine formula.
     *
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
