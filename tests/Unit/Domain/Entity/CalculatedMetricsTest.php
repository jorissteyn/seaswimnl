<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Entity\CalculatedMetrics;
use Seaswim\Domain\ValueObject\ComfortIndex;
use Seaswim\Domain\ValueObject\SafetyScore;

final class CalculatedMetricsTest extends TestCase
{
    public function testConstructionWithGreenSafetyScoreAndExcellentComfort(): void
    {
        $safetyScore = SafetyScore::Green;
        $comfortIndex = new ComfortIndex(10);

        $metrics = new CalculatedMetrics($safetyScore, $comfortIndex);

        $this->assertSame($safetyScore, $metrics->getSafetyScore());
        $this->assertSame($comfortIndex, $metrics->getComfortIndex());
    }

    public function testConstructionWithYellowSafetyScoreAndGoodComfort(): void
    {
        $safetyScore = SafetyScore::Yellow;
        $comfortIndex = new ComfortIndex(7);

        $metrics = new CalculatedMetrics($safetyScore, $comfortIndex);

        $this->assertSame($safetyScore, $metrics->getSafetyScore());
        $this->assertSame($comfortIndex, $metrics->getComfortIndex());
    }

    public function testConstructionWithRedSafetyScoreAndPoorComfort(): void
    {
        $safetyScore = SafetyScore::Red;
        $comfortIndex = new ComfortIndex(1);

        $metrics = new CalculatedMetrics($safetyScore, $comfortIndex);

        $this->assertSame($safetyScore, $metrics->getSafetyScore());
        $this->assertSame($comfortIndex, $metrics->getComfortIndex());
    }

    public function testGetSafetyScoreReturnsSameInstance(): void
    {
        $safetyScore = SafetyScore::Green;
        $comfortIndex = new ComfortIndex(8);

        $metrics = new CalculatedMetrics($safetyScore, $comfortIndex);

        $this->assertSame($safetyScore, $metrics->getSafetyScore());
        $this->assertSame($safetyScore, $metrics->getSafetyScore(), 'Multiple calls should return same instance');
    }

    public function testGetComfortIndexReturnsSameInstance(): void
    {
        $safetyScore = SafetyScore::Yellow;
        $comfortIndex = new ComfortIndex(5);

        $metrics = new CalculatedMetrics($safetyScore, $comfortIndex);

        $this->assertSame($comfortIndex, $metrics->getComfortIndex());
        $this->assertSame($comfortIndex, $metrics->getComfortIndex(), 'Multiple calls should return same instance');
    }

    public function testAllSafetyScoreVariants(): void
    {
        $comfortIndex = new ComfortIndex(5);

        $greenMetrics = new CalculatedMetrics(SafetyScore::Green, $comfortIndex);
        $this->assertSame(SafetyScore::Green, $greenMetrics->getSafetyScore());
        $this->assertSame('Safe', $greenMetrics->getSafetyScore()->getLabel());

        $yellowMetrics = new CalculatedMetrics(SafetyScore::Yellow, $comfortIndex);
        $this->assertSame(SafetyScore::Yellow, $yellowMetrics->getSafetyScore());
        $this->assertSame('Caution', $yellowMetrics->getSafetyScore()->getLabel());

        $redMetrics = new CalculatedMetrics(SafetyScore::Red, $comfortIndex);
        $this->assertSame(SafetyScore::Red, $redMetrics->getSafetyScore());
        $this->assertSame('Unsafe', $redMetrics->getSafetyScore()->getLabel());
    }

    public function testComfortIndexBoundaryValues(): void
    {
        $safetyScore = SafetyScore::Green;

        $minComfort = new ComfortIndex(1);
        $minMetrics = new CalculatedMetrics($safetyScore, $minComfort);
        $this->assertSame(1, $minMetrics->getComfortIndex()->getValue());
        $this->assertSame('Very Poor', $minMetrics->getComfortIndex()->getLabel());

        $maxComfort = new ComfortIndex(10);
        $maxMetrics = new CalculatedMetrics($safetyScore, $maxComfort);
        $this->assertSame(10, $maxMetrics->getComfortIndex()->getValue());
        $this->assertSame('Excellent', $maxMetrics->getComfortIndex()->getLabel());
    }

    public function testComfortIndexWithVariousLabels(): void
    {
        $safetyScore = SafetyScore::Green;

        $veryPoorMetrics = new CalculatedMetrics($safetyScore, new ComfortIndex(1));
        $this->assertSame('Very Poor', $veryPoorMetrics->getComfortIndex()->getLabel());

        $poorMetrics = new CalculatedMetrics($safetyScore, new ComfortIndex(2));
        $this->assertSame('Poor', $poorMetrics->getComfortIndex()->getLabel());

        $fairMetrics = new CalculatedMetrics($safetyScore, new ComfortIndex(4));
        $this->assertSame('Fair', $fairMetrics->getComfortIndex()->getLabel());

        $goodMetrics = new CalculatedMetrics($safetyScore, new ComfortIndex(6));
        $this->assertSame('Good', $goodMetrics->getComfortIndex()->getLabel());

        $excellentMetrics = new CalculatedMetrics($safetyScore, new ComfortIndex(8));
        $this->assertSame('Excellent', $excellentMetrics->getComfortIndex()->getLabel());
    }

    public function testReadonlyImmutability(): void
    {
        $safetyScore = SafetyScore::Green;
        $comfortIndex = new ComfortIndex(7);

        $metrics = new CalculatedMetrics($safetyScore, $comfortIndex);

        // Verify getters return consistent values
        $firstSafetyCall = $metrics->getSafetyScore();
        $secondSafetyCall = $metrics->getSafetyScore();
        $this->assertSame($firstSafetyCall, $secondSafetyCall);

        $firstComfortCall = $metrics->getComfortIndex();
        $secondComfortCall = $metrics->getComfortIndex();
        $this->assertSame($firstComfortCall, $secondComfortCall);
    }

    public function testDifferentInstancesAreIndependent(): void
    {
        $metrics1 = new CalculatedMetrics(SafetyScore::Green, new ComfortIndex(8));
        $metrics2 = new CalculatedMetrics(SafetyScore::Red, new ComfortIndex(2));

        $this->assertNotSame($metrics1, $metrics2);
        $this->assertSame(SafetyScore::Green, $metrics1->getSafetyScore());
        $this->assertSame(SafetyScore::Red, $metrics2->getSafetyScore());
        $this->assertSame(8, $metrics1->getComfortIndex()->getValue());
        $this->assertSame(2, $metrics2->getComfortIndex()->getValue());
    }

    public function testWorstCaseScenario(): void
    {
        $safetyScore = SafetyScore::Red;
        $comfortIndex = new ComfortIndex(1);

        $metrics = new CalculatedMetrics($safetyScore, $comfortIndex);

        $this->assertSame(SafetyScore::Red, $metrics->getSafetyScore());
        $this->assertSame('Unsafe', $metrics->getSafetyScore()->getLabel());
        $this->assertSame('Swimming not recommended due to unsafe conditions', $metrics->getSafetyScore()->getDescription());
        $this->assertSame(1, $metrics->getComfortIndex()->getValue());
        $this->assertSame('Very Poor', $metrics->getComfortIndex()->getLabel());
    }

    public function testBestCaseScenario(): void
    {
        $safetyScore = SafetyScore::Green;
        $comfortIndex = new ComfortIndex(10);

        $metrics = new CalculatedMetrics($safetyScore, $comfortIndex);

        $this->assertSame(SafetyScore::Green, $metrics->getSafetyScore());
        $this->assertSame('Safe', $metrics->getSafetyScore()->getLabel());
        $this->assertSame('Conditions are good for swimming', $metrics->getSafetyScore()->getDescription());
        $this->assertSame(10, $metrics->getComfortIndex()->getValue());
        $this->assertSame('Excellent', $metrics->getComfortIndex()->getLabel());
    }

    public function testMixedScenarios(): void
    {
        // Good comfort but unsafe conditions
        $unsafeButComfortable = new CalculatedMetrics(SafetyScore::Red, new ComfortIndex(9));
        $this->assertSame(SafetyScore::Red, $unsafeButComfortable->getSafetyScore());
        $this->assertSame(9, $unsafeButComfortable->getComfortIndex()->getValue());

        // Safe but uncomfortable conditions
        $safeButUncomfortable = new CalculatedMetrics(SafetyScore::Green, new ComfortIndex(2));
        $this->assertSame(SafetyScore::Green, $safeButUncomfortable->getSafetyScore());
        $this->assertSame(2, $safeButUncomfortable->getComfortIndex()->getValue());

        // Caution with fair comfort
        $cautionAndFair = new CalculatedMetrics(SafetyScore::Yellow, new ComfortIndex(5));
        $this->assertSame(SafetyScore::Yellow, $cautionAndFair->getSafetyScore());
        $this->assertSame(5, $cautionAndFair->getComfortIndex()->getValue());
    }
}
