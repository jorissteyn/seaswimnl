<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\ValueObject\SafetyScore;

final class SafetyScoreCalculator
{
    private const float RED_WATER_TEMP = 10.0;
    private const float YELLOW_WATER_TEMP = 15.0;
    private const float RED_WAVE_HEIGHT = 2.0;
    private const float YELLOW_WAVE_HEIGHT = 1.0;
    private const float RED_WIND_SPEED = 40.0;
    private const float YELLOW_WIND_SPEED = 20.0;

    public function calculate(?WaterConditions $water, ?WeatherConditions $weather): SafetyScore
    {
        $hasRed = false;
        $hasYellow = false;

        if (null !== $water) {
            $waterTemp = $water->getTemperature()->getCelsius();
            if ($waterTemp < self::RED_WATER_TEMP) {
                $hasRed = true;
            } elseif ($waterTemp < self::YELLOW_WATER_TEMP) {
                $hasYellow = true;
            }

            $waveHeight = $water->getWaveHeight()->getMeters();
            if ($waveHeight > self::RED_WAVE_HEIGHT) {
                $hasRed = true;
            } elseif ($waveHeight > self::YELLOW_WAVE_HEIGHT) {
                $hasYellow = true;
            }
        } else {
            $hasYellow = true;
        }

        if (null !== $weather) {
            $windSpeed = $weather->getWindSpeed()->getKilometersPerHour();
            if ($windSpeed > self::RED_WIND_SPEED) {
                $hasRed = true;
            } elseif ($windSpeed > self::YELLOW_WIND_SPEED) {
                $hasYellow = true;
            }
        }

        if ($hasRed) {
            return SafetyScore::Red;
        }

        if ($hasYellow) {
            return SafetyScore::Yellow;
        }

        return SafetyScore::Green;
    }
}
