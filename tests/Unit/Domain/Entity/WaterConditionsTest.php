<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaveHeight;

final class WaterConditionsTest extends TestCase
{
    public function testConstruction(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $temperature = Temperature::fromCelsius(18.5);
        $waveHeight = WaveHeight::fromMeters(0.8);
        $waterHeight = WaterHeight::fromMeters(0.45);
        $measuredAt = new \DateTimeImmutable('2024-12-20T10:00:00+01:00');

        $conditions = new WaterConditions(
            $location,
            $temperature,
            $waveHeight,
            $waterHeight,
            $measuredAt,
        );

        $this->assertSame($location, $conditions->getLocation());
        $this->assertSame($temperature, $conditions->getTemperature());
        $this->assertSame($waveHeight, $conditions->getWaveHeight());
        $this->assertSame($waterHeight, $conditions->getWaterHeight());
        $this->assertSame($measuredAt, $conditions->getMeasuredAt());
    }

    public function testWithUnknownValues(): void
    {
        $location = new RwsLocation('unknown', 'Unknown', 0.0, 0.0);
        $conditions = new WaterConditions(
            $location,
            Temperature::unknown(),
            WaveHeight::unknown(),
            WaterHeight::unknown(),
            new \DateTimeImmutable(),
        );

        $this->assertFalse($conditions->getTemperature()->isKnown());
        $this->assertFalse($conditions->getWaveHeight()->isKnown());
        $this->assertFalse($conditions->getWaterHeight()->isKnown());
    }
}
