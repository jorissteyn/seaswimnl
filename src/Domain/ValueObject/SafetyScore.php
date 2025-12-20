<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

enum SafetyScore: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public function getLabel(): string
    {
        return match ($this) {
            self::Green => 'Safe',
            self::Yellow => 'Caution',
            self::Red => 'Unsafe',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Green => 'Conditions are good for swimming',
            self::Yellow => 'Swim with caution - some conditions are suboptimal',
            self::Red => 'Swimming not recommended due to unsafe conditions',
        };
    }
}
