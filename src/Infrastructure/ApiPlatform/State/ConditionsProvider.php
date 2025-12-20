<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Seaswim\Application\UseCase\GetConditionsForLocation;
use Seaswim\Domain\Entity\CalculatedMetrics;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Infrastructure\ApiPlatform\Dto\ConditionsOutput;
use Seaswim\Infrastructure\ApiPlatform\Dto\MetricsOutput;
use Seaswim\Infrastructure\ApiPlatform\Dto\WaterConditionsOutput;
use Seaswim\Infrastructure\ApiPlatform\Dto\WeatherConditionsOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ConditionsOutput>
 */
final readonly class ConditionsProvider implements ProviderInterface
{
    public function __construct(
        private GetConditionsForLocation $getConditions,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $locationId = $uriVariables['location'] ?? null;

        if (null === $locationId) {
            throw new NotFoundHttpException('Location not specified');
        }

        $conditions = $this->getConditions->execute($locationId);

        if (null === $conditions) {
            throw new NotFoundHttpException('Location not found');
        }

        return new ConditionsOutput(
            locationId: $locationId,
            water: $this->mapWater($conditions['water']),
            weather: $this->mapWeather($conditions['weather']),
            metrics: $this->mapMetrics($conditions['metrics']),
            updatedAt: (new \DateTimeImmutable())->format('c'),
        );
    }

    private function mapWater(?WaterConditions $water): ?WaterConditionsOutput
    {
        if (null === $water) {
            return null;
        }

        return new WaterConditionsOutput(
            temperature: $water->getTemperature()->getCelsius(),
            waveHeight: $water->getWaveHeight()->getMeters(),
            waterHeight: $water->getWaterHeight()->getMeters(),
            quality: $water->getQuality()->value,
            qualityLabel: $water->getQuality()->getLabel(),
            measuredAt: $water->getMeasuredAt()->format('c'),
        );
    }

    private function mapWeather(?WeatherConditions $weather): ?WeatherConditionsOutput
    {
        if (null === $weather) {
            return null;
        }

        return new WeatherConditionsOutput(
            airTemperature: $weather->getAirTemperature()->getCelsius(),
            windSpeed: $weather->getWindSpeed()->getKilometersPerHour(),
            windDirection: $weather->getWindDirection(),
            uvIndex: $weather->getUvIndex()->getValue(),
            uvLevel: $weather->getUvIndex()->getLevel(),
            measuredAt: $weather->getMeasuredAt()->format('c'),
        );
    }

    private function mapMetrics(CalculatedMetrics $metrics): MetricsOutput
    {
        return new MetricsOutput(
            safetyScore: $metrics->getSafetyScore()->value,
            safetyLabel: $metrics->getSafetyScore()->getLabel(),
            safetyDescription: $metrics->getSafetyScore()->getDescription(),
            comfortIndex: $metrics->getComfortIndex()->getValue(),
            comfortLabel: $metrics->getComfortIndex()->getLabel(),
            recommendation: $metrics->getRecommendation()->getTypeValue(),
            recommendationLabel: $metrics->getRecommendation()->getLabel(),
            recommendationExplanation: $metrics->getRecommendation()->getExplanation(),
        );
    }
}
