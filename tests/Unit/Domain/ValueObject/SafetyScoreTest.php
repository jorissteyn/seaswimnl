<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\SafetyScore;

final class SafetyScoreTest extends TestCase
{
    public function testGreenCase(): void
    {
        $score = SafetyScore::Green;

        $this->assertSame('green', $score->value);
        $this->assertSame('Safe', $score->getLabel());
        $this->assertSame('Conditions are good for swimming', $score->getDescription());
    }

    public function testYellowCase(): void
    {
        $score = SafetyScore::Yellow;

        $this->assertSame('yellow', $score->value);
        $this->assertSame('Caution', $score->getLabel());
        $this->assertSame('Swim with caution - some conditions are suboptimal', $score->getDescription());
    }

    public function testRedCase(): void
    {
        $score = SafetyScore::Red;

        $this->assertSame('red', $score->value);
        $this->assertSame('Unsafe', $score->getLabel());
        $this->assertSame('Swimming not recommended due to unsafe conditions', $score->getDescription());
    }

    public function testFromStringValue(): void
    {
        $green = SafetyScore::from('green');
        $yellow = SafetyScore::from('yellow');
        $red = SafetyScore::from('red');

        $this->assertSame(SafetyScore::Green, $green);
        $this->assertSame(SafetyScore::Yellow, $yellow);
        $this->assertSame(SafetyScore::Red, $red);
    }

    public function testTryFromValidStringValue(): void
    {
        $green = SafetyScore::tryFrom('green');
        $yellow = SafetyScore::tryFrom('yellow');
        $red = SafetyScore::tryFrom('red');

        $this->assertSame(SafetyScore::Green, $green);
        $this->assertSame(SafetyScore::Yellow, $yellow);
        $this->assertSame(SafetyScore::Red, $red);
    }

    public function testTryFromInvalidStringValue(): void
    {
        $result = SafetyScore::tryFrom('invalid');

        $this->assertNull($result);
    }

    public function testTryFromEmptyString(): void
    {
        $result = SafetyScore::tryFrom('');

        $this->assertNull($result);
    }

    public function testCasesMethod(): void
    {
        $cases = SafetyScore::cases();

        $this->assertCount(3, $cases);
        $this->assertSame(SafetyScore::Green, $cases[0]);
        $this->assertSame(SafetyScore::Yellow, $cases[1]);
        $this->assertSame(SafetyScore::Red, $cases[2]);
    }

    public function testEnumEquality(): void
    {
        $green1 = SafetyScore::Green;
        $green2 = SafetyScore::from('green');

        $this->assertSame($green1, $green2);
        $this->assertTrue($green1 === $green2);
    }

    public function testEnumInequality(): void
    {
        $green = SafetyScore::Green;
        $yellow = SafetyScore::Yellow;
        $red = SafetyScore::Red;

        $this->assertNotSame($green, $yellow);
        $this->assertNotSame($green, $red);
        $this->assertNotSame($yellow, $red);
    }

    public function testLabelMethodReturnsConsistentValues(): void
    {
        // Test that calling getLabel() multiple times returns the same value
        $score = SafetyScore::Yellow;

        $this->assertSame($score->getLabel(), $score->getLabel());
    }

    public function testDescriptionMethodReturnsConsistentValues(): void
    {
        // Test that calling getDescription() multiple times returns the same value
        $score = SafetyScore::Red;

        $this->assertSame($score->getDescription(), $score->getDescription());
    }

    public function testAllLabelsAreUnique(): void
    {
        $labels = array_map(
            fn (SafetyScore $case) => $case->getLabel(),
            SafetyScore::cases()
        );

        $this->assertCount(3, $labels);
        $this->assertCount(3, array_unique($labels));
    }

    public function testAllDescriptionsAreUnique(): void
    {
        $descriptions = array_map(
            fn (SafetyScore $case) => $case->getDescription(),
            SafetyScore::cases()
        );

        $this->assertCount(3, $descriptions);
        $this->assertCount(3, array_unique($descriptions));
    }

    public function testLabelAndDescriptionTypesAreStrings(): void
    {
        foreach (SafetyScore::cases() as $case) {
            $this->assertIsString($case->getLabel());
            $this->assertIsString($case->getDescription());
        }
    }

    public function testLabelAndDescriptionAreNonEmpty(): void
    {
        foreach (SafetyScore::cases() as $case) {
            $this->assertNotEmpty($case->getLabel());
            $this->assertNotEmpty($case->getDescription());
        }
    }
}
