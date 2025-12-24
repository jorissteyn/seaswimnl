<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\Sunpower;

final class SunpowerTest extends TestCase
{
    public function testFromWattsPerSquareMeter(): void
    {
        $sunpower = Sunpower::fromWattsPerSquareMeter(450.5);

        $this->assertSame(450.5, $sunpower->getValue());
        $this->assertTrue($sunpower->isKnown());
    }

    public function testFromWattsPerSquareMeterWithNull(): void
    {
        $sunpower = Sunpower::fromWattsPerSquareMeter(null);

        $this->assertNull($sunpower->getValue());
        $this->assertFalse($sunpower->isKnown());
    }

    public function testUnknown(): void
    {
        $sunpower = Sunpower::unknown();

        $this->assertNull($sunpower->getValue());
        $this->assertFalse($sunpower->isKnown());
        $this->assertSame('Unknown', $sunpower->getLevel());
    }

    #[DataProvider('levelProvider')]
    public function testGetLevel(float $value, string $expectedLevel): void
    {
        $sunpower = Sunpower::fromWattsPerSquareMeter($value);

        $this->assertSame($expectedLevel, $sunpower->getLevel());
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function levelProvider(): array
    {
        return [
            'none (0)' => [0.0, 'None'],
            'low (100)' => [100.0, 'Low'],
            'low (199)' => [199.0, 'Low'],
            'moderate (200)' => [200.0, 'Moderate'],
            'moderate (350)' => [350.0, 'Moderate'],
            'good (400)' => [400.0, 'Good'],
            'good (600)' => [600.0, 'Good'],
            'strong (700)' => [700.0, 'Strong'],
            'strong (1000)' => [1000.0, 'Strong'],
        ];
    }
}
