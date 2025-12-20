<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Domain\Entity\CalculatedMetrics;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\Service\ComfortIndexCalculator;
use Seaswim\Domain\Service\SafetyScoreCalculator;
use Seaswim\Domain\Service\SwimTimeRecommender;

final readonly class GetConditionsForLocation
{
    public function __construct(
        private LocationRepositoryInterface $locationRepository,
        private WaterConditionsProviderInterface $waterProvider,
        private WeatherConditionsProviderInterface $weatherProvider,
        private SafetyScoreCalculator $safetyCalculator,
        private ComfortIndexCalculator $comfortCalculator,
        private SwimTimeRecommender $recommender,
    ) {
    }

    /**
     * @return array{water: WaterConditions|null, weather: WeatherConditions|null, metrics: CalculatedMetrics}|null
     */
    public function execute(string $locationId): ?array
    {
        $location = $this->locationRepository->findById($locationId);

        if ($location === null) {
            return null;
        }

        $water = $this->waterProvider->getConditions($location);
        $weather = $this->weatherProvider->getConditions($location);

        $safetyScore = $this->safetyCalculator->calculate($water, $weather);
        $comfortIndex = $this->comfortCalculator->calculate($water, $weather);
        $recommendation = $this->recommender->recommend($safetyScore, $comfortIndex);

        return [
            'water' => $water,
            'weather' => $weather,
            'metrics' => new CalculatedMetrics($safetyScore, $comfortIndex, $recommendation),
        ];
    }
}
