<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;

final readonly class FetchAllData
{
    public function __construct(
        private LocationRepositoryInterface $locationRepository,
        private WaterConditionsProviderInterface $waterProvider,
        private WeatherConditionsProviderInterface $weatherProvider,
    ) {
    }

    /**
     * @return array{locations: int, water: int, weather: int}
     */
    public function execute(): array
    {
        $locations = $this->locationRepository->findAll();
        $waterCount = 0;
        $weatherCount = 0;

        foreach ($locations as $location) {
            if ($this->waterProvider->getConditions($location) !== null) {
                ++$waterCount;
            }
            if ($this->weatherProvider->getConditions($location) !== null) {
                ++$weatherCount;
            }
        }

        return [
            'locations' => count($locations),
            'water' => $waterCount,
            'weather' => $weatherCount,
        ];
    }
}
