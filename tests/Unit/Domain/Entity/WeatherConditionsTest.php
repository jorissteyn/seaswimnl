<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\UVIndex;
use Seaswim\Domain\ValueObject\WindSpeed;

final class WeatherConditionsTest extends TestCase
{
    public function testConstruction(): void
    {
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);
        $airTemp = Temperature::fromCelsius(22.0);
        $windSpeed = WindSpeed::fromMetersPerSecond(5.0);
        $windDirection = 'NW';
        $uvIndex = UVIndex::fromValue(6);
        $measuredAt = new \DateTimeImmutable('2024-12-20T10:00:00+01:00');

        $conditions = new WeatherConditions(
            $location,
            $airTemp,
            $windSpeed,
            $windDirection,
            $uvIndex,
            $measuredAt,
        );

        $this->assertSame($location, $conditions->getLocation());
        $this->assertSame($airTemp, $conditions->getAirTemperature());
        $this->assertSame($windSpeed, $conditions->getWindSpeed());
        $this->assertSame($windDirection, $conditions->getWindDirection());
        $this->assertSame($uvIndex, $conditions->getUvIndex());
        $this->assertSame($measuredAt, $conditions->getMeasuredAt());
    }

    public function testWithNullWindDirection(): void
    {
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);
        $conditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(22.0),
            WindSpeed::fromMetersPerSecond(5.0),
            null,
            UVIndex::fromValue(6),
            new \DateTimeImmutable(),
        );

        $this->assertNull($conditions->getWindDirection());
    }

    public function testWithUnknownValues(): void
    {
        $location = new Location('unknown', 'Unknown', 0.0, 0.0);
        $conditions = new WeatherConditions(
            $location,
            Temperature::unknown(),
            WindSpeed::unknown(),
            null,
            UVIndex::unknown(),
            new \DateTimeImmutable(),
        );

        $this->assertFalse($conditions->getAirTemperature()->isKnown());
        $this->assertFalse($conditions->getWindSpeed()->isKnown());
        $this->assertFalse($conditions->getUvIndex()->isKnown());
    }
}
