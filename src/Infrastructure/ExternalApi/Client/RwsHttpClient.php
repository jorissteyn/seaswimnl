<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for the Rijkswaterstaat WaterWebservices API.
 *
 * @see https://waterwebservices.rijkswaterstaat.nl
 */
final readonly class RwsHttpClient implements RwsHttpClientInterface
{
    private const METADATA_PATH = '/METADATASERVICES/OphalenCatalogus';
    private const LATEST_OBSERVATIONS_PATH = '/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
        private int $timeout,
    ) {
    }

    /**
     * Fetch the latest water data for a location (temperature, water height).
     *
     * @return array<string, mixed>|null Normalized water data or null on failure
     */
    public function fetchWaterData(string $locationCode): ?array
    {
        $payload = [
            'LocatieLijst' => [
                ['Code' => $locationCode],
            ],
            'AquoPlusWaarnemingMetadataLijst' => [
                [
                    'AquoMetadata' => [
                        'Compartiment' => ['Code' => 'OW'],
                        'Grootheid' => ['Code' => 'T'],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Compartiment' => ['Code' => 'OW'],
                        'Grootheid' => ['Code' => 'WATHTE'],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Compartiment' => ['Code' => 'OW'],
                        'Grootheid' => ['Code' => 'Hm0'],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.self::LATEST_OBSERVATIONS_PATH, [
                'json' => $payload,
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            return $this->normalizeWaterData($data);
        } catch (\Throwable $e) {
            $this->logger->error('RWS API request failed', [
                'location' => $locationCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch the catalog of available locations.
     *
     * @return array<int, array{code: string, name: string, latitude: float, longitude: float}>|null
     */
    public function fetchLocations(): ?array
    {
        $payload = [
            'CatalogusFilter' => [
                'Compartimenten' => true,
                'Grootheden' => true,
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.self::METADATA_PATH, [
                'json' => $payload,
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            return $this->normalizeLocations($data);
        } catch (\Throwable $e) {
            $this->logger->error('RWS API locations request failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Normalize the RWS API response into a simpler format for the adapter.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeWaterData(array $data): array
    {
        $result = [
            'waterTemperature' => null,
            'waterHeight' => null,
            'waveHeight' => null,
            'timestamp' => null,
        ];

        $observations = $data['WaarnemingenLijst'] ?? [];

        foreach ($observations as $observation) {
            $metadata = $observation['AquoMetadata'] ?? [];
            $measurements = $observation['MetingenLijst'] ?? [];
            $grootheid = $metadata['Grootheid']['Code'] ?? null;

            if ([] === $measurements) {
                continue;
            }

            $latestMeasurement = $measurements[\count($measurements) - 1];
            $value = $latestMeasurement['Meetwaarde']['Waarde_Numeriek'] ?? null;
            $timestamp = $latestMeasurement['Tijdstip'] ?? null;

            if (null !== $timestamp && null === $result['timestamp']) {
                $result['timestamp'] = $timestamp;
            }

            switch ($grootheid) {
                case 'T':
                    $result['waterTemperature'] = $value;
                    break;
                case 'WATHTE':
                    // Convert from cm to meters
                    $result['waterHeight'] = null !== $value ? $value / 100.0 : null;
                    break;
                case 'Hm0':
                    $result['waveHeight'] = $value;
                    break;
            }
        }

        return $result;
    }

    /**
     * Normalize locations from the catalog response.
     *
     * @param array<string, mixed> $data
     *
     * @return array<int, array{code: string, name: string, latitude: float, longitude: float}>
     */
    private function normalizeLocations(array $data): array
    {
        $locations = [];
        $locationList = $data['LocatieLijst'] ?? [];

        foreach ($locationList as $location) {
            $code = $location['Code'] ?? null;
            $name = $location['Naam'] ?? $code;
            $x = $location['X'] ?? null;
            $y = $location['Y'] ?? null;

            if (null === $code || null === $x || null === $y) {
                continue;
            }

            $locations[] = [
                'code' => $code,
                'name' => $name,
                'latitude' => (float) $y,
                'longitude' => (float) $x,
            ];
        }

        return $locations;
    }
}
