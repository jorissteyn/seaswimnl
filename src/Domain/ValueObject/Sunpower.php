<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

/**
 * Solar radiation intensity in W/m² (watts per square meter).
 */
final readonly class Sunpower
{
    private function __construct(
        private ?float $value,
    ) {
    }

    public static function fromWattsPerSquareMeter(?float $value): self
    {
        return new self($value);
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function isKnown(): bool
    {
        return null !== $this->value;
    }

    /**
     * Get a human-readable level description.
     */
    public function getLevel(): string
    {
        if (null === $this->value) {
            return 'Unknown';
        }

        return match (true) {
            $this->value < 1 => 'None',
            $this->value < 200 => 'Low',
            $this->value < 400 => 'Moderate',
            $this->value < 700 => 'Good',
            default => 'Strong',
        };
    }
}
