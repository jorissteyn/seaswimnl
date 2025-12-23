<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Application\Port\TidalInfoProviderInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Domain\Entity\CalculatedMetrics;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\Service\ComfortIndexCalculator;
use Seaswim\Domain\Service\SafetyScoreCalculator;
use Seaswim\Domain\ValueObject\TideInfo;

final readonly class GetConditionsForLocation
{
    public function __construct(
        private LocationRepositoryInterface $locationRepository,
        private WaterConditionsProviderInterface $waterProvider,
        private WeatherConditionsProviderInterface $weatherProvider,
        private TidalInfoProviderInterface $tidalProvider,
        private SafetyScoreCalculator $safetyCalculator,
        private ComfortIndexCalculator $comfortCalculator,
    ) {
    }

    /**
     * @return array{water: WaterConditions|null, weather: WeatherConditions|null, tides: TideInfo|null, metrics: CalculatedMetrics, errors: array<string, string>}|null
     */
    public function execute(string $locationId): ?array
    {
        $location = $this->locationRepository->findById($locationId);

        if (null === $location) {
            return null;
        }

        $errors = [];

        $water = $this->waterProvider->getConditions($location);
        if (null === $water) {
            $errors['water'] = $this->waterProvider->getLastError() ?? 'Failed to fetch water conditions';
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
        ];
    }
}
