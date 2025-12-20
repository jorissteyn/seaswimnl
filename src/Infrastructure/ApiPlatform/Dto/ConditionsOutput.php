<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\Dto;

final readonly class ConditionsOutput
{
    public function __construct(
        public string $locationId,
        public ?WaterConditionsOutput $water,
        public ?WeatherConditionsOutput $weather,
        public MetricsOutput $metrics,
        public string $updatedAt,
    ) {
    }
}
