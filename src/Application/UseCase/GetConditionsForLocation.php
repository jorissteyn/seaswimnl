<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;

final readonly class GetConditionsForLocation
{
    public function __construct(
        private LocationRepositoryInterface $locationRepository,
        private WaterConditionsProviderInterface $waterProvider,
        private WeatherConditionsProviderInterface $weatherProvider,
    ) {
    }

    /**
     * @return array{water: WaterConditions|null, weather: WeatherConditions|null}|null
     */
    public function execute(string $locationId): ?array
    {
        $location = $this->locationRepository->findById($locationId);

        if ($location === null) {
            return null;
        }

        return [
            'water' => $this->waterProvider->getConditions($location),
            'weather' => $this->weatherProvider->getConditions($location),
        ];
    }
}
