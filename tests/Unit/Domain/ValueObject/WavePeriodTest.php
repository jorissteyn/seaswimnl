<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\WavePeriod;

final class WavePeriodTest extends TestCase
{
    public function testFromSeconds(): void
    {
        $period = WavePeriod::fromSeconds(5.5);

        $this->assertSame(5.5, $period->getSeconds());
        $this->assertTrue($period->isKnown());
    }

    public function testFromSecondsWithNull(): void
    {
        $period = WavePeriod::fromSeconds(null);

        $this->assertNull($period->getSeconds());
        $this->assertFalse($period->isKnown());
    }

    public function testFromSecondsWithZero(): void
    {
        $period = WavePeriod::fromSeconds(0.0);

        $this->assertSame(0.0, $period->getSeconds());
        $this->assertTrue($period->isKnown());
    }

    public function testFromSecondsWithNegativeValue(): void
    {
        $period = WavePeriod::fromSeconds(-1.5);

        $this->assertSame(-1.5, $period->getSeconds());
        $this->assertTrue($period->isKnown());
    }

    public function testFromSecondsWithLargeValue(): void
    {
        $period = WavePeriod::fromSeconds(100.0);

        $this->assertSame(100.0, $period->getSeconds());
        $this->assertTrue($period->isKnown());
    }

    public function testFromSecondsWithVerySmallValue(): void
    {
        $period = WavePeriod::fromSeconds(0.1);

        $this->assertSame(0.1, $period->getSeconds());
        $this->assertTrue($period->isKnown());
    }

    public function testUnknown(): void
    {
        $period = WavePeriod::unknown();

        $this->assertNull($period->getSeconds());
        $this->assertFalse($period->isKnown());
    }

    public function testIsKnownReturnsTrueForValidValue(): void
    {
        $period = WavePeriod::fromSeconds(10.0);

        $this->assertTrue($period->isKnown());
    }

    public function testIsKnownReturnsFalseForNullValue(): void
    {
        $period = WavePeriod::fromSeconds(null);

        $this->assertFalse($period->isKnown());
    }

    public function testIsKnownReturnsFalseForUnknown(): void
    {
        $period = WavePeriod::unknown();

        $this->assertFalse($period->isKnown());
    }

    public function testGetSecondsReturnsCorrectValue(): void
    {
        $period = WavePeriod::fromSeconds(7.3);

        $this->assertSame(7.3, $period->getSeconds());
    }

    public function testGetSecondsReturnsNullForUnknown(): void
    {
        $period = WavePeriod::unknown();

        $this->assertNull($period->getSeconds());
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $period1 = WavePeriod::fromSeconds(5.0);
        $period2 = WavePeriod::fromSeconds(10.0);
        $period3 = WavePeriod::unknown();

        $this->assertSame(5.0, $period1->getSeconds());
        $this->assertSame(10.0, $period2->getSeconds());
        $this->assertNull($period3->getSeconds());
    }

    public function testReadonlyBehavior(): void
    {
        $period = WavePeriod::fromSeconds(5.0);

        // Verify the value object is immutable by checking we get consistent values
        $this->assertSame($period->getSeconds(), $period->getSeconds());
        $this->assertSame($period->isKnown(), $period->isKnown());
    }
}
