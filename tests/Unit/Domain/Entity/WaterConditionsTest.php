<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaveDirection;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WavePeriod;
use Seaswim\Domain\ValueObject\WindSpeed;

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

    public function testOptionalParametersDefaultToNull(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
        );

        $this->assertNull($conditions->getWindSpeed());
        $this->assertNull($conditions->getWindDirection());
        $this->assertNull($conditions->getWavePeriod());
        $this->assertNull($conditions->getWaveDirection());
        $this->assertNull($conditions->getRawMeasurements());
    }

    public function testWithWindSpeed(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $windSpeed = WindSpeed::fromMetersPerSecond(5.5);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            $windSpeed,
        );

        $this->assertSame($windSpeed, $conditions->getWindSpeed());
        $this->assertTrue($conditions->getWindSpeed()->isKnown());
    }

    public function testWithUnknownWindSpeed(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $windSpeed = WindSpeed::unknown();

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            $windSpeed,
        );

        $this->assertSame($windSpeed, $conditions->getWindSpeed());
        $this->assertFalse($conditions->getWindSpeed()->isKnown());
    }

    public function testWithWindDirection(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            'NW',
        );

        $this->assertSame('NW', $conditions->getWindDirection());
    }

    public function testWithNullWindDirection(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
        );

        $this->assertNull($conditions->getWindDirection());
    }

    public function testWithWavePeriod(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $wavePeriod = WavePeriod::fromSeconds(6.5);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
            $wavePeriod,
        );

        $this->assertSame($wavePeriod, $conditions->getWavePeriod());
        $this->assertTrue($conditions->getWavePeriod()->isKnown());
    }

    public function testWithUnknownWavePeriod(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $wavePeriod = WavePeriod::unknown();

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
            $wavePeriod,
        );

        $this->assertSame($wavePeriod, $conditions->getWavePeriod());
        $this->assertFalse($conditions->getWavePeriod()->isKnown());
    }

    public function testWithWaveDirection(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $waveDirection = WaveDirection::fromDegrees(270.0);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
            null,
            $waveDirection,
        );

        $this->assertSame($waveDirection, $conditions->getWaveDirection());
        $this->assertTrue($conditions->getWaveDirection()->isKnown());
    }

    public function testWithUnknownWaveDirection(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $waveDirection = WaveDirection::unknown();

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
            null,
            $waveDirection,
        );

        $this->assertSame($waveDirection, $conditions->getWaveDirection());
        $this->assertFalse($conditions->getWaveDirection()->isKnown());
    }

    public function testWithRawMeasurements(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $rawMeasurements = [
            'temperature' => [
                'code' => 'T10',
                'compartiment' => 'OW',
                'value' => 18.5,
                'unit' => 'C',
            ],
            'waveHeight' => [
                'code' => 'H1/3',
                'compartiment' => 'OW',
                'value' => 0.8,
                'unit' => 'm',
            ],
        ];

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
            null,
            null,
            $rawMeasurements,
        );

        $this->assertSame($rawMeasurements, $conditions->getRawMeasurements());
        $this->assertIsArray($conditions->getRawMeasurements());
        $this->assertArrayHasKey('temperature', $conditions->getRawMeasurements());
        $this->assertArrayHasKey('waveHeight', $conditions->getRawMeasurements());
    }

    public function testWithEmptyRawMeasurements(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
            null,
            null,
            [],
        );

        $this->assertSame([], $conditions->getRawMeasurements());
        $this->assertIsArray($conditions->getRawMeasurements());
        $this->assertEmpty($conditions->getRawMeasurements());
    }

    public function testWithNullRawMeasurements(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
            null,
            null,
            null,
        );

        $this->assertNull($conditions->getRawMeasurements());
    }

    public function testWithRawMeasurementsContainingNullValues(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $rawMeasurements = [
            'temperature' => [
                'code' => 'T10',
                'compartiment' => 'OW',
                'value' => 18.5,
                'unit' => 'C',
            ],
            'missingData' => null,
            'waveHeight' => [
                'code' => 'H1/3',
                'compartiment' => 'OW',
                'value' => 0.8,
                'unit' => 'm',
            ],
        ];

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
            null,
            null,
            null,
            null,
            $rawMeasurements,
        );

        $this->assertSame($rawMeasurements, $conditions->getRawMeasurements());
        $this->assertArrayHasKey('missingData', $conditions->getRawMeasurements());
        $this->assertNull($conditions->getRawMeasurements()['missingData']);
    }

    public function testFullyPopulatedConditions(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $temperature = Temperature::fromCelsius(18.5);
        $waveHeight = WaveHeight::fromMeters(0.8);
        $waterHeight = WaterHeight::fromMeters(0.45);
        $measuredAt = new \DateTimeImmutable('2024-12-20T10:00:00+01:00');
        $windSpeed = WindSpeed::fromMetersPerSecond(5.5);
        $windDirection = 'NW';
        $wavePeriod = WavePeriod::fromSeconds(6.5);
        $waveDirection = WaveDirection::fromDegrees(270.0);
        $rawMeasurements = [
            'temperature' => [
                'code' => 'T10',
                'compartiment' => 'OW',
                'value' => 18.5,
                'unit' => 'C',
            ],
        ];

        $conditions = new WaterConditions(
            $location,
            $temperature,
            $waveHeight,
            $waterHeight,
            $measuredAt,
            $windSpeed,
            $windDirection,
            $wavePeriod,
            $waveDirection,
            $rawMeasurements,
        );

        $this->assertSame($location, $conditions->getLocation());
        $this->assertSame($temperature, $conditions->getTemperature());
        $this->assertSame($waveHeight, $conditions->getWaveHeight());
        $this->assertSame($waterHeight, $conditions->getWaterHeight());
        $this->assertSame($measuredAt, $conditions->getMeasuredAt());
        $this->assertSame($windSpeed, $conditions->getWindSpeed());
        $this->assertSame($windDirection, $conditions->getWindDirection());
        $this->assertSame($wavePeriod, $conditions->getWavePeriod());
        $this->assertSame($waveDirection, $conditions->getWaveDirection());
        $this->assertSame($rawMeasurements, $conditions->getRawMeasurements());
    }

    public function testWithVariousWindDirections(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $windDirections = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'NNO', 'ONO'];

        foreach ($windDirections as $direction) {
            $conditions = new WaterConditions(
                $location,
                Temperature::fromCelsius(18.5),
                WaveHeight::fromMeters(0.8),
                WaterHeight::fromMeters(0.45),
                new \DateTimeImmutable(),
                null,
                $direction,
            );

            $this->assertSame($direction, $conditions->getWindDirection());
        }
    }

    public function testWithBoundaryTemperatureValues(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);

        $coldConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(0.0),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
        );

        $this->assertSame(0.0, $coldConditions->getTemperature()->getCelsius());

        $hotConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(35.0),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
        );

        $this->assertSame(35.0, $hotConditions->getTemperature()->getCelsius());
    }

    public function testWithNegativeWaterHeight(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(-0.5),
            new \DateTimeImmutable(),
        );

        $this->assertSame(-0.5, $conditions->getWaterHeight()->getMeters());
    }

    public function testWithZeroWaveHeight(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.0),
            WaterHeight::fromMeters(0.45),
            new \DateTimeImmutable(),
        );

        $this->assertSame(0.0, $conditions->getWaveHeight()->getMeters());
    }

    public function testReadonlyBehavior(): void
    {
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
        $temperature = Temperature::fromCelsius(18.5);
        $measuredAt = new \DateTimeImmutable('2024-12-20T10:00:00+01:00');

        $conditions = new WaterConditions(
            $location,
            $temperature,
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.45),
            $measuredAt,
        );

        // Verify that the same instances are returned (readonly behavior)
        $this->assertSame($location, $conditions->getLocation());
        $this->assertSame($temperature, $conditions->getTemperature());
        $this->assertSame($measuredAt, $conditions->getMeasuredAt());
    }
}
