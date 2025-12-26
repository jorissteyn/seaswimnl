<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Controller\Api;

use Seaswim\Application\UseCase\GetConditionsForSwimmingSpot;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ConditionsController extends AbstractController
{
    public function __construct(
        private readonly GetConditionsForSwimmingSpot $getConditions,
    ) {
    }

    #[Route('/conditions/{swimmingSpot}', name: 'api_conditions', methods: ['GET'])]
    public function get(string $swimmingSpot): JsonResponse
    {
        $conditions = $this->getConditions->execute($swimmingSpot);

        if (null === $conditions) {
            return $this->json(
                ['error' => 'Swimming spot not found'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json($this->formatConditions($conditions));
    }

    private function formatConditions(array $conditions): array
    {
        $swimmingSpot = $conditions['swimmingSpot'];
        $rwsLocationResult = $conditions['rwsLocation'];
        $weatherStationResult = $conditions['weatherStation'];

        $result = [
            'swimmingSpot' => [
                'id' => $swimmingSpot->getId(),
                'name' => $swimmingSpot->getName(),
                'latitude' => $swimmingSpot->getLatitude(),
                'longitude' => $swimmingSpot->getLongitude(),
            ],
            'rwsLocation' => null !== $rwsLocationResult ? [
                'id' => $rwsLocationResult['location']->getId(),
                'name' => $rwsLocationResult['location']->getName(),
                'distanceKm' => $rwsLocationResult['distanceKm'],
            ] : null,
            'weatherStation' => null !== $weatherStationResult ? [
                'code' => $weatherStationResult['station']->getCode(),
                'name' => $weatherStationResult['station']->getName(),
                'distanceKm' => $weatherStationResult['distanceKm'],
            ] : null,
            'water' => null,
            'weather' => null,
            'tides' => null,
            'metrics' => null,
        ];

        $water = $conditions['water'];
        $waveHeightStation = $conditions['waveHeightStation'] ?? null;
        $wavePeriodStation = $conditions['wavePeriodStation'] ?? null;
        $waveDirectionStation = $conditions['waveDirectionStation'] ?? null;

        if (null !== $water) {
            $rawMeasurements = $water->getRawMeasurements();
            $result['water'] = [
                'location' => [
                    'id' => $water->getLocation()->getId(),
                    'name' => $water->getLocation()->getName(),
                ],
                'temperature' => $water->getTemperature()->getCelsius(),
                'temperatureRaw' => $rawMeasurements['waterTemperature'] ?? null,
                'waveHeight' => (null !== $waveHeightStation ? $waveHeightStation['waveHeight'] : null) ?? $water->getWaveHeight()->getMeters(),
                'waveHeightRaw' => $rawMeasurements['waveHeight'] ?? null,
                'waveHeightBuoy' => $waveHeightStation,
                'wavePeriod' => (null !== $wavePeriodStation ? $wavePeriodStation['wavePeriod'] : null) ?? $water->getWavePeriod()?->getSeconds(),
                'wavePeriodRaw' => $rawMeasurements['wavePeriod'] ?? null,
                'wavePeriodStation' => $wavePeriodStation,
                'waveDirection' => (null !== $waveDirectionStation ? $waveDirectionStation['waveDirection'] : null) ?? $water->getWaveDirection()?->getDegrees(),
                'waveDirectionCompass' => (null !== $waveDirectionStation ? $waveDirectionStation['waveDirectionCompass'] : null) ?? $water->getWaveDirection()?->getCompassDirection(),
                'waveDirectionRaw' => $rawMeasurements['waveDirection'] ?? null,
                'waveDirectionStation' => $waveDirectionStation,
                'waterHeight' => $water->getWaterHeight()->getMeters(),
                'waterHeightRaw' => $rawMeasurements['waterHeight'] ?? null,
                'windSpeed' => $water->getWindSpeed()?->getKilometersPerHour(),
                'windSpeedRaw' => $rawMeasurements['windSpeed'] ?? null,
                'windDirection' => $water->getWindDirection(),
                'windDirectionRaw' => $rawMeasurements['windDirection'] ?? null,
                'measuredAt' => $water->getMeasuredAt()->format('c'),
            ];
        }

        $weather = $conditions['weather'];
        if (null !== $weather) {
            $station = $weather->getStation();
            $weatherRaw = $weather->getRawMeasurements();
            $result['weather'] = [
                'station' => null !== $station ? [
                    'code' => $station->getCode(),
                    'name' => $station->getName(),
                    'distanceKm' => $weather->getStationDistanceKm(),
                ] : null,
                'airTemperature' => $weather->getAirTemperature()->getCelsius(),
                'airTemperatureRaw' => $weatherRaw['temperature'] ?? null,
                'windSpeed' => $weather->getWindSpeed()->getKilometersPerHour(),
                'windSpeedRaw' => $weatherRaw['windSpeed'] ?? null,
                'windDirection' => $weather->getWindDirection(),
                'windDirectionRaw' => $weatherRaw['windDirection'] ?? null,
                'sunpower' => $weather->getSunpower()->getValue(),
                'sunpowerRaw' => $weatherRaw['sunpower'] ?? null,
                'sunpowerLevel' => $weather->getSunpower()->getLevel(),
                'measuredAt' => $weather->getMeasuredAt()->format('c'),
            ];
        }

        $tides = $conditions['tides'] ?? null;
        $tidalStation = $conditions['tidalStation'] ?? null;
        if (null !== $tides) {
            // If tidal data comes from a nearby station, use that station's info
            // Otherwise use the water location (which is the selected location)
            $tidalLocation = null !== $tidalStation
                ? ['id' => $tidalStation['id'], 'name' => $tidalStation['name']]
                : (null !== $water ? ['id' => $water->getLocation()->getId(), 'name' => $water->getLocation()->getName()] : null);

            $result['tides'] = [
                'location' => $tidalLocation,
                'station' => $tidalStation,
            ];

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
        ];

        return $result;
    }
}
