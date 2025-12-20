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

    public function testUnknown(): void
    {
        $speed = WindSpeed::unknown();

        $this->assertNull($speed->getMetersPerSecond());
        $this->assertNull($speed->getKilometersPerHour());
        $this->assertFalse($speed->isKnown());
    }
}
