<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\UVIndex;

final class UVIndexTest extends TestCase
{
    public function testFromValue(): void
    {
        $uv = UVIndex::fromValue(5);

        $this->assertSame(5, $uv->getValue());
        $this->assertTrue($uv->isKnown());
    }

    public function testFromValueWithNull(): void
    {
        $uv = UVIndex::fromValue(null);

        $this->assertNull($uv->getValue());
        $this->assertFalse($uv->isKnown());
    }

    public function testUnknown(): void
    {
        $uv = UVIndex::unknown();

        $this->assertNull($uv->getValue());
        $this->assertFalse($uv->isKnown());
        $this->assertSame('Unknown', $uv->getLevel());
    }

    #[DataProvider('levelProvider')]
    public function testGetLevel(int $value, string $expectedLevel): void
    {
        $uv = UVIndex::fromValue($value);

        $this->assertSame($expectedLevel, $uv->getLevel());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function levelProvider(): array
    {
        return [
            'low (0)' => [0, 'Low'],
            'low (2)' => [2, 'Low'],
            'moderate (3)' => [3, 'Moderate'],
            'moderate (5)' => [5, 'Moderate'],
            'high (6)' => [6, 'High'],
            'high (7)' => [7, 'High'],
            'very high (8)' => [8, 'Very High'],
            'very high (10)' => [10, 'Very High'],
            'extreme (11)' => [11, 'Extreme'],
            'extreme (15)' => [15, 'Extreme'],
        ];
    }
}
