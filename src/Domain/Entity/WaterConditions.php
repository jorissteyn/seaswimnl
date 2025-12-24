<?php

declare(strict_types=1);

namespace Seaswim\Domain\Entity;

use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WindSpeed;

final readonly class WaterConditions
{
    public function __construct(
        private Location $location,
        private Temperature $temperature,
        private WaveHeight $waveHeight,
        private WaterHeight $waterHeight,
        private \DateTimeImmutable $measuredAt,
        private ?WindSpeed $windSpeed = null,
        private ?string $windDirection = null,
    ) {
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function getTemperature(): Temperature
    {
        return $this->temperature;
    }

    public function getWaveHeight(): WaveHeight
    {
        return $this->waveHeight;
    }

    public function getWaterHeight(): WaterHeight
    {
        return $this->waterHeight;
    }

    public function getMeasuredAt(): \DateTimeImmutable
    {
        return $this->measuredAt;
    }

    public function getWindSpeed(): ?WindSpeed
    {
        return $this->windSpeed;
    }

    public function getWindDirection(): ?string
    {
        return $this->windDirection;
    }
}
