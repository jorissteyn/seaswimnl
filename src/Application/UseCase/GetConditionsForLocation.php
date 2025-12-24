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
use Seaswim\Domain\ValueObject\TideInfo;

final readonly class GetConditionsForLocation
{
    private const CAPABILITY_WAVE_HEIGHT = 'Hm0';
    private const CAPABILITY_WAVE_PERIOD = 'Tm02';
    private const CAPABILITY_WAVE_DIRECTION = 'Th3';

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
     * @return array{water: WaterConditions|null, weather: WeatherConditions|null, tides: TideInfo|null, metrics: CalculatedMetrics, errors: array<string, string>, waveHeightStation: array<string, mixed>|null, wavePeriodStation: array<string, mixed>|null, waveDirectionStation: array<string, mixed>|null}|null
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
            $errors['tides'] = $this->tidalProvider->getLastError() ?? 'Failed to fetch tidal data';
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
        ];
    }

    /**
     * Fetch data from the nearest station that has the specified capability.
     *
     * @return array<string, mixed>|null
     */
    private function fetchFromNearestStation(WaterConditions $water, string $capability): ?array
    {
        $allLocations = $this->locationRepository->findAll();
        $stationResult = $this->rwsLocationFinder->findNearest($water->getLocation(), $allLocations, $capability);

        if (null === $stationResult) {
            return null;
        }

        $station = $stationResult['location'];
        $stationConditions = $this->waterProvider->getConditions($station);

        if (null === $stationConditions) {
            return null;
        }

        $baseResult = [
            'id' => $station->getId(),
            'name' => $station->getName(),
            'distanceKm' => $stationResult['distanceKm'],
        ];

        switch ($capability) {
            case self::CAPABILITY_WAVE_HEIGHT:
                $value = $stationConditions->getWaveHeight()->getMeters();

                return null === $value ? null : [...$baseResult, 'waveHeight' => $value];

            case self::CAPABILITY_WAVE_PERIOD:
                $wavePeriod = $stationConditions->getWavePeriod();
                $value = $wavePeriod?->getSeconds();

                return null === $value ? null : [...$baseResult, 'wavePeriod' => $value];

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
                ];

            default:
                return null;
        }
    }
}
