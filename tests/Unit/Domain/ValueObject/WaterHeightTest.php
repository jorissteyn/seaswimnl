<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\WaterHeight;

final class WaterHeightTest extends TestCase
{
    public function testFromMeters(): void
    {
        $height = WaterHeight::fromMeters(0.75);

        $this->assertSame(0.75, $height->getMeters());
        $this->assertTrue($height->isKnown());
    }

    public function testFromMetersWithNull(): void
    {
        $height = WaterHeight::fromMeters(null);

        $this->assertNull($height->getMeters());
        $this->assertFalse($height->isKnown());
    }

    public function testUnknown(): void
    {
        $height = WaterHeight::unknown();

        $this->assertNull($height->getMeters());
        $this->assertFalse($height->isKnown());
    }
}
