<?php

declare(strict_types=1);

namespace Seaswim\Domain\Entity;

use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaveDirection;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WavePeriod;
use Seaswim\Domain\ValueObject\WindSpeed;

final readonly class WaterConditions
{
    /**
     * @param array<string, array{code: string, compartiment: string, value: float, unit: string}|null>|null $rawMeasurements
     */
    public function __construct(
        private Location $location,
        private Temperature $temperature,
        private WaveHeight $waveHeight,
        private WaterHeight $waterHeight,
        private \DateTimeImmutable $measuredAt,
        private ?WindSpeed $windSpeed = null,
        private ?string $windDirection = null,
        private ?WavePeriod $wavePeriod = null,
        private ?WaveDirection $waveDirection = null,
        private ?array $rawMeasurements = null,
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

    public function getWavePeriod(): ?WavePeriod
    {
        return $this->wavePeriod;
    }

    public function getWaveDirection(): ?WaveDirection
    {
        return $this->waveDirection;
    }

    /**
     * @return array<string, array{code: string, compartiment: string, value: float, unit: string}|null>|null
     */
    public function getRawMeasurements(): ?array
    {
        return $this->rawMeasurements;
    }
}
