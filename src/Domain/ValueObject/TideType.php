<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

enum TideType: string
{
    case High = 'high';
    case Low = 'low';

    public function getLabel(): string
    {
        return match ($this) {
            self::High => 'High tide',
            self::Low => 'Low tide',
        };
    }
}
