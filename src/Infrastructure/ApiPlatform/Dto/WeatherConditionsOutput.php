<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\Dto;

final readonly class WeatherConditionsOutput
{
    public function __construct(
        public ?float $airTemperature,
        public ?float $windSpeed,
        public ?string $windDirection,
        public ?int $uvIndex,
        public string $uvLevel,
        public string $measuredAt,
    ) {
    }
}
