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
     * @return array<int, array{code: string, name: string, latitude: float, longitude: float}>|null
     */
    public function fetchLocations(): ?array;
}
