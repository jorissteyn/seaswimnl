<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Infrastructure\Service\LocationBlacklist;

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
final readonly class NearestRwsLocationFinder
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private LocationBlacklist $blacklist,
    ) {
    }

    /**
     * Find the nearest location with the specified measurement capability.
     *
     * @param RwsLocation   $location     The location to find the nearest station for
     * @param RwsLocation[] $allLocations All available RWS locations
     * @param string        $capability   The grootheid code to search for (e.g., 'Hm0', 'Tm02', 'Th3')
     *
     * @return array{location: RwsLocation, distanceKm: float}|null The nearest station and distance, or null if none found
     */
    public function findNearest(RwsLocation $location, array $allLocations, string $capability): ?array
    {
        $candidates = $this->findNearestCandidates($location, $allLocations, $capability, 1);

        return $candidates[0] ?? null;
    }

    /**
     * Find the nearest locations with the specified measurement capability, sorted by distance.
     *
     * @param RwsLocation   $location     The location to find the nearest stations for
     * @param RwsLocation[] $allLocations All available RWS locations
     * @param string        $capability   The grootheid code to search for (e.g., 'Hm0', 'Tm02', 'Th3', 'WATHTE')
     * @param int           $limit        Maximum number of candidates to return
     *
     * @return array<int, array{location: RwsLocation, distanceKm: float}> Stations sorted by distance
     */
    public function findNearestCandidates(RwsLocation $location, array $allLocations, string $capability, int $limit = 5): array
    {
        $candidates = [];
        $sourceWaterType = $location->getWaterBodyType();

        foreach ($allLocations as $candidate) {
            // Skip the same location
            if ($candidate->getId() === $location->getId()) {
                continue;
            }

            // Skip blacklisted locations (stale data)
            if ($this->blacklist->isBlacklisted($candidate->getId())) {
                continue;
            }

            // Only match locations with the same water body type
            // Skip unknown types - they shouldn't be used as fallbacks
            if ($candidate->getWaterBodyType() !== $sourceWaterType
                || RwsLocation::WATER_TYPE_UNKNOWN === $candidate->getWaterBodyType()) {
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

            $candidates[] = [
                'location' => $candidate,
                'distanceKm' => round($distance, 2),
            ];
        }

        // Sort by distance
        usort($candidates, fn ($a, $b) => $a['distanceKm'] <=> $b['distanceKm']);

        return \array_slice($candidates, 0, $limit);
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
