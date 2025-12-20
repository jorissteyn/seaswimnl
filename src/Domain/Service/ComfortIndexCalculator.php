<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\ValueObject\ComfortIndex;

final class ComfortIndexCalculator
{
    private const float WATER_TEMP_WEIGHT = 0.4;
    private const float AIR_TEMP_WEIGHT = 0.2;
    private const float WIND_SPEED_WEIGHT = 0.2;
    private const float UV_INDEX_WEIGHT = 0.1;
    private const float WAVE_HEIGHT_WEIGHT = 0.1;

    public function calculate(?WaterConditions $water, ?WeatherConditions $weather): ComfortIndex
    {
        $scores = [];
        $weights = [];

        if ($water !== null) {
            $scores[] = $this->scoreWaterTemperature($water->getTemperature()->getCelsius());
            $weights[] = self::WATER_TEMP_WEIGHT;

            $scores[] = $this->scoreWaveHeight($water->getWaveHeight()->getMeters());
            $weights[] = self::WAVE_HEIGHT_WEIGHT;
        }

        if ($weather !== null) {
            $scores[] = $this->scoreAirTemperature($weather->getAirTemperature()->getCelsius());
            $weights[] = self::AIR_TEMP_WEIGHT;

            $scores[] = $this->scoreWindSpeed($weather->getWindSpeed()->getKilometersPerHour());
            $weights[] = self::WIND_SPEED_WEIGHT;

            $scores[] = $this->scoreUvIndex($weather->getUvIndex()->getValue());
            $weights[] = self::UV_INDEX_WEIGHT;
        }

        if (empty($scores)) {
            return new ComfortIndex(5);
        }

        $totalWeight = array_sum($weights);
        $weightedSum = 0;

        foreach ($scores as $i => $score) {
            $weightedSum += $score * ($weights[$i] / $totalWeight);
        }

        return new ComfortIndex((int) round($weightedSum));
    }

    private function scoreWaterTemperature(float $celsius): float
    {
        if ($celsius >= 18 && $celsius <= 22) {
            return 10;
        }
        if ($celsius >= 16 && $celsius <= 24) {
            return 8;
        }
        if ($celsius >= 14 && $celsius <= 26) {
            return 6;
        }
        if ($celsius >= 12 && $celsius <= 28) {
            return 4;
        }
        if ($celsius >= 10) {
            return 2;
        }

        return 1;
    }

    private function scoreAirTemperature(float $celsius): float
    {
        if ($celsius >= 20 && $celsius <= 25) {
            return 10;
        }
        if ($celsius >= 18 && $celsius <= 28) {
            return 8;
        }
        if ($celsius >= 15 && $celsius <= 30) {
            return 6;
        }
        if ($celsius >= 12 && $celsius <= 32) {
            return 4;
        }

        return 2;
    }

    private function scoreWindSpeed(float $kmh): float
    {
        if ($kmh < 10) {
            return 10;
        }
        if ($kmh < 15) {
            return 8;
        }
        if ($kmh < 25) {
            return 6;
        }
        if ($kmh < 35) {
            return 4;
        }

        return 2;
    }

    private function scoreUvIndex(int $uv): float
    {
        if ($uv >= 3 && $uv <= 5) {
            return 10;
        }
        if ($uv >= 2 && $uv <= 6) {
            return 8;
        }
        if ($uv <= 7) {
            return 6;
        }
        if ($uv <= 9) {
            return 4;
        }

        return 2;
    }

    private function scoreWaveHeight(float $meters): float
    {
        if ($meters < 0.3) {
            return 10;
        }
        if ($meters < 0.5) {
            return 8;
        }
        if ($meters < 1.0) {
            return 6;
        }
        if ($meters < 1.5) {
            return 4;
        }

        return 2;
    }
}
