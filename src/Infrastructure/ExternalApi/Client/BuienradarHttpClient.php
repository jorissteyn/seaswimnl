<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the Buienradar weather API.
 *
 * @see https://data.buienradar.nl/2.0/feed/json
 */
final class BuienradarHttpClient implements BuienradarHttpClientInterface
{
    /** @var array<string, mixed>|null */
    private ?array $cachedFeed = null;

    private ?string $lastError = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function fetchStations(): ?array
    {
        $this->lastError = null;
        $feed = $this->fetchFeed();

        if (null === $feed) {
            return null;
        }

        $measurements = $feed['actual']['stationmeasurements'] ?? [];

        if (!is_array($measurements)) {
            $this->lastError = 'Buienradar API returned invalid data format';

            return null;
        }

        $stations = [];
        foreach ($measurements as $station) {
            if (!isset($station['stationid'], $station['stationname'], $station['lat'], $station['lon'])) {
                continue;
            }

            $stations[] = [
                'code' => (string) $station['stationid'],
                'name' => $this->normalizeStationName((string) $station['stationname']),
                'latitude' => (float) $station['lat'],
                'longitude' => (float) $station['lon'],
            ];
        }

        return $stations;
    }

    public function fetchWeatherData(string $stationCode): ?array
    {
        $this->lastError = null;
        $feed = $this->fetchFeed();

        if (null === $feed) {
            return null;
        }

        $measurements = $feed['actual']['stationmeasurements'] ?? [];

        if (!is_array($measurements)) {
            $this->lastError = 'Buienradar API returned invalid data format';

            return null;
        }

        foreach ($measurements as $station) {
            if ((string) ($station['stationid'] ?? '') === $stationCode) {
                return $this->normalizeWeatherData($station);
            }
        }

        $this->lastError = sprintf('Buienradar station %s not found', $stationCode);
        $this->logger->warning('Buienradar station not found in feed', [
            'station' => $stationCode,
        ]);

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFeed(): ?array
    {
        // Return cached feed if available (within same request)
        if (null !== $this->cachedFeed) {
            return $this->cachedFeed;
        }

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'timeout' => $this->timeout,
            ]);

            $this->cachedFeed = $response->toArray();

            return $this->cachedFeed;
        } catch (\Throwable $e) {
            $this->lastError = 'Buienradar API error: '.$e->getMessage();
            $this->logger->error('Buienradar API request failed', [
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Remove "Meetstation " prefix from station names.
     */
    private function normalizeStationName(string $name): string
    {
        if (str_starts_with($name, 'Meetstation ')) {
            return substr($name, 12);
        }

        return $name;
    }

    /**
     * Normalize Buienradar weather data to our internal format.
     *
     * @param array<string, mixed> $station
     *
     * @return array<string, mixed>
     */
    private function normalizeWeatherData(array $station): array
    {
        // Temperature is already in Celsius
        $temperature = isset($station['temperature']) && '' !== $station['temperature']
            ? (float) $station['temperature']
            : null;

        // Wind speed is already in m/s
        $windSpeed = isset($station['windspeed']) && '' !== $station['windspeed']
            ? (float) $station['windspeed']
            : null;

        // Wind direction is already in cardinal format (N, NNE, etc.)
        $windDirection = isset($station['winddirection']) && '' !== $station['winddirection']
            ? (string) $station['winddirection']
            : null;

        // Humidity is in percentage
        $humidity = isset($station['humidity']) && '' !== $station['humidity']
            ? (int) $station['humidity']
            : null;

        // Sunpower is in W/m²
        $sunpower = isset($station['sunpower']) && '' !== $station['sunpower']
            ? (float) $station['sunpower']
            : null;

        // Parse timestamp (format: "2025-12-23T23:20:00")
        $timestamp = new \DateTimeImmutable();
        if (isset($station['timestamp']) && '' !== $station['timestamp']) {
            try {
                $timestamp = new \DateTimeImmutable($station['timestamp']);
            } catch (\Exception) {
                // Keep default
            }
        }

        return [
            'temperature' => $temperature,
            'windSpeed' => $windSpeed,
            'windDirection' => $windDirection,
            'humidity' => $humidity,
            'sunpower' => $sunpower,
            'timestamp' => $timestamp->format('c'),
            // Raw measurement metadata for tooltips
            'raw' => [
                'temperature' => null !== $temperature ? [
                    'field' => 'temperature',
                    'value' => $temperature,
                    'unit' => '°C',
                ] : null,
                'windSpeed' => null !== $windSpeed ? [
                    'field' => 'windspeed',
                    'value' => $windSpeed,
                    'unit' => 'm/s',
                ] : null,
                'windDirection' => null !== $windDirection ? [
                    'field' => 'winddirection',
                    'value' => $windDirection,
                    'unit' => '',
                ] : null,
                'sunpower' => null !== $sunpower ? [
                    'field' => 'sunpower',
                    'value' => $sunpower,
                    'unit' => 'W/m²',
                ] : null,
            ],
        ];
    }
}
