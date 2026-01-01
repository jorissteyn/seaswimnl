<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\WindSpeed;

final class WindSpeedTest extends TestCase
{
    public function testFromMetersPerSecond(): void
    {
        $speed = WindSpeed::fromMetersPerSecond(5.0);

        $this->assertSame(5.0, $speed->getMetersPerSecond());
        $this->assertSame(18.0, $speed->getKilometersPerHour());
        $this->assertTrue($speed->isKnown());
    }

    public function testFromMetersPerSecondWithNull(): void
    {
        $speed = WindSpeed::fromMetersPerSecond(null);

        $this->assertNull($speed->getMetersPerSecond());
        $this->assertNull($speed->getKilometersPerHour());
        $this->assertFalse($speed->isKnown());
    }

    public function testFromKnots(): void
    {
        $speed = WindSpeed::fromKnots(10.0);

        // 10 knots = 10 * 0.514444 = 5.14444 m/s
        $this->assertEqualsWithDelta(5.14444, $speed->getMetersPerSecond(), 0.00001);
        $this->assertEqualsWithDelta(18.51998, $speed->getKilometersPerHour(), 0.00001);
        $this->assertTrue($speed->isKnown());
    }

    public function testFromKnotsWithZero(): void
    {
        $speed = WindSpeed::fromKnots(0.0);

        $this->assertSame(0.0, $speed->getMetersPerSecond());
        $this->assertSame(0.0, $speed->getKilometersPerHour());
        $this->assertTrue($speed->isKnown());
    }

    public function testFromKnotsWithNull(): void
    {
        $speed = WindSpeed::fromKnots(null);

        $this->assertNull($speed->getMetersPerSecond());
        $this->assertNull($speed->getKilometersPerHour());
        $this->assertFalse($speed->isKnown());
    }

    public function testUnknown(): void
    {
        $speed = WindSpeed::unknown();

        $this->assertNull($speed->getMetersPerSecond());
        $this->assertNull($speed->getKilometersPerHour());
        $this->assertFalse($speed->isKnown());
    }

    public function testGetKilometersPerHour(): void
    {
        $speed = WindSpeed::fromMetersPerSecond(10.0);

        // 10 m/s * 3.6 = 36 km/h
        $this->assertSame(36.0, $speed->getKilometersPerHour());
    }

    public function testGetBeaufortReturnsNullForUnknownSpeed(): void
    {
        $speed = WindSpeed::unknown();

        $this->assertNull($speed->getBeaufort());
    }

    /**
     * @dataProvider beaufortScaleProvider
     */
    public function testGetBeaufortScale(float $metersPerSecond, int $expectedBeaufort): void
    {
        $speed = WindSpeed::fromMetersPerSecond($metersPerSecond);

        $this->assertSame($expectedBeaufort, $speed->getBeaufort());
    }

    public static function beaufortScaleProvider(): array
    {
        return [
            // Beaufort 0: < 0.3 m/s
            'Calm - 0 m/s' => [0.0, 0],
            'Calm - 0.2 m/s' => [0.2, 0],
            'Calm - boundary' => [0.29, 0],

            // Beaufort 1: 0.3 - 1.5 m/s
            'Light air - lower boundary' => [0.3, 1],
            'Light air - middle' => [1.0, 1],
            'Light air - upper boundary' => [1.5, 1],

            // Beaufort 2: 1.6 - 3.3 m/s
            'Light breeze - lower boundary' => [1.6, 2],
            'Light breeze - middle' => [2.5, 2],
            'Light breeze - upper boundary' => [3.3, 2],

            // Beaufort 3: 3.4 - 5.4 m/s
            'Gentle breeze - lower boundary' => [3.4, 3],
            'Gentle breeze - middle' => [4.5, 3],
            'Gentle breeze - upper boundary' => [5.4, 3],

            // Beaufort 4: 5.5 - 7.9 m/s
            'Moderate breeze - lower boundary' => [5.5, 4],
            'Moderate breeze - middle' => [6.7, 4],
            'Moderate breeze - upper boundary' => [7.9, 4],

            // Beaufort 5: 8.0 - 10.7 m/s
            'Fresh breeze - lower boundary' => [8.0, 5],
            'Fresh breeze - middle' => [9.3, 5],
            'Fresh breeze - upper boundary' => [10.7, 5],

            // Beaufort 6: 10.8 - 13.8 m/s
            'Strong breeze - lower boundary' => [10.8, 6],
            'Strong breeze - middle' => [12.3, 6],
            'Strong breeze - upper boundary' => [13.8, 6],

            // Beaufort 7: 13.9 - 17.1 m/s
            'Near gale - lower boundary' => [13.9, 7],
            'Near gale - middle' => [15.5, 7],
            'Near gale - upper boundary' => [17.1, 7],

            // Beaufort 8: 17.2 - 20.7 m/s
            'Gale - lower boundary' => [17.2, 8],
            'Gale - middle' => [19.0, 8],
            'Gale - upper boundary' => [20.7, 8],

            // Beaufort 9: 20.8 - 24.4 m/s
            'Strong gale - lower boundary' => [20.8, 9],
            'Strong gale - middle' => [22.6, 9],
            'Strong gale - upper boundary' => [24.4, 9],

            // Beaufort 10: 24.5 - 28.4 m/s
            'Storm - lower boundary' => [24.5, 10],
            'Storm - middle' => [26.5, 10],
            'Storm - upper boundary' => [28.4, 10],

            // Beaufort 11: 28.5 - 32.6 m/s
            'Violent storm - lower boundary' => [28.5, 11],
            'Violent storm - middle' => [30.5, 11],
            'Violent storm - upper boundary' => [32.6, 11],

            // Beaufort 12: >= 32.7 m/s
            'Hurricane - lower boundary' => [32.7, 12],
            'Hurricane - high value' => [40.0, 12],
            'Hurricane - very high value' => [100.0, 12],
        ];
    }

    public function testGetBeaufortLabelReturnsNullForUnknownSpeed(): void
    {
        $speed = WindSpeed::unknown();

        $this->assertNull($speed->getBeaufortLabel());
    }

    /**
     * @dataProvider beaufortLabelProvider
     */
    public function testGetBeaufortLabel(float $metersPerSecond, string $expectedLabel): void
    {
        $speed = WindSpeed::fromMetersPerSecond($metersPerSecond);

        $this->assertSame($expectedLabel, $speed->getBeaufortLabel());
    }

    public static function beaufortLabelProvider(): array
    {
        return [
            'Calm' => [0.0, 'Calm'],
            'Light air' => [1.0, 'Light air'],
            'Light breeze' => [2.0, 'Light breeze'],
            'Gentle breeze' => [4.0, 'Gentle breeze'],
            'Moderate breeze' => [6.0, 'Moderate breeze'],
            'Fresh breeze' => [9.0, 'Fresh breeze'],
            'Strong breeze' => [12.0, 'Strong breeze'],
            'Near gale' => [15.0, 'Near gale'],
            'Gale' => [18.0, 'Gale'],
            'Strong gale' => [22.0, 'Strong gale'],
            'Storm' => [26.0, 'Storm'],
            'Violent storm' => [30.0, 'Violent storm'],
            'Hurricane' => [35.0, 'Hurricane'],
        ];
    }

    public function testIsKnownWithKnownSpeed(): void
    {
        $speed = WindSpeed::fromMetersPerSecond(5.0);

        $this->assertTrue($speed->isKnown());
    }

    public function testIsKnownWithZeroSpeed(): void
    {
        $speed = WindSpeed::fromMetersPerSecond(0.0);

        $this->assertTrue($speed->isKnown());
    }

    public function testIsKnownWithUnknownSpeed(): void
    {
        $speed = WindSpeed::unknown();

        $this->assertFalse($speed->isKnown());
    }
}
