<?php

declare(strict_types=1);

namespace Seaswim\Domain\Entity;

use Seaswim\Domain\ValueObject\ComfortIndex;
use Seaswim\Domain\ValueObject\SafetyScore;

final readonly class CalculatedMetrics
{
    public function __construct(
        private SafetyScore $safetyScore,
        private ComfortIndex $comfortIndex,
    ) {
    }

    public function getSafetyScore(): SafetyScore
    {
        return $this->safetyScore;
    }

    public function getComfortIndex(): ComfortIndex
    {
        return $this->comfortIndex;
    }
}
