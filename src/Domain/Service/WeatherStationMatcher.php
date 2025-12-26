<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Application\Port\WeatherStationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Domain\ValueObject\WeatherStation;

/**
 * Finds the nearest weather station for a given location.
 *
 * Uses the Haversine formula to calculate the great-circle distance
 * between the swimming spot and all weather stations, returning
 * the closest one along with the distance.
 */
final readonly class WeatherStationMatcher
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private WeatherStationRepositoryInterface $stationRepository,
    ) {
    }

    /**
     * Find the nearest weather station to the given RWS location.
     *
     * @return array{station: WeatherStation, distanceKm: float}|null
     */
    public function findNearestStation(RwsLocation $location): ?array
    {
        return $this->findNearestByCoordinates($location->getLatitude(), $location->getLongitude());
    }

    /**
     * Find the nearest weather station to the given swimming spot.
     *
     * @return array{station: WeatherStation, distanceKm: float}|null
     */
    public function findNearestStationForSpot(SwimmingSpot $spot): ?array
    {
        return $this->findNearestByCoordinates($spot->getLatitude(), $spot->getLongitude());
    }

    /**
     * Find the nearest weather station to the given coordinates.
     *
     * @return array{station: WeatherStation, distanceKm: float}|null
     */
    private function findNearestByCoordinates(float $latitude, float $longitude): ?array
    {
        $stations = $this->stationRepository->findAll();

        if ([] === $stations) {
            return null;
        }

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stations as $station) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
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
