<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\ComfortIndex;

final class ComfortIndexTest extends TestCase
{
    public function testConstructorWithMinimumValue(): void
    {
        $comfortIndex = new ComfortIndex(1);

        $this->assertSame(1, $comfortIndex->getValue());
        $this->assertSame('Very Poor', $comfortIndex->getLabel());
    }

    public function testConstructorWithMaximumValue(): void
    {
        $comfortIndex = new ComfortIndex(10);

        $this->assertSame(10, $comfortIndex->getValue());
        $this->assertSame('Excellent', $comfortIndex->getLabel());
    }

    public function testConstructorWithValueBelowMinimumClampsToOne(): void
    {
        $comfortIndex = new ComfortIndex(0);

        $this->assertSame(1, $comfortIndex->getValue());
        $this->assertSame('Very Poor', $comfortIndex->getLabel());
    }

    public function testConstructorWithLargeNegativeValueClampsToOne(): void
    {
        $comfortIndex = new ComfortIndex(-100);

        $this->assertSame(1, $comfortIndex->getValue());
        $this->assertSame('Very Poor', $comfortIndex->getLabel());
    }

    public function testConstructorWithValueAboveMaximumClampsToTen(): void
    {
        $comfortIndex = new ComfortIndex(11);

        $this->assertSame(10, $comfortIndex->getValue());
        $this->assertSame('Excellent', $comfortIndex->getLabel());
    }

    public function testConstructorWithLargePositiveValueClampsToTen(): void
    {
        $comfortIndex = new ComfortIndex(1000);

        $this->assertSame(10, $comfortIndex->getValue());
        $this->assertSame('Excellent', $comfortIndex->getLabel());
    }

    public function testConstructorWithMidRangeValue(): void
    {
        $comfortIndex = new ComfortIndex(5);

        $this->assertSame(5, $comfortIndex->getValue());
        $this->assertSame('Fair', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsVeryPoorForValueOne(): void
    {
        $comfortIndex = new ComfortIndex(1);

        $this->assertSame('Very Poor', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsPoorForValueTwo(): void
    {
        $comfortIndex = new ComfortIndex(2);

        $this->assertSame('Poor', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsPoorForValueThree(): void
    {
        $comfortIndex = new ComfortIndex(3);

        $this->assertSame('Poor', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsFairForValueFour(): void
    {
        $comfortIndex = new ComfortIndex(4);

        $this->assertSame('Fair', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsFairForValueFive(): void
    {
        $comfortIndex = new ComfortIndex(5);

        $this->assertSame('Fair', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsGoodForValueSix(): void
    {
        $comfortIndex = new ComfortIndex(6);

        $this->assertSame('Good', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsGoodForValueSeven(): void
    {
        $comfortIndex = new ComfortIndex(7);

        $this->assertSame('Good', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsExcellentForValueEight(): void
    {
        $comfortIndex = new ComfortIndex(8);

        $this->assertSame('Excellent', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsExcellentForValueNine(): void
    {
        $comfortIndex = new ComfortIndex(9);

        $this->assertSame('Excellent', $comfortIndex->getLabel());
    }

    public function testGetLabelReturnsExcellentForValueTen(): void
    {
        $comfortIndex = new ComfortIndex(10);

        $this->assertSame('Excellent', $comfortIndex->getLabel());
    }

    public function testGetValueReturnsConsistentValue(): void
    {
        $comfortIndex = new ComfortIndex(7);

        $this->assertSame($comfortIndex->getValue(), $comfortIndex->getValue());
        $this->assertSame(7, $comfortIndex->getValue());
    }

    public function testGetLabelReturnsConsistentValue(): void
    {
        $comfortIndex = new ComfortIndex(6);

        $this->assertSame($comfortIndex->getLabel(), $comfortIndex->getLabel());
        $this->assertSame('Good', $comfortIndex->getLabel());
    }

    public function testGetValueReturnsInteger(): void
    {
        $comfortIndex = new ComfortIndex(5);

        $this->assertIsInt($comfortIndex->getValue());
    }

    public function testGetLabelReturnsString(): void
    {
        $comfortIndex = new ComfortIndex(5);

        $this->assertIsString($comfortIndex->getLabel());
    }

    public function testGetLabelReturnsNonEmptyString(): void
    {
        foreach (range(1, 10) as $value) {
            $comfortIndex = new ComfortIndex($value);
            $label = $comfortIndex->getLabel();

            $this->assertNotEmpty($label);
        }
    }

    public function testAllValidValuesProduceValidLabels(): void
    {
        $expectedLabels = [
            1 => 'Very Poor',
            2 => 'Poor',
            3 => 'Poor',
            4 => 'Fair',
            5 => 'Fair',
            6 => 'Good',
            7 => 'Good',
            8 => 'Excellent',
            9 => 'Excellent',
            10 => 'Excellent',
        ];

        foreach ($expectedLabels as $value => $expectedLabel) {
            $comfortIndex = new ComfortIndex($value);

            $this->assertSame($expectedLabel, $comfortIndex->getLabel());
        }
    }

    public function testReadonlyPropertyPreventsMutation(): void
    {
        $comfortIndex = new ComfortIndex(5);
        $initialValue = $comfortIndex->getValue();

        // Create a new instance to verify immutability pattern
        $newComfortIndex = new ComfortIndex(8);

        $this->assertSame(5, $initialValue);
        $this->assertSame(5, $comfortIndex->getValue());
        $this->assertSame(8, $newComfortIndex->getValue());
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $comfortIndex1 = new ComfortIndex(3);
        $comfortIndex2 = new ComfortIndex(7);
        $comfortIndex3 = new ComfortIndex(10);

        $this->assertSame(3, $comfortIndex1->getValue());
        $this->assertSame(7, $comfortIndex2->getValue());
        $this->assertSame(10, $comfortIndex3->getValue());

        $this->assertSame('Poor', $comfortIndex1->getLabel());
        $this->assertSame('Good', $comfortIndex2->getLabel());
        $this->assertSame('Excellent', $comfortIndex3->getLabel());
    }

    public function testClampingBehaviorWithExtremeValues(): void
    {
        $veryLow = new ComfortIndex(PHP_INT_MIN);
        $veryHigh = new ComfortIndex(PHP_INT_MAX);

        $this->assertSame(1, $veryLow->getValue());
        $this->assertSame(10, $veryHigh->getValue());
    }

    public function testLabelBoundaryBetweenVeryPoorAndPoor(): void
    {
        $veryPoor = new ComfortIndex(1);
        $poor = new ComfortIndex(2);

        $this->assertSame('Very Poor', $veryPoor->getLabel());
        $this->assertSame('Poor', $poor->getLabel());
        $this->assertNotSame($veryPoor->getLabel(), $poor->getLabel());
    }

    public function testLabelBoundaryBetweenPoorAndFair(): void
    {
        $poor = new ComfortIndex(3);
        $fair = new ComfortIndex(4);

        $this->assertSame('Poor', $poor->getLabel());
        $this->assertSame('Fair', $fair->getLabel());
        $this->assertNotSame($poor->getLabel(), $fair->getLabel());
    }

    public function testLabelBoundaryBetweenFairAndGood(): void
    {
        $fair = new ComfortIndex(5);
        $good = new ComfortIndex(6);

        $this->assertSame('Fair', $fair->getLabel());
        $this->assertSame('Good', $good->getLabel());
        $this->assertNotSame($fair->getLabel(), $good->getLabel());
    }

    public function testLabelBoundaryBetweenGoodAndExcellent(): void
    {
        $good = new ComfortIndex(7);
        $excellent = new ComfortIndex(8);

        $this->assertSame('Good', $good->getLabel());
        $this->assertSame('Excellent', $excellent->getLabel());
        $this->assertNotSame($good->getLabel(), $excellent->getLabel());
    }

    public function testObjectIsImmutable(): void
    {
        $comfortIndex = new ComfortIndex(6);
        $value1 = $comfortIndex->getValue();
        $label1 = $comfortIndex->getLabel();

        // Call methods multiple times
        $value2 = $comfortIndex->getValue();
        $label2 = $comfortIndex->getLabel();

        $this->assertSame($value1, $value2);
        $this->assertSame($label1, $label2);
    }

    public function testZeroValueClampsToOne(): void
    {
        $comfortIndex = new ComfortIndex(0);

        $this->assertSame(1, $comfortIndex->getValue());
    }

    public function testNegativeOneValueClampsToOne(): void
    {
        $comfortIndex = new ComfortIndex(-1);

        $this->assertSame(1, $comfortIndex->getValue());
    }

    public function testElevenValueClampsToTen(): void
    {
        $comfortIndex = new ComfortIndex(11);

        $this->assertSame(10, $comfortIndex->getValue());
    }
}
