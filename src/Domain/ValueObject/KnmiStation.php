<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class KnmiStation
{
    public function __construct(
        private string $code,
        private string $name,
        private float $latitude,
        private float $longitude,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }
}
