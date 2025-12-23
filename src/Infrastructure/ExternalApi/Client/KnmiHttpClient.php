<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the KNMI daggegevens API.
 *
 * @see https://www.daggegevens.knmi.nl
 */
final readonly class KnmiHttpClient implements KnmiHttpClientInterface
{
    private const HOURLY_DATA_PATH = '/klimatologie/uurgegevens';

    /**
     * Hardcoded list of main KNMI automatic weather stations.
     * KNMI doesn't provide a stations API, so we maintain this list manually.
     */
    private const STATIONS = [
        ['code' => '210', 'name' => 'Valkenburg', 'latitude' => 52.17, 'longitude' => 4.42],
        ['code' => '225', 'name' => 'IJmuiden', 'latitude' => 52.46, 'longitude' => 4.56],
        ['code' => '235', 'name' => 'De Kooy', 'latitude' => 52.92, 'longitude' => 4.79],
        ['code' => '240', 'name' => 'Schiphol', 'latitude' => 52.30, 'longitude' => 4.77],
        ['code' => '249', 'name' => 'Berkhout', 'latitude' => 52.65, 'longitude' => 4.99],
        ['code' => '251', 'name' => 'Hoorn Terschelling', 'latitude' => 53.39, 'longitude' => 5.35],
        ['code' => '260', 'name' => 'De Bilt', 'latitude' => 52.10, 'longitude' => 5.18],
        ['code' => '267', 'name' => 'Stavoren', 'latitude' => 52.90, 'longitude' => 5.38],
        ['code' => '269', 'name' => 'Lelystad', 'latitude' => 52.46, 'longitude' => 5.53],
        ['code' => '270', 'name' => 'Leeuwarden', 'latitude' => 53.22, 'longitude' => 5.75],
        ['code' => '273', 'name' => 'Marknesse', 'latitude' => 52.70, 'longitude' => 5.89],
        ['code' => '275', 'name' => 'Deelen', 'latitude' => 52.06, 'longitude' => 5.87],
        ['code' => '277', 'name' => 'Lauwersoog', 'latitude' => 53.42, 'longitude' => 6.20],
        ['code' => '278', 'name' => 'Heino', 'latitude' => 52.44, 'longitude' => 6.26],
        ['code' => '279', 'name' => 'Hoogeveen', 'latitude' => 52.75, 'longitude' => 6.57],
        ['code' => '280', 'name' => 'Eelde', 'latitude' => 53.13, 'longitude' => 6.59],
        ['code' => '283', 'name' => 'Hupsel', 'latitude' => 52.07, 'longitude' => 6.65],
        ['code' => '286', 'name' => 'Nieuw Beerta', 'latitude' => 53.20, 'longitude' => 7.15],
        ['code' => '290', 'name' => 'Twenthe', 'latitude' => 52.27, 'longitude' => 6.90],
        ['code' => '310', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60],
        ['code' => '319', 'name' => 'Westdorpe', 'latitude' => 51.23, 'longitude' => 3.86],
        ['code' => '323', 'name' => 'Wilhelminadorp', 'latitude' => 51.53, 'longitude' => 3.88],
        ['code' => '330', 'name' => 'Hoek van Holland', 'latitude' => 51.98, 'longitude' => 4.12],
        ['code' => '340', 'name' => 'Woensdrecht', 'latitude' => 51.45, 'longitude' => 4.34],
        ['code' => '344', 'name' => 'Rotterdam', 'latitude' => 51.96, 'longitude' => 4.45],
        ['code' => '348', 'name' => 'Cabauw', 'latitude' => 51.97, 'longitude' => 4.93],
        ['code' => '350', 'name' => 'Gilze-Rijen', 'latitude' => 51.57, 'longitude' => 4.93],
        ['code' => '356', 'name' => 'Herwijnen', 'latitude' => 51.86, 'longitude' => 5.15],
        ['code' => '370', 'name' => 'Eindhoven', 'latitude' => 51.45, 'longitude' => 5.42],
        ['code' => '375', 'name' => 'Volkel', 'latitude' => 51.66, 'longitude' => 5.71],
        ['code' => '377', 'name' => 'Ell', 'latitude' => 51.20, 'longitude' => 5.76],
        ['code' => '380', 'name' => 'Maastricht', 'latitude' => 50.91, 'longitude' => 5.77],
        ['code' => '391', 'name' => 'Arcen', 'latitude' => 51.50, 'longitude' => 6.20],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
        private int $timeout,
    ) {
    }

    public function fetchStations(): ?array
    {
        // Return hardcoded stations since KNMI doesn't provide a stations API
        return self::STATIONS;
    }

    public function fetchHourlyData(string $stationCode, \DateTimeImmutable $date): ?array
    {
        // Format date for KNMI API (YYYYMMDDHH format)
        // We request data for the current hour
        $hour = (int) $date->format('H');
        // KNMI uses hours 1-24, not 0-23
        $knmiHour = 0 === $hour ? 24 : $hour;
        $startDate = $date->format('Ymd').sprintf('%02d', $knmiHour);
        $endDate = $startDate;

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.self::HOURLY_DATA_PATH, [
                'body' => [
                    'start' => $startDate,
                    'end' => $endDate,
                    'stns' => $stationCode,
                    'vars' => 'T:FH:DD:U',
                    'fmt' => 'json',
                ],
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = $response->toArray();

            if ([] === $data) {
                $this->logger->warning('KNMI API returned empty data', [
                    'station' => $stationCode,
                    'date' => $date->format('Y-m-d H:i'),
                ]);

                return null;
            }

            return $this->normalizeHourlyData($data);
        } catch (\Throwable $e) {
            $this->logger->error('KNMI API request failed', [
                'station' => $stationCode,
                'date' => $date->format('Y-m-d H:i'),
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Normalize KNMI hourly data response.
     *
     * KNMI uses 0.1 as base unit for most measurements.
     *
     * @param array<int, array<string, mixed>> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeHourlyData(array $data): array
    {
        // Get the last (most recent) entry
        $entry = end($data);

        if (false === $entry || !is_array($entry)) {
            return [
                'temperature' => null,
                'windSpeed' => null,
                'windDirection' => null,
                'humidity' => null,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ];
        }

        // T is in 0.1 degrees Celsius
        $temperature = isset($entry['T']) && '' !== $entry['T']
            ? (float) $entry['T'] / 10.0
            : null;

        // FH (hourly mean wind speed) is in 0.1 m/s
        $windSpeed = isset($entry['FH']) && '' !== $entry['FH']
            ? (float) $entry['FH'] / 10.0
            : null;

        // DD is wind direction in degrees (360=N, 90=E, 180=S, 270=W, 0=variable)
        $windDirection = isset($entry['DD']) && '' !== $entry['DD']
            ? $this->convertWindDirection((int) $entry['DD'])
            : null;

        // U is relative humidity in %
        $humidity = isset($entry['U']) && '' !== $entry['U']
            ? (int) $entry['U']
            : null;

        // Construct timestamp from date and hour
        $dateStr = $entry['date'] ?? (new \DateTimeImmutable())->format('Ymd');
        $hourInt = $entry['hour'] ?? (int) (new \DateTimeImmutable())->format('H');
        // KNMI hour 24 = midnight of next day, convert to standard format
        $baseDate = \DateTimeImmutable::createFromFormat('Ymd', (string) $dateStr);
        if (false === $baseDate) {
            $timestamp = new \DateTimeImmutable();
        } elseif (24 === $hourInt) {
            $timestamp = $baseDate->modify('+1 day')->setTime(0, 0);
        } else {
            $timestamp = $baseDate->setTime($hourInt, 0);
        }

        return [
            'temperature' => $temperature,
            'windSpeed' => $windSpeed,
            'windDirection' => $windDirection,
            'humidity' => $humidity,
            'timestamp' => $timestamp->format('c'),
        ];
    }

    /**
     * Convert wind direction from degrees to cardinal direction.
     */
    private function convertWindDirection(int $degrees): string
    {
        if (0 === $degrees) {
            return 'Variable';
        }

        $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        $index = ((int) round($degrees / 22.5)) % 16;

        return $directions[$index] ?? 'N';
    }
}
