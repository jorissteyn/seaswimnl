<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class WavePeriod
{
    private function __construct(
        private ?float $seconds,
    ) {
    }

    public static function fromSeconds(?float $value): self
    {
        return new self($value);
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    public function getSeconds(): ?float
    {
        return $this->seconds;
    }

    public function isKnown(): bool
    {
        return null !== $this->seconds;
    }
}
