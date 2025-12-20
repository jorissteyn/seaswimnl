<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\WaterQuality;

final class WaterQualityTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('good', WaterQuality::Good->value);
        $this->assertSame('moderate', WaterQuality::Moderate->value);
        $this->assertSame('poor', WaterQuality::Poor->value);
        $this->assertSame('unknown', WaterQuality::Unknown->value);
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Good', WaterQuality::Good->getLabel());
        $this->assertSame('Moderate', WaterQuality::Moderate->getLabel());
        $this->assertSame('Poor', WaterQuality::Poor->getLabel());
        $this->assertSame('Unknown', WaterQuality::Unknown->getLabel());
    }
}
