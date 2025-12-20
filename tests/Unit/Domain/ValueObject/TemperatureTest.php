<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\Temperature;

final class TemperatureTest extends TestCase
{
    public function testFromCelsius(): void
    {
        $temp = Temperature::fromCelsius(18.5);

        $this->assertSame(18.5, $temp->getCelsius());
        $this->assertTrue($temp->isKnown());
    }

    public function testFromCelsiusWithNull(): void
    {
        $temp = Temperature::fromCelsius(null);

        $this->assertNull($temp->getCelsius());
        $this->assertFalse($temp->isKnown());
    }

    public function testUnknown(): void
    {
        $temp = Temperature::unknown();

        $this->assertNull($temp->getCelsius());
        $this->assertFalse($temp->isKnown());
    }
}
