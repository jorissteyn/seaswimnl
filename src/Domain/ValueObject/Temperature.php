<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class Temperature
{
    private function __construct(
        private ?float $celsius,
    ) {
    }

    public static function fromCelsius(?float $value): self
    {
        return new self($value);
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    public function getCelsius(): ?float
    {
        return $this->celsius;
    }

    public function isKnown(): bool
    {
        return null !== $this->celsius;
    }
}
