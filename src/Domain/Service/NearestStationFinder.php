<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Domain\ValueObject\Location;

/**
 * Finds the nearest location with a specific measurement capability.
 *
 * Some measurements (wave height, wave period, wave direction) are only available
 * at certain stations. This service finds the nearest station that has the
 * specified capability, allowing the application to fetch data from a nearby
 * location when the primary location doesn't have the measurement.
 *
 * Distance is calculated using the Haversine formula for accurate great-circle
 * distance between two points on Earth given their latitude and longitude.
 */
final readonly class NearestStationFinder
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Find the nearest location with the specified measurement capability.
     *
     * @param Location   $location     The location to find the nearest station for
     * @param Location[] $allLocations All available RWS locations
     * @param string     $capability   The grootheid code to search for (e.g., 'Hm0', 'Tm02', 'Th3')
     *
     * @return array{location: Location, distanceKm: float}|null The nearest station and distance, or null if none found
     */
    public function findNearest(Location $location, array $allLocations, string $capability): ?array
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($allLocations as $candidate) {
            // Skip the same location
            if ($candidate->getId() === $location->getId()) {
                continue;
            }

            // Only consider locations with the requested capability
            if (!\in_array($capability, $candidate->getGrootheden(), true)) {
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
