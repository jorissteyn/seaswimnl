<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Domain\ValueObject\Location;

/**
 * Finds the nearest buoy (boei) location for a given RWS location.
 *
 * Wave height (Hm0) is only measured at offshore locations with wave buoys/sensors.
 * Coastal stations like harbors and piers don't have wave measurement equipment -
 * they only measure water temperature and water height (tide level).
 *
 * This service helps find the nearest buoy location that may have wave data,
 * allowing the application to fetch wave height from a nearby offshore buoy
 * when the primary location doesn't have wave measurements.
 *
 * Distance is calculated using the Haversine formula for accurate great-circle
 * distance between two points on Earth given their latitude and longitude.
 */
final readonly class NearestBuoyFinder
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Find the nearest buoy location for a given location.
     *
     * @param Location   $location     The location to find the nearest buoy for
     * @param Location[] $allLocations All available RWS locations
     *
     * @return array{location: Location, distanceKm: float}|null The nearest buoy and distance, or null if none found
     */
    public function findNearestBuoy(Location $location, array $allLocations): ?array
    {
        $buoys = $this->filterBuoys($allLocations);

        if ([] === $buoys) {
            return null;
        }

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($buoys as $buoy) {
            $distance = $this->calculateDistance(
                $location->getLatitude(),
                $location->getLongitude(),
                $buoy->getLatitude(),
                $buoy->getLongitude()
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $buoy;
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
     * Filter locations to only include buoys.
     *
     * @param Location[] $locations
     *
     * @return Location[]
     */
    private function filterBuoys(array $locations): array
    {
        return array_filter(
            $locations,
            fn (Location $loc) => str_contains(strtolower($loc->getName()), 'boei')
        );
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
