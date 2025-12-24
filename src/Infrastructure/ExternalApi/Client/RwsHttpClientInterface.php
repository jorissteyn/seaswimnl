<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi\Client;

interface RwsHttpClientInterface
{
    /**
     * Fetch the latest water data for a location (temperature, water height).
     *
     * @return array<string, mixed>|null Normalized water data or null on failure
     */
    public function fetchWaterData(string $locationCode): ?array;

    /**
     * Fetch the catalog of available locations.
     *
     * @return array<int, array{code: string, name: string, latitude: float, longitude: float, compartimenten: array<string>, grootheden: array<string>}>|null
     */
    public function fetchLocations(): ?array;

    /**
     * Fetch tidal predictions (astronomical water heights) for a location.
     *
     * @return array<int, array{timestamp: string, height: float}>|null Height in cm relative to NAP
     */
    public function fetchTidalPredictions(string $locationCode, \DateTimeImmutable $start, \DateTimeImmutable $end): ?array;

    /**
     * Get the last error message from a failed API call.
     */
    public function getLastError(): ?string;
}
