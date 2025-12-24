<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class WaveDirection
{
    private function __construct(
        private ?float $degrees,
    ) {
    }

    public static function fromDegrees(?float $value): self
    {
        return new self($value);
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    public function getDegrees(): ?float
    {
        return $this->degrees;
    }

    /**
     * Convert degrees to compass direction (N, NNO, NO, etc.).
     */
    public function getCompassDirection(): ?string
    {
        if (null === $this->degrees) {
            return null;
        }

        $directions = ['N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW'];
        $index = ((int) round($this->degrees / 22.5) % 16 + 16) % 16;

        return $directions[$index];
    }

    public function isKnown(): bool
    {
        return null !== $this->degrees;
    }
}
