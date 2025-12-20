<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class TideInfo
{
    /**
     * @param TideEvent[] $events Tide events sorted by time
     */
    public function __construct(
        private array $events,
        private \DateTimeImmutable $referenceTime,
    ) {
    }

    /**
     * @return TideEvent[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getPreviousHighTide(): ?TideEvent
    {
        return $this->findPreviousEvent(TideType::High);
    }

    public function getPreviousLowTide(): ?TideEvent
    {
        return $this->findPreviousEvent(TideType::Low);
    }

    public function getNextHighTide(): ?TideEvent
    {
        return $this->findNextEvent(TideType::High);
    }

    public function getNextLowTide(): ?TideEvent
    {
        return $this->findNextEvent(TideType::Low);
    }

    public function getNextTide(): ?TideEvent
    {
        foreach ($this->events as $event) {
            if ($event->getTime() > $this->referenceTime) {
                return $event;
            }
        }

        return null;
    }

    public function getPreviousTide(): ?TideEvent
    {
        $previous = null;
        foreach ($this->events as $event) {
            if ($event->getTime() >= $this->referenceTime) {
                break;
            }
            $previous = $event;
        }

        return $previous;
    }

    private function findPreviousEvent(TideType $type): ?TideEvent
    {
        $previous = null;
        foreach ($this->events as $event) {
            if ($event->getTime() >= $this->referenceTime) {
                break;
            }
            if ($event->getType() === $type) {
                $previous = $event;
            }
        }

        return $previous;
    }

    private function findNextEvent(TideType $type): ?TideEvent
    {
        foreach ($this->events as $event) {
            if ($event->getTime() > $this->referenceTime && $event->getType() === $type) {
                return $event;
            }
        }

        return null;
    }
}
