<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Controller\Api;

use Seaswim\Application\UseCase\GetConditionsForLocation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ConditionsController extends AbstractController
{
    public function __construct(
        private readonly GetConditionsForLocation $getConditions,
    ) {
    }

    #[Route('/conditions/{location}', name: 'api_conditions', methods: ['GET'])]
    public function get(string $location): JsonResponse
    {
        $conditions = $this->getConditions->execute($location);

        if (null === $conditions) {
            return $this->json(
                ['error' => 'Location not found'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json($this->formatConditions($conditions));
    }

    private function formatConditions(array $conditions): array
    {
        $result = [
            'water' => null,
            'weather' => null,
            'tides' => null,
            'metrics' => null,
        ];

        $water = $conditions['water'];
        if (null !== $water) {
            $result['water'] = [
                'temperature' => $water->getTemperature()->getCelsius(),
                'waveHeight' => $water->getWaveHeight()->getMeters(),
                'waterHeight' => $water->getWaterHeight()->getMeters(),
                'quality' => $water->getQuality()->value,
                'qualityLabel' => $water->getQuality()->getLabel(),
                'measuredAt' => $water->getMeasuredAt()->format('c'),
            ];
        }

        $weather = $conditions['weather'];
        if (null !== $weather) {
            $result['weather'] = [
                'airTemperature' => $weather->getAirTemperature()->getCelsius(),
                'windSpeed' => $weather->getWindSpeed()->getKilometersPerHour(),
                'windDirection' => $weather->getWindDirection(),
                'uvIndex' => $weather->getUvIndex()->getValue(),
                'uvLevel' => $weather->getUvIndex()->getLevel(),
                'measuredAt' => $weather->getMeasuredAt()->format('c'),
            ];
        }

        $tides = $conditions['tides'] ?? null;
        if (null !== $tides) {
            $result['tides'] = [];

            $prevTide = $tides->getPreviousTide();
            if (null !== $prevTide) {
                $result['tides']['previous'] = [
                    'type' => $prevTide->getType()->value,
                    'typeLabel' => $prevTide->getType()->getLabel(),
                    'time' => $prevTide->getTime()->format('c'),
                    'timeFormatted' => $prevTide->getTime()->format('H:i'),
                    'heightCm' => $prevTide->getHeightCm(),
                ];
            }

            $nextTide = $tides->getNextTide();
            if (null !== $nextTide) {
                $result['tides']['next'] = [
                    'type' => $nextTide->getType()->value,
                    'typeLabel' => $nextTide->getType()->getLabel(),
                    'time' => $nextTide->getTime()->format('c'),
                    'timeFormatted' => $nextTide->getTime()->format('H:i'),
                    'heightCm' => $nextTide->getHeightCm(),
                ];
            }

            $nextHigh = $tides->getNextHighTide();
            if (null !== $nextHigh) {
                $result['tides']['nextHigh'] = [
                    'time' => $nextHigh->getTime()->format('c'),
                    'timeFormatted' => $nextHigh->getTime()->format('H:i'),
                    'heightCm' => $nextHigh->getHeightCm(),
                ];
            }

            $nextLow = $tides->getNextLowTide();
            if (null !== $nextLow) {
                $result['tides']['nextLow'] = [
                    'time' => $nextLow->getTime()->format('c'),
                    'timeFormatted' => $nextLow->getTime()->format('H:i'),
                    'heightCm' => $nextLow->getHeightCm(),
                ];
            }
        }

        $metrics = $conditions['metrics'];
        $result['metrics'] = [
            'safetyScore' => $metrics->getSafetyScore()->value,
            'safetyLabel' => $metrics->getSafetyScore()->getLabel(),
            'safetyDescription' => $metrics->getSafetyScore()->getDescription(),
            'comfortIndex' => $metrics->getComfortIndex()->getValue(),
            'comfortLabel' => $metrics->getComfortIndex()->getLabel(),
            'recommendation' => $metrics->getRecommendation()->getTypeValue(),
            'recommendationLabel' => $metrics->getRecommendation()->getLabel(),
            'recommendationExplanation' => $metrics->getRecommendation()->getExplanation(),
        ];

        return $result;
    }
}
