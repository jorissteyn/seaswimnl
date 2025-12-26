<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\Service\ComfortIndexCalculator;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\Sunpower;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WindSpeed;

final class ComfortIndexCalculatorTest extends TestCase
{
    private ComfortIndexCalculator $calculator;
    private RwsLocation $location;

    protected function setUp(): void
    {
        $this->calculator = new ComfortIndexCalculator();
        $this->location = new RwsLocation('test', 'Test', 51.0, 3.0);
    }

    public function testHighComfortWithOptimalConditions(): void
    {
        $water = $this->createWaterConditions(20.0, 0.2);
        $weather = $this->createWeatherConditions(22.0, 2.0, 400.0);

        $index = $this->calculator->calculate($water, $weather);

        $this->assertGreaterThanOrEqual(8, $index->getValue());
        $this->assertSame('Excellent', $index->getLabel());
    }

    public function testMediumComfortWithSuboptimalConditions(): void
    {
        $water = $this->createWaterConditions(14.0, 0.8);
        $weather = $this->createWeatherConditions(18.0, 6.0, 50.0);

        $index = $this->calculator->calculate($water, $weather);

        $this->assertGreaterThanOrEqual(4, $index->getValue());
        $this->assertLessThanOrEqual(7, $index->getValue());
    }

    public function testLowComfortWithPoorConditions(): void
    {
        $water = $this->createWaterConditions(10.0, 1.8);
        $weather = $this->createWeatherConditions(10.0, 12.0, 10.0);

        $index = $this->calculator->calculate($water, $weather);

        $this->assertLessThanOrEqual(4, $index->getValue());
    }

    public function testDefaultComfortWithNoData(): void
    {
        $index = $this->calculator->calculate(null, null);

        $this->assertSame(5, $index->getValue());
    }

    public function testWaterTemperatureContributesSignificantly(): void
    {
        // Same conditions except water temperature
        $waterWarm = $this->createWaterConditions(20.0, 0.5);
        $waterCold = $this->createWaterConditions(10.0, 0.5);
        $weather = $this->createWeatherConditions(22.0, 2.0, 400.0);

        $indexWarm = $this->calculator->calculate($waterWarm, $weather);
        $indexCold = $this->calculator->calculate($waterCold, $weather);

        // Warm water should result in higher comfort
        $this->assertGreaterThan($indexCold->getValue(), $indexWarm->getValue());
    }

    public function testIndexIsClampedBetween1And10(): void
    {
        // Even with very poor conditions, index should be at least 1
        $water = $this->createWaterConditions(5.0, 3.0);
        $weather = $this->createWeatherConditions(5.0, 15.0, 5.0);

        $index = $this->calculator->calculate($water, $weather);

        $this->assertGreaterThanOrEqual(1, $index->getValue());
        $this->assertLessThanOrEqual(10, $index->getValue());
    }

    private function createWaterConditions(float $temp, float $waveHeight): WaterConditions
    {
        return new WaterConditions(
            $this->location,
            Temperature::fromCelsius($temp),
            WaveHeight::fromMeters($waveHeight),
            WaterHeight::fromMeters(0.0),
            new \DateTimeImmutable(),
        );
    }

    private function createWeatherConditions(float $airTemp, float $windSpeedMs, float $sunpower): WeatherConditions
    {
        return new WeatherConditions(
            $this->location,
            Temperature::fromCelsius($airTemp),
            WindSpeed::fromMetersPerSecond($windSpeedMs),
            'N',
            Sunpower::fromWattsPerSquareMeter($sunpower),
            new \DateTimeImmutable(),
        );
    }
}
