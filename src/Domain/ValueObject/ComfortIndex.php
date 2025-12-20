<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class ComfortIndex
{
    private int $value;

    public function __construct(int $value)
    {
        $this->value = max(1, min(10, $value));
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match (true) {
            $this->value >= 8 => 'Excellent',
            $this->value >= 6 => 'Good',
            $this->value >= 4 => 'Fair',
            $this->value >= 2 => 'Poor',
            default => 'Very Poor',
        };
    }
}
