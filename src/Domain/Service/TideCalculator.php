<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Domain\ValueObject\TideEvent;
use Seaswim\Domain\ValueObject\TideInfo;
use Seaswim\Domain\ValueObject\TideType;

/**
 * Calculates tide events (high/low) from water height predictions.
 */
final class TideCalculator
{
    /**
     * Find tide events from water height predictions.
     *
     * @param array<int, array{timestamp: string, height: float}> $predictions Water heights in cm
     */
    public function calculateTides(array $predictions, \DateTimeImmutable $referenceTime): TideInfo
    {
        if (\count($predictions) < 3) {
            return new TideInfo([], $referenceTime);
        }

        $events = [];
        $count = \count($predictions);

        // Track direction changes to find extrema
        // This handles plateaus where consecutive values are equal
        $lastDirection = null; // 'up', 'down', or null
        $extremeIndex = 0;
        $extremeValue = $predictions[0]['height'];

        for ($i = 1; $i < $count; ++$i) {
            $curr = $predictions[$i]['height'];
            $prev = $predictions[$i - 1]['height'];

            if ($curr > $prev) {
                // Going up
                if ('down' === $lastDirection) {
                    // Direction changed from down to up = we passed a low tide
                    $events[] = new TideEvent(
                        TideType::Low,
                        new \DateTimeImmutable($predictions[$extremeIndex]['timestamp']),
                        $extremeValue,
                    );
                }
                $lastDirection = 'up';
                $extremeIndex = $i;
                $extremeValue = $curr;
            } elseif ($curr < $prev) {
                // Going down
                if ('up' === $lastDirection) {
                    // Direction changed from up to down = we passed a high tide
                    $events[] = new TideEvent(
                        TideType::High,
                        new \DateTimeImmutable($predictions[$extremeIndex]['timestamp']),
                        $extremeValue,
                    );
                }
                $lastDirection = 'down';
                $extremeIndex = $i;
                $extremeValue = $curr;
            }
            // If curr == prev, we're on a plateau - keep tracking but don't change direction
        }

        return new TideInfo($events, $referenceTime);
    }
}
