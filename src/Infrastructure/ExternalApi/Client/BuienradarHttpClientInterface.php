<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi\Client;

interface BuienradarHttpClientInterface
{
    /**
     * Fetch the list of Buienradar weather stations.
     *
     * @return array<int, array{code: string, name: string, latitude: float, longitude: float}>|null
     */
    public function fetchStations(): ?array;

    /**
     * Fetch current weather data for a given station.
     *
     * @return array<string, mixed>|null Normalized weather data or null on failure
     */
    public function fetchWeatherData(string $stationCode): ?array;
}
