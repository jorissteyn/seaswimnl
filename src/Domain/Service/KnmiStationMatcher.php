<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

use Seaswim\Application\Port\KnmiStationRepositoryInterface;
use Seaswim\Domain\ValueObject\KnmiStation;

final readonly class KnmiStationMatcher
{
    private const MAX_LEVENSHTEIN_DISTANCE = 3;
    private const DEFAULT_STATION_CODE = '260'; // De Bilt

    public function __construct(
        private KnmiStationRepositoryInterface $stationRepository,
    ) {
    }

    public function findMatchingStation(string $locationName): ?KnmiStation
    {
        $stations = $this->stationRepository->findAll();

        if ([] === $stations) {
            return null;
        }

        $defaultStation = null;
        foreach ($stations as $station) {
            if (self::DEFAULT_STATION_CODE === $station->getCode()) {
                $defaultStation = $station;
                break;
            }
        }

        $normalizedLocationName = $this->normalize($locationName);
        $locationFirstWord = $this->extractFirstWord($normalizedLocationName);

        // First try exact match on first word
        foreach ($stations as $station) {
            $normalizedStationName = $this->normalize($station->getName());
            $stationFirstWord = $this->extractFirstWord($normalizedStationName);

            if ($locationFirstWord === $stationFirstWord) {
                return $station;
            }
        }

        // Then try fuzzy match using Levenshtein distance
        $bestMatch = null;
        $bestDistance = self::MAX_LEVENSHTEIN_DISTANCE + 1;

        foreach ($stations as $station) {
            $normalizedStationName = $this->normalize($station->getName());
            $stationFirstWord = $this->extractFirstWord($normalizedStationName);

            $distance = levenshtein($locationFirstWord, $stationFirstWord);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestMatch = $station;
            }
        }

        if ($bestDistance <= self::MAX_LEVENSHTEIN_DISTANCE) {
            return $bestMatch;
        }

        return $defaultStation;
    }

    private function normalize(string $name): string
    {
        // Convert to lowercase
        $normalized = mb_strtolower($name);

        // Remove dots and other punctuation
        $normalized = preg_replace('/[.\-_\/]/', ' ', $normalized) ?? $normalized;

        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function extractFirstWord(string $name): string
    {
        $parts = explode(' ', $name);

        return $parts[0] ?? '';
    }
}
