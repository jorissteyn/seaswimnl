<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class UVIndex
{
    private function __construct(
        private ?int $value,
    ) {
    }

    public static function fromValue(?int $value): self
    {
        return new self($value);
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function isKnown(): bool
    {
        return null !== $this->value;
    }

    public function getLevel(): string
    {
        if (null === $this->value) {
            return 'Unknown';
        }

        return match (true) {
            $this->value <= 2 => 'Low',
            $this->value <= 5 => 'Moderate',
            $this->value <= 7 => 'High',
            $this->value <= 10 => 'Very High',
            default => 'Extreme',
        };
    }
}
