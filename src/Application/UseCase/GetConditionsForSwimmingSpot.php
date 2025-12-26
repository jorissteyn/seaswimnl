<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Seaswim\Application\Port\TidalInfoProviderInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Domain\Entity\CalculatedMetrics;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\Service\ComfortIndexCalculator;
use Seaswim\Domain\Service\NearestRwsLocationFinder;
use Seaswim\Domain\Service\NearestRwsLocationMatcher;
use Seaswim\Domain\Service\SafetyScoreCalculator;
use Seaswim\Domain\Service\WeatherStationMatcher;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Domain\ValueObject\TideInfo;
use Seaswim\Domain\ValueObject\WeatherStation;

final readonly class GetConditionsForSwimmingSpot
{
    private const CAPABILITY_WAVE_HEIGHT = 'Hm0';
    private const CAPABILITY_WAVE_PERIOD = 'Tm02';
    private const CAPABILITY_WAVE_DIRECTION = 'Th3';
    private const CAPABILITY_TIDES = 'WATHTE';

    public function __construct(
        private SwimmingSpotRepositoryInterface $swimmingSpotRepository,
        private RwsLocationRepositoryInterface $locationRepository,
        private WaterConditionsProviderInterface $waterProvider,
        private WeatherConditionsProviderInterface $weatherProvider,
        private TidalInfoProviderInterface $tidalProvider,
        private SafetyScoreCalculator $safetyCalculator,
        private ComfortIndexCalculator $comfortCalculator,
        private NearestRwsLocationMatcher $rwsLocationMatcher,
        private NearestRwsLocationFinder $rwsLocationFinder,
        private WeatherStationMatcher $weatherStationMatcher,
    ) {
    }

    /**
     * @return array{
     *     swimmingSpot: SwimmingSpot,
     *     rwsLocation: array{location: RwsLocation, distanceKm: float}|null,
     *     weatherStation: array{station: WeatherStation, distanceKm: float}|null,
     *     water: WaterConditions|null,
     *     weather: WeatherConditions|null,
     *     tides: TideInfo|null,
     *     metrics: CalculatedMetrics,
     *     errors: array<string, string>,
     *     waveHeightStation: array<string, mixed>|null,
     *     wavePeriodStation: array<string, mixed>|null,
     *     waveDirectionStation: array<string, mixed>|null,
     *     tidalStation: array<string, mixed>|null
     * }|null
     */
    public function execute(string $swimmingSpotId): ?array
    {
        $swimmingSpot = $this->swimmingSpotRepository->findById($swimmingSpotId);

        if (null === $swimmingSpot) {
            return null;
        }

        $errors = [];
        $waveHeightStation = null;
        $wavePeriodStation = null;
        $waveDirectionStation = null;
        $tidalStation = null;

        // Find nearest RWS location for water data
        $rwsLocationResult = $this->rwsLocationMatcher->findNearestLocation($swimmingSpot);
        $rwsLocation = $rwsLocationResult['location'] ?? null;

        // Find nearest weather station
        $weatherStationResult = $this->weatherStationMatcher->findNearestStationForSpot($swimmingSpot);

        // Fetch water conditions from nearest RWS location
        $water = null;
        if (null !== $rwsLocation) {
            $water = $this->waterProvider->getConditions($rwsLocation);
            if (null === $water) {
                $errors['water'] = $this->waterProvider->getLastError() ?? 'Failed to fetch water conditions';
            }
        } else {
            $errors['water'] = 'No RWS location found near this swimming spot';
        }

        // Fetch wave data from nearest stations if not available at primary location
        if (null !== $water && null !== $rwsLocation) {
            if (null === $water->getWaveHeight()->getMeters()) {
                $waveHeightStation = $this->fetchFromNearestStation($rwsLocation, self::CAPABILITY_WAVE_HEIGHT);
            }

            if (null === $water->getWavePeriod() || null === $water->getWavePeriod()->getSeconds()) {
                $wavePeriodStation = $this->fetchFromNearestStation($rwsLocation, self::CAPABILITY_WAVE_PERIOD);
            }

            if (null === $water->getWaveDirection() || null === $water->getWaveDirection()->getDegrees()) {
                $waveDirectionStation = $this->fetchFromNearestStation($rwsLocation, self::CAPABILITY_WAVE_DIRECTION);
            }
        }

        // Fetch weather conditions - use RWS location for weather lookup (keeps existing behavior)
        $weather = null;
        if (null !== $rwsLocation) {
            $weather = $this->weatherProvider->getConditions($rwsLocation);
            if (null === $weather) {
                $errors['weather'] = $this->weatherProvider->getLastError() ?? 'Failed to fetch weather conditions';
            }
        }

        // Fetch tidal data
        $tides = null;
        if (null !== $rwsLocation) {
            $tides = $this->tidalProvider->getTidalInfo($rwsLocation);
            if (null === $tides) {
                // Try to get tidal data from the nearest station with tidal data
                $tidalStation = $this->fetchFromNearestStation($rwsLocation, self::CAPABILITY_TIDES);
                if (null !== $tidalStation) {
                    $tides = $tidalStation['tides'];
                } else {
                    $errors['tides'] = $this->tidalProvider->getLastError() ?? 'Failed to fetch tidal data';
                }
            }
        }

        $safetyScore = $this->safetyCalculator->calculate($water, $weather);
        $comfortIndex = $this->comfortCalculator->calculate($water, $weather);

        return [
            'swimmingSpot' => $swimmingSpot,
            'rwsLocation' => $rwsLocationResult,
            'weatherStation' => $weatherStationResult,
            'water' => $water,
            'weather' => $weather,
            'tides' => $tides,
            'metrics' => new CalculatedMetrics($safetyScore, $comfortIndex),
            'errors' => $errors,
            'waveHeightStation' => $waveHeightStation,
            'wavePeriodStation' => $wavePeriodStation,
            'waveDirectionStation' => $waveDirectionStation,
            'tidalStation' => $tidalStation,
        ];
    }

    /**
     * Fetch data from the nearest station that has the specified capability.
     *
     * @return array<string, mixed>|null
     */
    private function fetchFromNearestStation(RwsLocation $location, string $capability): ?array
    {
        $allLocations = $this->locationRepository->findAll();
        $stationResult = $this->rwsLocationFinder->findNearest($location, $allLocations, $capability);

        if (null === $stationResult) {
            return null;
        }

        $station = $stationResult['location'];

        $baseResult = [
            'id' => $station->getId(),
            'name' => $station->getName(),
            'distanceKm' => $stationResult['distanceKm'],
        ];

        // Tidal data uses the tidal provider, not water conditions
        if (self::CAPABILITY_TIDES === $capability) {
            $tides = $this->tidalProvider->getTidalInfo($station);

            return null === $tides ? null : [
                ...$baseResult,
                'tides' => $tides,
            ];
        }

        // Wave data uses the water conditions provider
        $stationConditions = $this->waterProvider->getConditions($station);

        if (null === $stationConditions) {
            return null;
        }

        $rawMeasurements = $stationConditions->getRawMeasurements();

        switch ($capability) {
            case self::CAPABILITY_WAVE_HEIGHT:
                $value = $stationConditions->getWaveHeight()->getMeters();

                return null === $value ? null : [
                    ...$baseResult,
                    'waveHeight' => $value,
                    'raw' => $rawMeasurements['waveHeight'] ?? null,
                    'measuredAt' => $stationConditions->getMeasuredAt(),
                ];

            case self::CAPABILITY_WAVE_PERIOD:
                $wavePeriod = $stationConditions->getWavePeriod();
                $value = $wavePeriod?->getSeconds();

                return null === $value ? null : [
                    ...$baseResult,
                    'wavePeriod' => $value,
                    'raw' => $rawMeasurements['wavePeriod'] ?? null,
                    'measuredAt' => $stationConditions->getMeasuredAt(),
                ];

            case self::CAPABILITY_WAVE_DIRECTION:
                $waveDirection = $stationConditions->getWaveDirection();
                if (null === $waveDirection) {
                    return null;
                }
                $value = $waveDirection->getDegrees();

                return null === $value ? null : [
                    ...$baseResult,
                    'waveDirection' => $value,
                    'waveDirectionCompass' => $waveDirection->getCompassDirection(),
                    'raw' => $rawMeasurements['waveDirection'] ?? null,
                    'measuredAt' => $stationConditions->getMeasuredAt(),
                ];

            default:
                return null;
        }
    }
}
