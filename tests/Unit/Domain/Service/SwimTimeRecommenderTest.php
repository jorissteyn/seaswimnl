<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Service\SwimTimeRecommender;
use Seaswim\Domain\ValueObject\ComfortIndex;
use Seaswim\Domain\ValueObject\SafetyScore;
use Seaswim\Domain\ValueObject\SwimRecommendationType;

final class SwimTimeRecommenderTest extends TestCase
{
    private SwimTimeRecommender $recommender;

    protected function setUp(): void
    {
        $this->recommender = new SwimTimeRecommender();
    }

    public function testNotRecommendedWithRedSafety(): void
    {
        $recommendation = $this->recommender->recommend(
            SafetyScore::Red,
            new ComfortIndex(8),
        );

        $this->assertSame(SwimRecommendationType::NotRecommended, $recommendation->getType());
        $this->assertStringContainsString('unsafe', strtolower($recommendation->getExplanation()));
    }

    public function testNowWithYellowSafetyAndHighComfort(): void
    {
        $recommendation = $this->recommender->recommend(
            SafetyScore::Yellow,
            new ComfortIndex(7),
        );

        $this->assertSame(SwimRecommendationType::Now, $recommendation->getType());
        $this->assertStringContainsString('caution', strtolower($recommendation->getExplanation()));
    }

    public function testLaterTodayWithYellowSafetyAndLowComfort(): void
    {
        $recommendation = $this->recommender->recommend(
            SafetyScore::Yellow,
            new ComfortIndex(4),
        );

        $this->assertSame(SwimRecommendationType::LaterToday, $recommendation->getType());
    }

    public function testNowWithGreenSafetyAndHighComfort(): void
    {
        $recommendation = $this->recommender->recommend(
            SafetyScore::Green,
            new ComfortIndex(8),
        );

        $this->assertSame(SwimRecommendationType::Now, $recommendation->getType());
        $this->assertStringContainsString('excellent', strtolower($recommendation->getExplanation()));
    }

    public function testNowWithGreenSafetyAndMediumComfort(): void
    {
        $recommendation = $this->recommender->recommend(
            SafetyScore::Green,
            new ComfortIndex(6),
        );

        $this->assertSame(SwimRecommendationType::Now, $recommendation->getType());
        $this->assertStringContainsString('good', strtolower($recommendation->getExplanation()));
    }

    public function testLaterTodayWithGreenSafetyAndLowComfort(): void
    {
        $recommendation = $this->recommender->recommend(
            SafetyScore::Green,
            new ComfortIndex(3),
        );

        $this->assertSame(SwimRecommendationType::LaterToday, $recommendation->getType());
    }

    public function testRecommendationAlwaysHasExplanation(): void
    {
        $scenarios = [
            [SafetyScore::Green, new ComfortIndex(10)],
            [SafetyScore::Green, new ComfortIndex(5)],
            [SafetyScore::Green, new ComfortIndex(1)],
            [SafetyScore::Yellow, new ComfortIndex(8)],
            [SafetyScore::Yellow, new ComfortIndex(3)],
            [SafetyScore::Red, new ComfortIndex(10)],
        ];

        foreach ($scenarios as [$safety, $comfort]) {
            $recommendation = $this->recommender->recommend($safety, $comfort);

            $this->assertNotEmpty($recommendation->getExplanation());
            $this->assertNotEmpty($recommendation->getLabel());
        }
    }
}
