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

    public static function fromKnots(?float $value): self
    {
        // 1 knot = 0.514444 m/s
        return new self(null !== $value ? $value * 0.514444 : null);
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
        return null !== $this->metersPerSecond ? $this->metersPerSecond * 3.6 : null;
    }

    public function getBeaufort(): ?int
    {
        if (null === $this->metersPerSecond) {
            return null;
        }

        $ms = $this->metersPerSecond;

        return match (true) {
            $ms < 0.3 => 0,
            $ms < 1.6 => 1,
            $ms < 3.4 => 2,
            $ms < 5.5 => 3,
            $ms < 8.0 => 4,
            $ms < 10.8 => 5,
            $ms < 13.9 => 6,
            $ms < 17.2 => 7,
            $ms < 20.8 => 8,
            $ms < 24.5 => 9,
            $ms < 28.5 => 10,
            $ms < 32.7 => 11,
            default => 12,
        };
    }

    public function getBeaufortLabel(): ?string
    {
        $beaufort = $this->getBeaufort();

        if (null === $beaufort) {
            return null;
        }

        return match ($beaufort) {
            0 => 'Calm',
            1 => 'Light air',
            2 => 'Light breeze',
            3 => 'Gentle breeze',
            4 => 'Moderate breeze',
            5 => 'Fresh breeze',
            6 => 'Strong breeze',
            7 => 'Near gale',
            8 => 'Gale',
            9 => 'Strong gale',
            10 => 'Storm',
            11 => 'Violent storm',
            12 => 'Hurricane',
            default => 'Unknown',
        };
    }

    public function isKnown(): bool
    {
        return null !== $this->metersPerSecond;
    }
}
