<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\Dto;

final readonly class LocationOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public float $latitude,
        public float $longitude,
    ) {
    }
}
