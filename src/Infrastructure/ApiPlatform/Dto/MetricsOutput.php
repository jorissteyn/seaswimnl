<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\Dto;

final readonly class MetricsOutput
{
    public function __construct(
        public string $safetyScore,
        public string $safetyLabel,
        public string $safetyDescription,
        public int $comfortIndex,
        public string $comfortLabel,
    ) {
    }
}
