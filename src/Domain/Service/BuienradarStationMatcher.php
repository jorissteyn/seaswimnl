<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Application\Port\BuienradarStationRepositoryInterface;
use Seaswim\Domain\ValueObject\BuienradarStation;
use Seaswim\Domain\ValueObject\Location;

/**
 * Finds the nearest Buienradar weather station for a given RWS location.
 *
 * Uses the Haversine formula to calculate the great-circle distance
 * between the RWS location and all Buienradar stations, returning
 * the closest one along with the distance.
 */
final readonly class BuienradarStationMatcher
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private BuienradarStationRepositoryInterface $stationRepository,
    ) {
    }

    /**
     * Find the nearest Buienradar station to the given location.
     *
     * @return array{station: BuienradarStation, distanceKm: float}|null
     */
    public function findNearestStation(Location $location): ?array
    {
        $stations = $this->stationRepository->findAll();

        if ([] === $stations) {
            return null;
        }

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stations as $station) {
            $distance = $this->calculateDistance(
                $location->getLatitude(),
                $location->getLongitude(),
                $station->getLatitude(),
                $station->getLongitude()
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $station;
            }
        }

        if (null === $nearest) {
            return null;
        }

        return [
            'station' => $nearest,
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
