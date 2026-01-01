<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\TideEvent;
use Seaswim\Domain\ValueObject\TideType;

final class TideEventTest extends TestCase
{
    public function testConstructWithHighTide(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::High, $time, 450.0);

        $this->assertSame(TideType::High, $tideEvent->getType());
        $this->assertSame($time, $tideEvent->getTime());
        $this->assertSame(450.0, $tideEvent->getHeightCm());
    }

    public function testConstructWithLowTide(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 18:30:00');
        $tideEvent = new TideEvent(TideType::Low, $time, 120.5);

        $this->assertSame(TideType::Low, $tideEvent->getType());
        $this->assertSame($time, $tideEvent->getTime());
        $this->assertSame(120.5, $tideEvent->getHeightCm());
    }

    public function testGetHeightMetersConversion(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::High, $time, 450.0);

        $this->assertSame(4.5, $tideEvent->getHeightMeters());
    }

    public function testGetHeightMetersWithZero(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::Low, $time, 0.0);

        $this->assertSame(0.0, $tideEvent->getHeightMeters());
    }

    public function testGetHeightMetersWithNegativeValue(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::Low, $time, -50.0);

        $this->assertSame(-0.5, $tideEvent->getHeightMeters());
    }

    public function testGetHeightMetersWithDecimalPrecision(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::High, $time, 123.45);

        $this->assertEqualsWithDelta(1.2345, $tideEvent->getHeightMeters(), 0.00001);
    }

    public function testIsHighTideReturnsTrue(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::High, $time, 450.0);

        $this->assertTrue($tideEvent->isHighTide());
        $this->assertFalse($tideEvent->isLowTide());
    }

    public function testIsLowTideReturnsTrue(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 18:30:00');
        $tideEvent = new TideEvent(TideType::Low, $time, 120.5);

        $this->assertTrue($tideEvent->isLowTide());
        $this->assertFalse($tideEvent->isHighTide());
    }

    public function testGetTimeReturnsImmutableDateTime(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::High, $time, 450.0);

        $retrievedTime = $tideEvent->getTime();
        $this->assertInstanceOf(\DateTimeImmutable::class, $retrievedTime);
        $this->assertEquals($time, $retrievedTime);
    }

    public function testWithVeryLargeHeight(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::High, $time, 10000.0);

        $this->assertSame(10000.0, $tideEvent->getHeightCm());
        $this->assertSame(100.0, $tideEvent->getHeightMeters());
    }

    public function testWithVerySmallHeight(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::Low, $time, 0.01);

        $this->assertSame(0.01, $tideEvent->getHeightCm());
        $this->assertEqualsWithDelta(0.0001, $tideEvent->getHeightMeters(), 0.000001);
    }

    public function testWithDifferentTimezones(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('America/New_York'));
        $tideEvent = new TideEvent(TideType::High, $time, 450.0);

        $retrievedTime = $tideEvent->getTime();
        $this->assertEquals('America/New_York', $retrievedTime->getTimezone()->getName());
        $this->assertEquals($time, $retrievedTime);
    }

    public function testReadonlyPropertyImmutability(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::High, $time, 450.0);

        // Verify that modifying the returned time doesn't affect the original
        $retrievedTime = $tideEvent->getTime();
        $modifiedTime = $retrievedTime->modify('+1 hour');

        $this->assertEquals($time, $tideEvent->getTime());
        $this->assertNotEquals($modifiedTime, $tideEvent->getTime());
    }

    public function testMultipleCallsReturnConsistentValues(): void
    {
        $time = new \DateTimeImmutable('2026-01-01 12:00:00');
        $tideEvent = new TideEvent(TideType::High, $time, 450.0);

        // Call getters multiple times to ensure consistency
        $this->assertSame($tideEvent->getType(), $tideEvent->getType());
        $this->assertSame($tideEvent->getHeightCm(), $tideEvent->getHeightCm());
        $this->assertSame($tideEvent->getHeightMeters(), $tideEvent->getHeightMeters());
        $this->assertSame($tideEvent->isHighTide(), $tideEvent->isHighTide());
        $this->assertSame($tideEvent->isLowTide(), $tideEvent->isLowTide());
    }
}
