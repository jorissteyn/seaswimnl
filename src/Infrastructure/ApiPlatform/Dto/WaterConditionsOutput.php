<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\Dto;

final readonly class WaterConditionsOutput
{
    public function __construct(
        public ?float $temperature,
        public ?float $waveHeight,
        public ?float $waterHeight,
        public string $quality,
        public string $qualityLabel,
        public string $measuredAt,
    ) {
    }
}
