<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

enum WaterQuality: string
{
    case Good = 'good';
    case Moderate = 'moderate';
    case Poor = 'poor';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Moderate => 'Moderate',
            self::Poor => 'Poor',
            self::Unknown => 'Unknown',
        };
    }
}
