<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Domain\ValueObject\ComfortIndex;
use Seaswim\Domain\ValueObject\SafetyScore;
use Seaswim\Domain\ValueObject\SwimRecommendation;
use Seaswim\Domain\ValueObject\SwimRecommendationType;

final class SwimTimeRecommender
{
    public function recommend(SafetyScore $safety, ComfortIndex $comfort): SwimRecommendation
    {
        if (SafetyScore::Red === $safety) {
            return new SwimRecommendation(
                SwimRecommendationType::NotRecommended,
                'Current conditions are unsafe for swimming. Check back later when conditions improve.',
            );
        }

        if (SafetyScore::Yellow === $safety) {
            if ($comfort->getValue() >= 6) {
                return new SwimRecommendation(
                    SwimRecommendationType::Now,
                    'Conditions are acceptable but swim with caution. Be aware of changing conditions.',
                );
            }

            return new SwimRecommendation(
                SwimRecommendationType::LaterToday,
                'Conditions may improve later. Check back in a few hours.',
            );
        }

        if ($comfort->getValue() >= 7) {
            return new SwimRecommendation(
                SwimRecommendationType::Now,
                'Excellent conditions for swimming! Enjoy your swim.',
            );
        }

        if ($comfort->getValue() >= 5) {
            return new SwimRecommendation(
                SwimRecommendationType::Now,
                'Good conditions for swimming. Have a nice swim!',
            );
        }

        return new SwimRecommendation(
            SwimRecommendationType::LaterToday,
            'Conditions are safe but comfort is low. Consider waiting for better conditions.',
        );
    }
}
