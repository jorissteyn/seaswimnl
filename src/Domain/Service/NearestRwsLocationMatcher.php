<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Infrastructure\Service\LocationBlacklist;

/**
 * Finds the nearest RWS location for a given swimming spot.
 *
 * Uses the Haversine formula to calculate the great-circle distance
 * between the swimming spot and all RWS locations, returning
 * the closest non-blacklisted one along with the distance.
 */
final readonly class NearestRwsLocationMatcher
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private RwsLocationRepositoryInterface $locationRepository,
        private LocationBlacklist $blacklist,
    ) {
    }

    /**
     * Find the nearest RWS location to the given swimming spot.
     *
     * @return array{location: RwsLocation, distanceKm: float}|null
     */
    public function findNearestLocation(SwimmingSpot $spot): ?array
    {
        $locations = $this->locationRepository->findAll();

        if ([] === $locations) {
            return null;
        }

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($locations as $location) {
            // Skip blacklisted locations
            if ($this->blacklist->isBlacklisted($location->getId())) {
                continue;
            }

            $distance = $this->calculateDistance(
                $spot->getLatitude(),
                $spot->getLongitude(),
                $location->getLatitude(),
                $location->getLongitude()
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $location;
            }
        }

        if (null === $nearest) {
            return null;
        }

        return [
            'location' => $nearest,
            'distanceKm' => round($minDistance, 1),
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
