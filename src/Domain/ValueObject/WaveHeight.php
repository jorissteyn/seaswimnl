<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class WaveHeight
{
    private function __construct(
        private ?float $meters,
    ) {
    }

    public static function fromMeters(?float $value): self
    {
        return new self($value);
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    public function getMeters(): ?float
    {
        return $this->meters;
    }

    public function isKnown(): bool
    {
        return null !== $this->meters;
    }
}
