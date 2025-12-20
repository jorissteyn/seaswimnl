<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\Service\SafetyScoreCalculator;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\SafetyScore;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\UVIndex;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaterQuality;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WindSpeed;

final class SafetyScoreCalculatorTest extends TestCase
{
    private SafetyScoreCalculator $calculator;
    private Location $location;

    protected function setUp(): void
    {
        $this->calculator = new SafetyScoreCalculator();
        $this->location = new Location('test', 'Test', 51.0, 3.0);
    }

    public function testGreenScoreWithOptimalConditions(): void
    {
        $water = $this->createWaterConditions(18.0, 0.5, WaterQuality::Good);
        $weather = $this->createWeatherConditions(5.0); // 18 km/h

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Green, $score);
    }

    public function testYellowScoreWithLowWaterTemperature(): void
    {
        $water = $this->createWaterConditions(12.0, 0.5, WaterQuality::Good);
        $weather = $this->createWeatherConditions(5.0);

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Yellow, $score);
    }

    public function testRedScoreWithVeryLowWaterTemperature(): void
    {
        $water = $this->createWaterConditions(8.0, 0.5, WaterQuality::Good);
        $weather = $this->createWeatherConditions(5.0);

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Red, $score);
    }

    public function testYellowScoreWithHighWaves(): void
    {
        $water = $this->createWaterConditions(18.0, 1.5, WaterQuality::Good);
        $weather = $this->createWeatherConditions(5.0);

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Yellow, $score);
    }

    public function testRedScoreWithVeryHighWaves(): void
    {
        $water = $this->createWaterConditions(18.0, 2.5, WaterQuality::Good);
        $weather = $this->createWeatherConditions(5.0);

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Red, $score);
    }

    public function testYellowScoreWithModerateWaterQuality(): void
    {
        $water = $this->createWaterConditions(18.0, 0.5, WaterQuality::Moderate);
        $weather = $this->createWeatherConditions(5.0);

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Yellow, $score);
    }

    public function testRedScoreWithPoorWaterQuality(): void
    {
        $water = $this->createWaterConditions(18.0, 0.5, WaterQuality::Poor);
        $weather = $this->createWeatherConditions(5.0);

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Red, $score);
    }

    public function testYellowScoreWithHighWindSpeed(): void
    {
        $water = $this->createWaterConditions(18.0, 0.5, WaterQuality::Good);
        $weather = $this->createWeatherConditions(8.5); // ~30 km/h

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Yellow, $score);
    }

    public function testRedScoreWithVeryHighWindSpeed(): void
    {
        $water = $this->createWaterConditions(18.0, 0.5, WaterQuality::Good);
        $weather = $this->createWeatherConditions(12.0); // ~43 km/h

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Red, $score);
    }

    public function testYellowScoreWithMissingWaterData(): void
    {
        $weather = $this->createWeatherConditions(5.0);

        $score = $this->calculator->calculate(null, $weather);

        $this->assertSame(SafetyScore::Yellow, $score);
    }

    public function testRedConditionOverridesYellow(): void
    {
        // Multiple yellow conditions, but one red
        $water = $this->createWaterConditions(12.0, 1.5, WaterQuality::Moderate); // Yellow temp, yellow waves, yellow quality
        $weather = $this->createWeatherConditions(12.0); // Red wind

        $score = $this->calculator->calculate($water, $weather);

        $this->assertSame(SafetyScore::Red, $score);
    }

    private function createWaterConditions(float $temp, float $waveHeight, WaterQuality $quality): WaterConditions
    {
        return new WaterConditions(
            $this->location,
            Temperature::fromCelsius($temp),
            WaveHeight::fromMeters($waveHeight),
            WaterHeight::fromMeters(0.0),
            $quality,
            new \DateTimeImmutable(),
        );
    }

    private function createWeatherConditions(float $windSpeedMs): WeatherConditions
    {
        return new WeatherConditions(
            $this->location,
            Temperature::fromCelsius(20.0),
            WindSpeed::fromMetersPerSecond($windSpeedMs),
            'N',
            UVIndex::fromValue(5),
            new \DateTimeImmutable(),
        );
    }
}
