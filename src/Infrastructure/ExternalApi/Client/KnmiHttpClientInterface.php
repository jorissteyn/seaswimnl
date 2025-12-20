<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi\Client;

interface KnmiHttpClientInterface
{
    /**
     * Fetch weather data for a given location.
     *
     * @return array<string, mixed>|null Weather data or null on failure
     */
    public function fetchWeatherData(float $latitude, float $longitude): ?array;
}
