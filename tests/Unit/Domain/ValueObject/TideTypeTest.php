<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\TideType;

final class TideTypeTest extends TestCase
{
    public function testHighCase(): void
    {
        $type = TideType::High;

        $this->assertSame('high', $type->value);
        $this->assertSame('High tide', $type->getLabel());
    }

    public function testLowCase(): void
    {
        $type = TideType::Low;

        $this->assertSame('low', $type->value);
        $this->assertSame('Low tide', $type->getLabel());
    }

    public function testFromStringValue(): void
    {
        $high = TideType::from('high');
        $low = TideType::from('low');

        $this->assertSame(TideType::High, $high);
        $this->assertSame(TideType::Low, $low);
    }

    public function testTryFromValidStringValue(): void
    {
        $high = TideType::tryFrom('high');
        $low = TideType::tryFrom('low');

        $this->assertSame(TideType::High, $high);
        $this->assertSame(TideType::Low, $low);
    }

    public function testTryFromInvalidStringValue(): void
    {
        $result = TideType::tryFrom('invalid');

        $this->assertNull($result);
    }

    public function testTryFromEmptyString(): void
    {
        $result = TideType::tryFrom('');

        $this->assertNull($result);
    }

    public function testTryFromMixedCase(): void
    {
        $upperHigh = TideType::tryFrom('HIGH');
        $upperLow = TideType::tryFrom('LOW');
        $mixedHigh = TideType::tryFrom('High');
        $mixedLow = TideType::tryFrom('Low');

        $this->assertNull($upperHigh);
        $this->assertNull($upperLow);
        $this->assertNull($mixedHigh);
        $this->assertNull($mixedLow);
    }

    public function testCasesMethod(): void
    {
        $cases = TideType::cases();

        $this->assertCount(2, $cases);
        $this->assertSame(TideType::High, $cases[0]);
        $this->assertSame(TideType::Low, $cases[1]);
    }

    public function testEnumEquality(): void
    {
        $high1 = TideType::High;
        $high2 = TideType::from('high');

        $this->assertSame($high1, $high2);
        $this->assertTrue($high1 === $high2);
    }

    public function testEnumInequality(): void
    {
        $high = TideType::High;
        $low = TideType::Low;

        $this->assertNotSame($high, $low);
        $this->assertFalse($high === $low);
    }

    public function testLabelMethodReturnsConsistentValues(): void
    {
        // Test that calling getLabel() multiple times returns the same value
        $type = TideType::High;

        $this->assertSame($type->getLabel(), $type->getLabel());
    }

    public function testAllLabelsAreUnique(): void
    {
        $labels = array_map(
            fn (TideType $case) => $case->getLabel(),
            TideType::cases()
        );

        $this->assertCount(2, $labels);
        $this->assertCount(2, array_unique($labels));
    }

    public function testLabelTypesAreStrings(): void
    {
        foreach (TideType::cases() as $case) {
            $this->assertIsString($case->getLabel());
        }
    }

    public function testLabelsAreNonEmpty(): void
    {
        foreach (TideType::cases() as $case) {
            $this->assertNotEmpty($case->getLabel());
        }
    }

    public function testValueTypesAreStrings(): void
    {
        foreach (TideType::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }

    public function testValuesAreNonEmpty(): void
    {
        foreach (TideType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testHighLabelFormat(): void
    {
        // Ensure labels follow expected format with proper capitalization
        $label = TideType::High->getLabel();

        $this->assertStringContainsString('High', $label);
        $this->assertStringContainsString('tide', $label);
    }

    public function testLowLabelFormat(): void
    {
        // Ensure labels follow expected format with proper capitalization
        $label = TideType::Low->getLabel();

        $this->assertStringContainsString('Low', $label);
        $this->assertStringContainsString('tide', $label);
    }

    public function testAllValuesAreLowercase(): void
    {
        foreach (TideType::cases() as $case) {
            $this->assertSame(strtolower($case->value), $case->value);
        }
    }

    public function testEnumCanBeUsedInMatchExpression(): void
    {
        $high = TideType::High;
        $low = TideType::Low;

        $highResult = match ($high) {
            TideType::High => 'matched high',
            TideType::Low => 'matched low',
        };

        $lowResult = match ($low) {
            TideType::High => 'matched high',
            TideType::Low => 'matched low',
        };

        $this->assertSame('matched high', $highResult);
        $this->assertSame('matched low', $lowResult);
    }

    public function testEnumCanBeUsedInArrayKey(): void
    {
        $data = [
            TideType::High->value => 'high tide data',
            TideType::Low->value => 'low tide data',
        ];

        $this->assertArrayHasKey('high', $data);
        $this->assertArrayHasKey('low', $data);
        $this->assertSame('high tide data', $data['high']);
        $this->assertSame('low tide data', $data['low']);
    }

    public function testFromMethodThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);

        TideType::from('invalid');
    }

    public function testFromMethodThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\ValueError::class);

        TideType::from('');
    }

    public function testSerializationCompatibility(): void
    {
        // Test that enum values can be serialized and remain consistent
        $high = TideType::High;
        $serialized = serialize($high);
        $unserialized = unserialize($serialized);

        $this->assertSame($high, $unserialized);
        $this->assertSame('high', $unserialized->value);
        $this->assertSame('High tide', $unserialized->getLabel());
    }

    public function testJsonSerializationValue(): void
    {
        $high = TideType::High;
        $low = TideType::Low;

        $highJson = json_encode($high);
        $lowJson = json_encode($low);

        $this->assertSame('"high"', $highJson);
        $this->assertSame('"low"', $lowJson);
    }
}
