<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class WindSpeed
{
    private function __construct(
        private ?float $metersPerSecond,
    ) {
    }

    public static function fromMetersPerSecond(?float $value): self
    {
        return new self($value);
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    public function getMetersPerSecond(): ?float
    {
        return $this->metersPerSecond;
    }

    public function getKilometersPerHour(): ?float
    {
        return $this->metersPerSecond !== null ? $this->metersPerSecond * 3.6 : null;
    }

    public function isKnown(): bool
    {
        return $this->metersPerSecond !== null;
    }
}
