<?php

declare(strict_types=1);

namespace Seaswim\Domain\Entity;

use Seaswim\Domain\ValueObject\BuienradarStation;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\UVIndex;
use Seaswim\Domain\ValueObject\WindSpeed;

final readonly class WeatherConditions
{
    public function __construct(
        private Location $location,
        private Temperature $airTemperature,
        private WindSpeed $windSpeed,
        private ?string $windDirection,
        private UVIndex $uvIndex,
        private \DateTimeImmutable $measuredAt,
        private ?BuienradarStation $station = null,
    ) {
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function getAirTemperature(): Temperature
    {
        return $this->airTemperature;
    }

    public function getWindSpeed(): WindSpeed
    {
        return $this->windSpeed;
    }

    public function getWindDirection(): ?string
    {
        return $this->windDirection;
    }

    public function getUvIndex(): UVIndex
    {
        return $this->uvIndex;
    }

    public function getMeasuredAt(): \DateTimeImmutable
    {
        return $this->measuredAt;
    }

    public function getStation(): ?BuienradarStation
    {
        return $this->station;
    }
}
