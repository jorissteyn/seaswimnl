<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\TideEvent;
use Seaswim\Domain\ValueObject\TideInfo;
use Seaswim\Domain\ValueObject\TideType;

final class TideInfoTest extends TestCase
{
    public function testConstructionAndGetEvents(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 06:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 12:30:00'), 50.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);

        $this->assertSame($events, $tideInfo->getEvents());
        $this->assertCount(2, $tideInfo->getEvents());
    }

    public function testConstructionWithEmptyEvents(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $tideInfo = new TideInfo([], $referenceTime);

        $this->assertSame([], $tideInfo->getEvents());
        $this->assertCount(0, $tideInfo->getEvents());
    }

    public function testGetPreviousHighTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 06:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 11:30:00'), 460.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 15:00:00'), 55.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousHigh = $tideInfo->getPreviousHighTide();

        $this->assertNotNull($previousHigh);
        $this->assertSame(TideType::High, $previousHigh->getType());
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 11:30:00'), $previousHigh->getTime());
        $this->assertSame(460.0, $previousHigh->getHeightCm());
    }

    public function testGetPreviousHighTideReturnsNullWhenNoPreviousHighTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 450.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousHigh = $tideInfo->getPreviousHighTide();

        $this->assertNull($previousHigh);
    }

    public function testGetPreviousHighTideWithEventAtExactReferenceTime(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 06:00:00'), 450.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 12:00:00'), 460.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 15:00:00'), 50.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousHigh = $tideInfo->getPreviousHighTide();

        // Event at exact reference time should not be included as "previous"
        $this->assertNotNull($previousHigh);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 06:00:00'), $previousHigh->getTime());
        $this->assertSame(450.0, $previousHigh->getHeightCm());
    }

    public function testGetPreviousLowTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 06:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 09:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 11:30:00'), 55.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 460.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousLow = $tideInfo->getPreviousLowTide();

        $this->assertNotNull($previousLow);
        $this->assertSame(TideType::Low, $previousLow->getType());
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 11:30:00'), $previousLow->getTime());
        $this->assertSame(55.0, $previousLow->getHeightCm());
    }

    public function testGetPreviousLowTideReturnsNullWhenNoPreviousLowTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 09:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 15:00:00'), 50.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousLow = $tideInfo->getPreviousLowTide();

        $this->assertNull($previousLow);
    }

    public function testGetNextHighTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 06:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 460.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 18:00:00'), 55.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 21:00:00'), 470.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextHigh = $tideInfo->getNextHighTide();

        $this->assertNotNull($nextHigh);
        $this->assertSame(TideType::High, $nextHigh->getType());
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 15:00:00'), $nextHigh->getTime());
        $this->assertSame(460.0, $nextHigh->getHeightCm());
    }

    public function testGetNextHighTideReturnsNullWhenNoNextHighTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 09:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 15:00:00'), 50.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextHigh = $tideInfo->getNextHighTide();

        $this->assertNull($nextHigh);
    }

    public function testGetNextHighTideWithEventAtExactReferenceTime(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 12:00:00'), 450.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 460.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextHigh = $tideInfo->getNextHighTide();

        // Event at exact reference time should not be included as "next"
        $this->assertNotNull($nextHigh);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 15:00:00'), $nextHigh->getTime());
        $this->assertSame(460.0, $nextHigh->getHeightCm());
    }

    public function testGetNextLowTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 06:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 09:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 15:00:00'), 55.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 18:00:00'), 460.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 21:00:00'), 60.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextLow = $tideInfo->getNextLowTide();

        $this->assertNotNull($nextLow);
        $this->assertSame(TideType::Low, $nextLow->getType());
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 15:00:00'), $nextLow->getTime());
        $this->assertSame(55.0, $nextLow->getHeightCm());
    }

    public function testGetNextLowTideReturnsNullWhenNoNextLowTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 450.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextLow = $tideInfo->getNextLowTide();

        $this->assertNull($nextLow);
    }

    public function testGetNextTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 06:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 460.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 18:00:00'), 55.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextTide = $tideInfo->getNextTide();

        $this->assertNotNull($nextTide);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 15:00:00'), $nextTide->getTime());
        $this->assertSame(TideType::High, $nextTide->getType());
    }

    public function testGetNextTideReturnsNullWhenNoFutureEvents(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 06:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 50.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextTide = $tideInfo->getNextTide();

        $this->assertNull($nextTide);
    }

    public function testGetNextTideWithEventAtExactReferenceTime(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 12:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 15:00:00'), 50.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextTide = $tideInfo->getNextTide();

        // Event at exact reference time should not be included as "next"
        $this->assertNotNull($nextTide);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 15:00:00'), $nextTide->getTime());
    }

    public function testGetPreviousTide(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 06:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 460.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousTide = $tideInfo->getPreviousTide();

        $this->assertNotNull($previousTide);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 09:00:00'), $previousTide->getTime());
        $this->assertSame(TideType::Low, $previousTide->getType());
    }

    public function testGetPreviousTideReturnsNullWhenNoPastEvents(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 18:00:00'), 50.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousTide = $tideInfo->getPreviousTide();

        $this->assertNull($previousTide);
    }

    public function testGetPreviousTideWithEventAtExactReferenceTime(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 12:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 15:00:00'), 55.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousTide = $tideInfo->getPreviousTide();

        // Event at exact reference time should not be included as "previous"
        $this->assertNotNull($previousTide);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 09:00:00'), $previousTide->getTime());
        $this->assertSame(TideType::Low, $previousTide->getType());
    }

    public function testGetPreviousTideReturnsLastBeforeReferenceTime(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 03:00:00'), 450.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 06:00:00'), 50.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 09:00:00'), 460.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 11:30:00'), 55.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 470.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousTide = $tideInfo->getPreviousTide();

        // Should return the last event before reference time
        $this->assertNotNull($previousTide);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 11:30:00'), $previousTide->getTime());
        $this->assertSame(TideType::Low, $previousTide->getType());
        $this->assertSame(55.0, $previousTide->getHeightCm());
    }

    public function testWithEmptyEventsAllMethodsReturnNull(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $tideInfo = new TideInfo([], $referenceTime);

        $this->assertNull($tideInfo->getPreviousHighTide());
        $this->assertNull($tideInfo->getPreviousLowTide());
        $this->assertNull($tideInfo->getNextHighTide());
        $this->assertNull($tideInfo->getNextLowTide());
        $this->assertNull($tideInfo->getNextTide());
        $this->assertNull($tideInfo->getPreviousTide());
    }

    public function testMultipleTidesOfSameTypeReturnsMostRecentPrevious(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 03:00:00'), 440.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 06:00:00'), 450.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 09:00:00'), 460.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2025-01-15 15:00:00'), 470.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $previousHigh = $tideInfo->getPreviousHighTide();

        $this->assertNotNull($previousHigh);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 09:00:00'), $previousHigh->getTime());
        $this->assertSame(460.0, $previousHigh->getHeightCm());
    }

    public function testMultipleTidesOfSameTypeReturnsFirstNext(): void
    {
        $referenceTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $events = [
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 09:00:00'), 40.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 15:00:00'), 50.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 18:00:00'), 55.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2025-01-15 21:00:00'), 60.0),
        ];

        $tideInfo = new TideInfo($events, $referenceTime);
        $nextLow = $tideInfo->getNextLowTide();

        $this->assertNotNull($nextLow);
        $this->assertEquals(new \DateTimeImmutable('2025-01-15 15:00:00'), $nextLow->getTime());
        $this->assertSame(50.0, $nextLow->getHeightCm());
    }
}
