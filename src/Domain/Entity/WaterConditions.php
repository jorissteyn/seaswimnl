<?php

declare(strict_types=1);

namespace Seaswim\Domain\Entity;

use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaterQuality;
use Seaswim\Domain\ValueObject\WaveHeight;

final readonly class WaterConditions
{
    public function __construct(
        private Location $location,
        private Temperature $temperature,
        private WaveHeight $waveHeight,
        private WaterHeight $waterHeight,
        private WaterQuality $quality,
        private \DateTimeImmutable $measuredAt,
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

    public function getQuality(): WaterQuality
    {
        return $this->quality;
    }

    public function getMeasuredAt(): \DateTimeImmutable
    {
        return $this->measuredAt;
    }
}
