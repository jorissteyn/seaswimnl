<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\TidalInfoProviderInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Domain\Entity\CalculatedMetrics;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\Service\ComfortIndexCalculator;
use Seaswim\Domain\Service\NearestRwsLocationFinder;
use Seaswim\Domain\Service\SafetyScoreCalculator;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\TideInfo;

final readonly class GetConditionsForLocation
{
    private const CAPABILITY_WAVE_HEIGHT = 'Hm0';
    private const CAPABILITY_WAVE_PERIOD = 'Tm02';
    private const CAPABILITY_WAVE_DIRECTION = 'Th3';
    private const CAPABILITY_TIDES = 'WATHTE';

    public function __construct(
        private RwsLocationRepositoryInterface $locationRepository,
        private WaterConditionsProviderInterface $waterProvider,
        private WeatherConditionsProviderInterface $weatherProvider,
        private TidalInfoProviderInterface $tidalProvider,
        private SafetyScoreCalculator $safetyCalculator,
        private ComfortIndexCalculator $comfortCalculator,
        private NearestRwsLocationFinder $rwsLocationFinder,
    ) {
    }

    /**
     * @return array{water: WaterConditions|null, weather: WeatherConditions|null, tides: TideInfo|null, metrics: CalculatedMetrics, errors: array<string, string>, waveHeightStation: array<string, mixed>|null, wavePeriodStation: array<string, mixed>|null, waveDirectionStation: array<string, mixed>|null, tidalStation: array<string, mixed>|null}|null
     */
    public function execute(string $locationId): ?array
    {
        $location = $this->locationRepository->findById($locationId);

        if (null === $location) {
            return null;
        }

        $errors = [];
        $waveHeightStation = null;
        $wavePeriodStation = null;
        $waveDirectionStation = null;
        $tidalStation = null;

        $water = $this->waterProvider->getConditions($location);
        if (null === $water) {
            $errors['water'] = $this->waterProvider->getLastError() ?? 'Failed to fetch water conditions';
        }

        // If wave height is not available, try to get it from the nearest station
        if (null !== $water && null === $water->getWaveHeight()->getMeters()) {
            $waveHeightStation = $this->fetchFromNearestStation($water, self::CAPABILITY_WAVE_HEIGHT);
        }

        // If wave period is not available, try to get it from the nearest station
        if (null !== $water && (null === $water->getWavePeriod() || null === $water->getWavePeriod()->getSeconds())) {
            $wavePeriodStation = $this->fetchFromNearestStation($water, self::CAPABILITY_WAVE_PERIOD);
        }

        // If wave direction is not available, try to get it from the nearest station
        if (null !== $water && (null === $water->getWaveDirection() || null === $water->getWaveDirection()->getDegrees())) {
            $waveDirectionStation = $this->fetchFromNearestStation($water, self::CAPABILITY_WAVE_DIRECTION);
        }

        $weather = $this->weatherProvider->getConditions($location);
        if (null === $weather) {
            $errors['weather'] = $this->weatherProvider->getLastError() ?? 'Failed to fetch weather conditions';
        }

        $tides = $this->tidalProvider->getTidalInfo($location);
        if (null === $tides) {
            // Try to get tidal data from the nearest station with tidal data
            $tidalStation = $this->fetchFromNearestStation($location, self::CAPABILITY_TIDES);
            if (null !== $tidalStation) {
                $tides = $tidalStation['tides'];
            } else {
                $errors['tides'] = $this->tidalProvider->getLastError() ?? 'Failed to fetch tidal data';
            }
        }

        $safetyScore = $this->safetyCalculator->calculate($water, $weather);
        $comfortIndex = $this->comfortCalculator->calculate($water, $weather);

        return [
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
    private function fetchFromNearestStation(Location|WaterConditions $source, string $capability): ?array
    {
        $location = $source instanceof WaterConditions ? $source->getLocation() : $source;
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
                ];

            case self::CAPABILITY_WAVE_PERIOD:
                $wavePeriod = $stationConditions->getWavePeriod();
                $value = $wavePeriod?->getSeconds();

                return null === $value ? null : [
                    ...$baseResult,
                    'wavePeriod' => $value,
                    'raw' => $rawMeasurements['wavePeriod'] ?? null,
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
                ];

            default:
                return null;
        }
    }
}
