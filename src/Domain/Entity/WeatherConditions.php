<?php

declare(strict_types=1);

namespace Seaswim\Domain\Entity;

use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\Sunpower;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WeatherStation;
use Seaswim\Domain\ValueObject\WindSpeed;

final readonly class WeatherConditions
{
    /**
     * @param array<string, array{field: string, value: mixed, unit: string}|null>|null $rawMeasurements
     */
    public function __construct(
        private RwsLocation $location,
        private Temperature $airTemperature,
        private WindSpeed $windSpeed,
        private ?string $windDirection,
        private Sunpower $sunpower,
        private \DateTimeImmutable $measuredAt,
        private ?WeatherStation $station = null,
        private ?float $stationDistanceKm = null,
        private ?array $rawMeasurements = null,
    ) {
    }

    public function getLocation(): RwsLocation
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

    public function getSunpower(): Sunpower
    {
        return $this->sunpower;
    }

    public function getMeasuredAt(): \DateTimeImmutable
    {
        return $this->measuredAt;
    }

    public function getStation(): ?WeatherStation
    {
        return $this->station;
    }

    public function getStationDistanceKm(): ?float
    {
        return $this->stationDistanceKm;
    }

    /**
     * @return array<string, array{field: string, value: mixed, unit: string}|null>|null
     */
    public function getRawMeasurements(): ?array
    {
        return $this->rawMeasurements;
    }
}
