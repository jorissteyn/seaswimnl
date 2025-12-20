<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\WaveHeight;

final class WaveHeightTest extends TestCase
{
    public function testFromMeters(): void
    {
        $height = WaveHeight::fromMeters(1.5);

        $this->assertSame(1.5, $height->getMeters());
        $this->assertTrue($height->isKnown());
    }

    public function testFromMetersWithNull(): void
    {
        $height = WaveHeight::fromMeters(null);

        $this->assertNull($height->getMeters());
        $this->assertFalse($height->isKnown());
    }

    public function testUnknown(): void
    {
        $height = WaveHeight::unknown();

        $this->assertNull($height->getMeters());
        $this->assertFalse($height->isKnown());
    }
}
