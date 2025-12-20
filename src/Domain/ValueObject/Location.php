<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class Location
{
    public function __construct(
        private string $id,
        private string $name,
        private float $latitude,
        private float $longitude,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
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
