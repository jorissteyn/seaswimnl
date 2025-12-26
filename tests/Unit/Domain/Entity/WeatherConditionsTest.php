<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\Sunpower;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WindSpeed;

final class WeatherConditionsTest extends TestCase
{
    public function testConstruction(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $airTemp = Temperature::fromCelsius(22.0);
        $windSpeed = WindSpeed::fromMetersPerSecond(5.0);
        $windDirection = 'NW';
        $sunpower = Sunpower::fromWattsPerSquareMeter(450.0);
        $measuredAt = new \DateTimeImmutable('2024-12-20T10:00:00+01:00');

        $conditions = new WeatherConditions(
            $location,
            $airTemp,
            $windSpeed,
            $windDirection,
            $sunpower,
            $measuredAt,
        );

        $this->assertSame($location, $conditions->getLocation());
        $this->assertSame($airTemp, $conditions->getAirTemperature());
        $this->assertSame($windSpeed, $conditions->getWindSpeed());
        $this->assertSame($windDirection, $conditions->getWindDirection());
        $this->assertSame($sunpower, $conditions->getSunpower());
        $this->assertSame($measuredAt, $conditions->getMeasuredAt());
    }

    public function testWithNullWindDirection(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $conditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(22.0),
            WindSpeed::fromMetersPerSecond(5.0),
            null,
            Sunpower::fromWattsPerSquareMeter(450.0),
            new \DateTimeImmutable(),
        );

        $this->assertNull($conditions->getWindDirection());
    }

    public function testWithUnknownValues(): void
    {
        $location = new RwsLocation('unknown', 'Unknown', 0.0, 0.0);
        $conditions = new WeatherConditions(
            $location,
            Temperature::unknown(),
            WindSpeed::unknown(),
            null,
            Sunpower::unknown(),
            new \DateTimeImmutable(),
        );

        $this->assertFalse($conditions->getAirTemperature()->isKnown());
        $this->assertFalse($conditions->getWindSpeed()->isKnown());
        $this->assertFalse($conditions->getSunpower()->isKnown());
    }
}
