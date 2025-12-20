<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class TideEvent
{
    public function __construct(
        private TideType $type,
        private \DateTimeImmutable $time,
        private float $heightCm,
    ) {
    }

    public function getType(): TideType
    {
        return $this->type;
    }

    public function getTime(): \DateTimeImmutable
    {
        return $this->time;
    }

    public function getHeightCm(): float
    {
        return $this->heightCm;
    }

    public function getHeightMeters(): float
    {
        return $this->heightCm / 100.0;
    }

    public function isHighTide(): bool
    {
        return TideType::High === $this->type;
    }

    public function isLowTide(): bool
    {
        return TideType::Low === $this->type;
    }
}
