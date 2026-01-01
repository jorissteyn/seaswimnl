<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\WaveDirection;

final class WaveDirectionTest extends TestCase
{
    public function testFromDegrees(): void
    {
        $direction = WaveDirection::fromDegrees(180.0);

        $this->assertSame(180.0, $direction->getDegrees());
        $this->assertTrue($direction->isKnown());
    }

    public function testFromDegreesWithNull(): void
    {
        $direction = WaveDirection::fromDegrees(null);

        $this->assertNull($direction->getDegrees());
        $this->assertFalse($direction->isKnown());
        $this->assertNull($direction->getCompassDirection());
    }

    public function testFromDegreesWithZero(): void
    {
        $direction = WaveDirection::fromDegrees(0.0);

        $this->assertSame(0.0, $direction->getDegrees());
        $this->assertTrue($direction->isKnown());
        $this->assertSame('N', $direction->getCompassDirection());
    }

    public function testUnknown(): void
    {
        $direction = WaveDirection::unknown();

        $this->assertNull($direction->getDegrees());
        $this->assertFalse($direction->isKnown());
        $this->assertNull($direction->getCompassDirection());
    }

    /**
     * @dataProvider compassDirectionProvider
     */
    public function testGetCompassDirection(float $degrees, string $expectedDirection): void
    {
        $direction = WaveDirection::fromDegrees($degrees);

        $this->assertSame($expectedDirection, $direction->getCompassDirection());
    }

    public static function compassDirectionProvider(): array
    {
        return [
            // North and near-north directions
            'North at 0 degrees' => [0.0, 'N'],
            'North at 360 degrees' => [360.0, 'N'],
            'North at 11 degrees' => [11.0, 'N'],
            'NNO at 22.5 degrees' => [22.5, 'NNO'],
            'NNO at 30 degrees' => [30.0, 'NNO'],

            // East directions
            'NO at 45 degrees' => [45.0, 'NO'],
            'ONO at 67.5 degrees' => [67.5, 'ONO'],
            'O at 90 degrees' => [90.0, 'O'],
            'OZO at 112.5 degrees' => [112.5, 'OZO'],

            // South directions
            'ZO at 135 degrees' => [135.0, 'ZO'],
            'ZZO at 157.5 degrees' => [157.5, 'ZZO'],
            'Z at 180 degrees' => [180.0, 'Z'],
            'ZZW at 202.5 degrees' => [202.5, 'ZZW'],

            // West directions
            'ZW at 225 degrees' => [225.0, 'ZW'],
            'WZW at 247.5 degrees' => [247.5, 'WZW'],
            'W at 270 degrees' => [270.0, 'W'],
            'WNW at 292.5 degrees' => [292.5, 'WNW'],

            // Northwest back to north
            'NW at 315 degrees' => [315.0, 'NW'],
            'NNW at 337.5 degrees' => [337.5, 'NNW'],
            'NNW at 348 degrees' => [348.0, 'NNW'],
        ];
    }

    /**
     * @dataProvider boundaryDegreesProvider
     */
    public function testGetCompassDirectionAtBoundaries(float $degrees, string $expectedDirection): void
    {
        $direction = WaveDirection::fromDegrees($degrees);

        $this->assertSame($expectedDirection, $direction->getCompassDirection());
    }

    public static function boundaryDegreesProvider(): array
    {
        return [
            // Boundary cases between compass directions (test rounding)
            'Just below NNO threshold' => [11.24, 'N'],
            'At NNO threshold' => [11.25, 'NNO'],
            'Just above NNO threshold' => [11.26, 'NNO'],
            'Just below NO threshold' => [33.74, 'NNO'],
            'At NO threshold' => [33.75, 'NO'],
            'Just above NO threshold' => [33.76, 'NO'],

            // Test wraparound near 360 degrees
            'Just below N threshold at end' => [348.74, 'NNW'],
            'At N threshold at end' => [348.75, 'N'],
            'Near 360' => [359.9, 'N'],
        ];
    }

    /**
     * @dataProvider negativeDegreesProvider
     */
    public function testGetCompassDirectionWithNegativeDegrees(float $degrees, string $expectedDirection): void
    {
        $direction = WaveDirection::fromDegrees($degrees);

        $this->assertSame($expectedDirection, $direction->getCompassDirection());
    }

    public static function negativeDegreesProvider(): array
    {
        return [
            // Negative degrees should wrap around correctly
            'Negative 45 degrees (NW)' => [-45.0, 'NW'],
            'Negative 90 degrees (W)' => [-90.0, 'W'],
            'Negative 180 degrees (Z)' => [-180.0, 'Z'],
            'Negative 22.5 degrees (NNW)' => [-22.5, 'NNW'],
        ];
    }

    /**
     * @dataProvider largeDegreesProvider
     */
    public function testGetCompassDirectionWithLargeDegrees(float $degrees, string $expectedDirection): void
    {
        $direction = WaveDirection::fromDegrees($degrees);

        $this->assertSame($expectedDirection, $direction->getCompassDirection());
    }

    public static function largeDegreesProvider(): array
    {
        return [
            // Degrees larger than 360 should wrap around
            'One full rotation plus 90 degrees' => [450.0, 'O'],
            'Two full rotations' => [720.0, 'N'],
            'Two full rotations plus 180 degrees' => [900.0, 'Z'],
            'Multiple rotations plus 45 degrees' => [1125.0, 'NO'],
        ];
    }

    public function testGetCompassDirectionReturnsNullForUnknown(): void
    {
        $direction = WaveDirection::unknown();

        $this->assertNull($direction->getCompassDirection());
    }

    public function testIsKnownReturnsTrueForValidDegrees(): void
    {
        $direction = WaveDirection::fromDegrees(123.45);

        $this->assertTrue($direction->isKnown());
    }

    public function testIsKnownReturnsFalseForNull(): void
    {
        $direction = WaveDirection::fromDegrees(null);

        $this->assertFalse($direction->isKnown());
    }

    public function testFromDegreesWithFloatingPointPrecision(): void
    {
        $direction = WaveDirection::fromDegrees(123.456789);

        $this->assertSame(123.456789, $direction->getDegrees());
        $this->assertSame('OZO', $direction->getCompassDirection());
    }
}
