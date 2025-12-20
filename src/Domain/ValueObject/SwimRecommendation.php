<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

enum SwimRecommendationType: string
{
    case Now = 'now';
    case LaterToday = 'later_today';
    case Tomorrow = 'tomorrow';
    case NotRecommended = 'not_recommended';
}

final readonly class SwimRecommendation
{
    public function __construct(
        private SwimRecommendationType $type,
        private string $explanation,
    ) {
    }

    public function getType(): SwimRecommendationType
    {
        return $this->type;
    }

    public function getTypeValue(): string
    {
        return $this->type->value;
    }

    public function getLabel(): string
    {
        return match ($this->type) {
            SwimRecommendationType::Now => 'Go now!',
            SwimRecommendationType::LaterToday => 'Wait for later',
            SwimRecommendationType::Tomorrow => 'Try tomorrow',
            SwimRecommendationType::NotRecommended => 'Not recommended',
        };
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }
}
