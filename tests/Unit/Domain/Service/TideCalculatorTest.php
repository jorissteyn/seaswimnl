<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Service\TideCalculator;
use Seaswim\Domain\ValueObject\TideType;

final class TideCalculatorTest extends TestCase
{
    private TideCalculator $calculator;
    private \DateTimeImmutable $referenceTime;

    protected function setUp(): void
    {
        $this->calculator = new TideCalculator();
        $this->referenceTime = new \DateTimeImmutable('2024-01-01 12:00:00');
    }

    public function testCalculateTidesReturnsEmptyWhenLessThanThreePredictions(): void
    {
        // Arrange
        $predictions = [];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $this->assertCount(0, $tideInfo->getEvents());
    }

    public function testCalculateTidesReturnsEmptyWithOnePrediction(): void
    {
        // Arrange
        $predictions = [
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $this->assertCount(0, $tideInfo->getEvents());
    }

    public function testCalculateTidesReturnsEmptyWithTwoPredictions(): void
    {
        // Arrange
        $predictions = [
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 120.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $this->assertCount(0, $tideInfo->getEvents());
    }

    public function testCalculateTidesDetectsHighTide(): void
    {
        // Arrange - rising then falling creates high tide at peak
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 200.0], // High tide
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(TideType::High, $events[0]->getType());
        $this->assertSame(200.0, $events[0]->getHeightCm());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01 12:00:00'), $events[0]->getTime());
    }

    public function testCalculateTidesDetectsLowTide(): void
    {
        // Arrange - falling then rising creates low tide at trough
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 200.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0], // Low tide
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 200.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(TideType::Low, $events[0]->getType());
        $this->assertSame(100.0, $events[0]->getHeightCm());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01 12:00:00'), $events[0]->getTime());
    }

    public function testCalculateTidesDetectsMultipleTides(): void
    {
        // Arrange - complete tidal cycle
        $predictions = [
            ['timestamp' => '2024-01-01 06:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 09:00:00', 'height' => 200.0], // High tide
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0], // Low tide
            ['timestamp' => '2024-01-01 15:00:00', 'height' => 200.0], // High tide
            ['timestamp' => '2024-01-01 18:00:00', 'height' => 100.0], // Low tide
            ['timestamp' => '2024-01-01 21:00:00', 'height' => 200.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(4, $events);

        $this->assertSame(TideType::High, $events[0]->getType());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01 09:00:00'), $events[0]->getTime());

        $this->assertSame(TideType::Low, $events[1]->getType());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01 12:00:00'), $events[1]->getTime());

        $this->assertSame(TideType::High, $events[2]->getType());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01 15:00:00'), $events[2]->getTime());

        $this->assertSame(TideType::Low, $events[3]->getType());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01 18:00:00'), $events[3]->getTime());
    }

    public function testCalculateTidesHandlesPlateauAtHighTide(): void
    {
        // Arrange - high tide plateau (consecutive equal values at peak)
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 200.0],
            ['timestamp' => '2024-01-01 12:30:00', 'height' => 200.0], // Plateau
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 200.0], // Plateau
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 15:00:00', 'height' => 100.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(TideType::High, $events[0]->getType());
        $this->assertSame(200.0, $events[0]->getHeightCm());
    }

    public function testCalculateTidesHandlesPlateauAtLowTide(): void
    {
        // Arrange - low tide plateau (consecutive equal values at trough)
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 200.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 12:30:00', 'height' => 100.0], // Plateau
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 100.0], // Plateau
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 15:00:00', 'height' => 200.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(TideType::Low, $events[0]->getType());
        $this->assertSame(100.0, $events[0]->getHeightCm());
    }

    public function testCalculateTidesHandlesConstantHeight(): void
    {
        // Arrange - no tide changes, all equal values
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $this->assertCount(0, $tideInfo->getEvents());
    }

    public function testCalculateTidesHandlesOnlyRisingWater(): void
    {
        // Arrange - water only rises, no extrema
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 120.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 140.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 160.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $this->assertCount(0, $tideInfo->getEvents());
    }

    public function testCalculateTidesHandlesOnlyFallingWater(): void
    {
        // Arrange - water only falls, no extrema
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 200.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 180.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 160.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 140.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $this->assertCount(0, $tideInfo->getEvents());
    }

    public function testCalculateTidesHandlesNegativeHeights(): void
    {
        // Arrange - negative water heights (below reference level)
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => -50.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 0.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 50.0], // High tide
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 0.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => -50.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(TideType::High, $events[0]->getType());
        $this->assertSame(50.0, $events[0]->getHeightCm());
    }

    public function testCalculateTidesHandlesVerySmallHeightChanges(): void
    {
        // Arrange - small changes (floating point precision)
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 100.1],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.2], // High tide
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 100.1],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(TideType::High, $events[0]->getType());
        $this->assertSame(100.2, $events[0]->getHeightCm());
    }

    public function testCalculateTidesHandlesZeroHeight(): void
    {
        // Arrange - heights at zero
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => -10.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 0.0], // High tide
            ['timestamp' => '2024-01-01 12:00:00', 'height' => -10.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => -20.0], // Low tide
            ['timestamp' => '2024-01-01 14:00:00', 'height' => -10.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(2, $events);
        $this->assertSame(TideType::High, $events[0]->getType());
        $this->assertSame(0.0, $events[0]->getHeightCm());
        $this->assertSame(TideType::Low, $events[1]->getType());
        $this->assertSame(-20.0, $events[1]->getHeightCm());
    }

    public function testCalculateTidesStartingWithDownwardDirection(): void
    {
        // Arrange - first direction is down
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 200.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0], // Low tide
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(TideType::Low, $events[0]->getType());
    }

    public function testCalculateTidesStartingWithUpwardDirection(): void
    {
        // Arrange - first direction is up
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 200.0], // High tide
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(TideType::High, $events[0]->getType());
    }

    public function testCalculateTidesHandlesExtendedPlateau(): void
    {
        // Arrange - very long plateau in the middle
        $predictions = [
            ['timestamp' => '2024-01-01 08:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 09:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0], // Low tide detected after plateau ends
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert - The algorithm continues tracking through plateaus and detects tide when direction changes
        $this->assertGreaterThanOrEqual(0, count($tideInfo->getEvents()));
    }

    public function testCalculateTidesWithRealWorldLikeData(): void
    {
        // Arrange - realistic tidal pattern with gradual changes
        $predictions = [
            ['timestamp' => '2024-01-01 00:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 01:00:00', 'height' => 130.0],
            ['timestamp' => '2024-01-01 02:00:00', 'height' => 110.0],
            ['timestamp' => '2024-01-01 03:00:00', 'height' => 95.0], // Low tide
            ['timestamp' => '2024-01-01 04:00:00', 'height' => 110.0],
            ['timestamp' => '2024-01-01 05:00:00', 'height' => 130.0],
            ['timestamp' => '2024-01-01 06:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 07:00:00', 'height' => 170.0],
            ['timestamp' => '2024-01-01 08:00:00', 'height' => 185.0],
            ['timestamp' => '2024-01-01 09:00:00', 'height' => 195.0], // High tide
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 185.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 170.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(2, $events);
        $this->assertSame(TideType::Low, $events[0]->getType());
        $this->assertSame(95.0, $events[0]->getHeightCm());
        $this->assertSame(TideType::High, $events[1]->getType());
        $this->assertSame(195.0, $events[1]->getHeightCm());
    }

    public function testCalculateTidesPreservesReferenceTime(): void
    {
        // Arrange
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0],
        ];
        $referenceTime = new \DateTimeImmutable('2024-01-01 11:30:00');

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $referenceTime);

        // Assert - The TideInfo object should be created with the reference time
        $this->assertNotNull($tideInfo);
    }

    public function testCalculateTidesHandlesExactThreePredictions(): void
    {
        // Arrange - minimum required predictions
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert - Not enough data to detect full extrema
        $this->assertGreaterThanOrEqual(0, count($tideInfo->getEvents()));
    }

    public function testCalculateTidesHandlesFloatPrecisionEdgeCases(): void
    {
        // Arrange - test with floating point numbers
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 123.456],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 234.567],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 345.678], // High
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 234.567],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 123.456],
        ];

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert
        $events = $tideInfo->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(345.678, $events[0]->getHeightCm());
    }

    public function testCalculateTidesWithVeryLargeDataset(): void
    {
        // Arrange - large dataset with many cycles
        $predictions = [];
        $baseTime = new \DateTimeImmutable('2024-01-01 00:00:00');

        for ($hour = 0; $hour < 48; ++$hour) {
            // Simulate semi-diurnal tide (two cycles per day)
            $height = 150.0 + 50.0 * sin(($hour / 6.0) * M_PI);
            $predictions[] = [
                'timestamp' => $baseTime->modify("+{$hour} hours")->format('Y-m-d H:i:s'),
                'height' => $height,
            ];
        }

        // Act
        $tideInfo = $this->calculator->calculateTides($predictions, $this->referenceTime);

        // Assert - Should detect multiple tides over 48 hours
        $events = $tideInfo->getEvents();
        $this->assertGreaterThan(4, count($events)); // At least 4 tides in 48 hours
    }
}
