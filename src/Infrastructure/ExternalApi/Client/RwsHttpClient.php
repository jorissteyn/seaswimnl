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
    private const OBSERVATIONS_PATH = '/ONLINEWAARNEMINGENSERVICES/OphalenWaarnemingen';

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
                'exception' => $e,
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

        $url = $this->baseUrl.self::METADATA_PATH;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $this->logger->debug('Response status: {status}', ['status' => $statusCode]);

            if ($statusCode >= 400) {
                $this->logger->error('RWS API locations request failed', [
                    'status' => $statusCode,
                    'response' => $response->getContent(false),
                ]);

                return null;
            }

            $data = $response->toArray();

            return $this->normalizeLocations($data);
        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $this->logger->error('RWS API locations request failed', [
                'status' => $response->getStatusCode(),
                'response' => $response->getContent(false),
                'exception' => $e,
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('RWS API locations request failed', [
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Fetch tidal predictions (astronomical water heights) for a location.
     *
     * @return array<int, array{timestamp: string, height: float}>|null Height in cm relative to NAP
     */
    public function fetchTidalPredictions(string $locationCode, \DateTimeImmutable $start, \DateTimeImmutable $end): ?array
    {
        $payload = [
            'Locatie' => ['Code' => $locationCode],
            'AquoPlusWaarnemingMetadata' => [
                'AquoMetadata' => [
                    'Grootheid' => ['Code' => 'WATHTE'],
                    'ProcesType' => 'astronomisch',
                ],
            ],
            'Periode' => [
                'Begindatumtijd' => $start->format('Y-m-d\TH:i:s.000P'),
                'Einddatumtijd' => $end->format('Y-m-d\TH:i:s.000P'),
            ],
        ];

        $url = $this->baseUrl.self::OBSERVATIONS_PATH;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            return $this->normalizeTidalPredictions($data);
        } catch (\Throwable $e) {
            $this->logger->error('RWS API tidal predictions request failed', [
                'location' => $locationCode,
                'exception' => $e,
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
            $lat = $location['Lat'] ?? null;
            $lon = $location['Lon'] ?? null;

            if (null === $code || null === $lat || null === $lon) {
                continue;
            }

            $locations[] = [
                'code' => $code,
                'name' => $name,
                'latitude' => (float) $lat,
                'longitude' => (float) $lon,
            ];
        }

        return $locations;
    }

    /**
     * Normalize tidal predictions from the observations response.
     *
     * @param array<string, mixed> $data
     *
     * @return array<int, array{timestamp: string, height: float}>
     */
    private function normalizeTidalPredictions(array $data): array
    {
        $predictions = [];
        $observations = $data['WaarnemingenLijst'] ?? [];

        if ([] === $observations) {
            return [];
        }

        $measurements = $observations[0]['MetingenLijst'] ?? [];

        foreach ($measurements as $measurement) {
            $timestamp = $measurement['Tijdstip'] ?? null;
            $height = $measurement['Meetwaarde']['Waarde_Numeriek'] ?? null;

            if (null === $timestamp || null === $height) {
                continue;
            }

            $predictions[] = [
                'timestamp' => $timestamp,
                'height' => (float) $height,
            ];
        }

        return $predictions;
    }
}
