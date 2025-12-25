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
final class RwsHttpClient implements RwsHttpClientInterface
{
    private const METADATA_PATH = '/METADATASERVICES/OphalenCatalogus';
    private const LATEST_OBSERVATIONS_PATH = '/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';
    private const OBSERVATIONS_PATH = '/ONLINEWAARNEMINGENSERVICES/OphalenWaarnemingen';

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

    /**
     * Fetch the latest water data for a location (temperature, water height).
     *
     * @return array<string, mixed>|null Normalized water data or null on failure
     */
    public function fetchWaterData(string $locationCode): ?array
    {
        $this->lastError = null;

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
                [
                    'AquoMetadata' => [
                        'Compartiment' => ['Code' => 'LT'],
                        'Grootheid' => ['Code' => 'WINDSHD'],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Compartiment' => ['Code' => 'LT'],
                        'Grootheid' => ['Code' => 'WINDRTG'],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Compartiment' => ['Code' => 'OW'],
                        'Grootheid' => ['Code' => 'Tm02'],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Compartiment' => ['Code' => 'OW'],
                        'Grootheid' => ['Code' => 'Th3'],
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

            // 204 No Content means no data available for this location
            if (204 === $response->getStatusCode()) {
                $this->lastError = 'No water data available for this location';
                $this->logger->debug('RWS API returned no data for location', [
                    'location' => $locationCode,
                ]);

                return null;
            }

            $data = $response->toArray();

            return $this->normalizeWaterData($data);
        } catch (\Throwable $e) {
            $this->lastError = 'RWS API error: '.$e->getMessage();
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
     * @return array<int, array{code: string, name: string, latitude: float, longitude: float, compartimenten: array<string>, grootheden: array<string>}>|null
     */
    public function fetchLocations(): ?array
    {
        $this->lastError = null;

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
                $this->lastError = sprintf('RWS API returned HTTP %d', $statusCode);
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
            $this->lastError = sprintf('RWS API error: HTTP %d', $response->getStatusCode());
            $this->logger->error('RWS API locations request failed', [
                'status' => $response->getStatusCode(),
                'response' => $response->getContent(false),
                'exception' => $e,
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->lastError = 'RWS API error: '.$e->getMessage();
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
        $this->lastError = null;

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
            $this->lastError = 'RWS API error: '.$e->getMessage();
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
            'wavePeriod' => null,
            'waveDirection' => null,
            'windSpeed' => null,
            'windDirection' => null,
            'timestamp' => null,
            // Raw measurement metadata for tooltips
            'raw' => [
                'waterTemperature' => null,
                'waterHeight' => null,
                'waveHeight' => null,
                'wavePeriod' => null,
                'waveDirection' => null,
                'windSpeed' => null,
                'windDirection' => null,
            ],
        ];

        // Track timestamps for each measurement type to pick the most recent
        $timestamps = [
            'waterTemperature' => null,
            'waterHeight' => null,
            'waveHeight' => null,
            'wavePeriod' => null,
            'waveDirection' => null,
            'windSpeed' => null,
            'windDirection' => null,
        ];

        $observations = $data['WaarnemingenLijst'] ?? [];

        foreach ($observations as $observation) {
            $metadata = $observation['AquoMetadata'] ?? [];
            $measurements = $observation['MetingenLijst'] ?? [];
            $grootheid = $metadata['Grootheid']['Code'] ?? null;
            $compartiment = $metadata['Compartiment']['Code'] ?? null;

            if ([] === $measurements) {
                continue;
            }

            // Check each measurement in this observation
            foreach ($measurements as $measurement) {
                $value = $measurement['Meetwaarde']['Waarde_Numeriek'] ?? null;
                $timestamp = $measurement['Tijdstip'] ?? null;

                if (null === $value || null === $timestamp) {
                    continue;
                }

                switch ($grootheid) {
                    case 'T':
                        // Only use surface water temperature (OW), not air temperature (LT)
                        // Pick the most recent reading by timestamp
                        if ('OW' === $compartiment) {
                            if (null === $timestamps['waterTemperature'] || $timestamp > $timestamps['waterTemperature']) {
                                $result['waterTemperature'] = $value;
                                $timestamps['waterTemperature'] = $timestamp;
                                $result['timestamp'] = $timestamp;
                                $result['raw']['waterTemperature'] = [
                                    'code' => $grootheid,
                                    'compartiment' => $compartiment,
                                    'value' => $value,
                                    'unit' => '°C',
                                ];
                            }
                        }
                        break;
                    case 'WATHTE':
                        // Convert from cm to meters, pick most recent
                        if (null === $timestamps['waterHeight'] || $timestamp > $timestamps['waterHeight']) {
                            $result['waterHeight'] = $value / 100.0;
                            $timestamps['waterHeight'] = $timestamp;
                            $result['raw']['waterHeight'] = [
                                'code' => $grootheid,
                                'compartiment' => $compartiment,
                                'value' => $value,
                                'unit' => 'cm',
                            ];
                        }
                        break;
                    case 'Hm0':
                        // Convert from cm to meters, pick most recent
                        if (null === $timestamps['waveHeight'] || $timestamp > $timestamps['waveHeight']) {
                            $result['waveHeight'] = $value / 100.0;
                            $timestamps['waveHeight'] = $timestamp;
                            $result['raw']['waveHeight'] = [
                                'code' => $grootheid,
                                'compartiment' => $compartiment,
                                'value' => $value,
                                'unit' => 'cm',
                            ];
                        }
                        break;
                    case 'WINDSHD':
                        // Wind speed in m/s
                        if (null === $timestamps['windSpeed'] || $timestamp > $timestamps['windSpeed']) {
                            $result['windSpeed'] = $value;
                            $timestamps['windSpeed'] = $timestamp;
                            $result['raw']['windSpeed'] = [
                                'code' => $grootheid,
                                'compartiment' => $compartiment,
                                'value' => $value,
                                'unit' => 'm/s',
                            ];
                        }
                        break;
                    case 'WINDRTG':
                        // Wind direction in degrees, convert to compass direction
                        if (null === $timestamps['windDirection'] || $timestamp > $timestamps['windDirection']) {
                            $result['windDirection'] = $this->degreesToCompass((int) $value);
                            $timestamps['windDirection'] = $timestamp;
                            $result['raw']['windDirection'] = [
                                'code' => $grootheid,
                                'compartiment' => $compartiment,
                                'value' => $value,
                                'unit' => '°',
                            ];
                        }
                        break;
                    case 'Tm02':
                        // Wave period in seconds, pick most recent
                        if (null === $timestamps['wavePeriod'] || $timestamp > $timestamps['wavePeriod']) {
                            $result['wavePeriod'] = $value;
                            $timestamps['wavePeriod'] = $timestamp;
                            $result['raw']['wavePeriod'] = [
                                'code' => $grootheid,
                                'compartiment' => $compartiment,
                                'value' => $value,
                                'unit' => 's',
                            ];
                        }
                        break;
                    case 'Th3':
                        // Wave direction in degrees, pick most recent (keep as degrees for value object)
                        if (null === $timestamps['waveDirection'] || $timestamp > $timestamps['waveDirection']) {
                            $result['waveDirection'] = $value;
                            $timestamps['waveDirection'] = $timestamp;
                            $result['raw']['waveDirection'] = [
                                'code' => $grootheid,
                                'compartiment' => $compartiment,
                                'value' => $value,
                                'unit' => '°',
                            ];
                        }
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * Convert degrees to compass direction.
     */
    private function degreesToCompass(int $degrees): string
    {
        $directions = ['N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW'];
        $index = ((int) round($degrees / 22.5) % 16 + 16) % 16;

        return $directions[$index];
    }

    /**
     * Normalize locations from the catalog response.
     *
     * The RWS API returns locations and their metadata in separate lists with a join table:
     * - LocatieLijst: locations with Locatie_MessageID
     * - AquoMetadataLijst: metadata with AquoMetadata_MessageID, Compartiment.Code, Grootheid.Code
     * - AquoMetadataLocatieLijst: links Locatie_MessageID to AquoMetaData_MessageID
     *
     * @param array<string, mixed> $data
     *
     * @return array<int, array{code: string, name: string, latitude: float, longitude: float, compartimenten: array<string>, grootheden: array<string>}>
     */
    private function normalizeLocations(array $data): array
    {
        // Build metadata lookup: AquoMetadata_MessageID => {compartiment, grootheid}
        $metadataLookup = [];
        foreach ($data['AquoMetadataLijst'] ?? [] as $meta) {
            $messageId = $meta['AquoMetadata_MessageID'] ?? null;
            if (null === $messageId) {
                continue;
            }
            $metadataLookup[$messageId] = [
                'compartiment' => $meta['Compartiment']['Code'] ?? null,
                'grootheid' => $meta['Grootheid']['Code'] ?? null,
            ];
        }

        // Build location metadata mapping: Locatie_MessageID => [AquoMetaData_MessageID, ...]
        $locationMetadataMap = [];
        foreach ($data['AquoMetadataLocatieLijst'] ?? [] as $link) {
            $locMessageId = $link['Locatie_MessageID'] ?? null;
            $metaMessageId = $link['AquoMetaData_MessageID'] ?? null;
            if (null === $locMessageId || null === $metaMessageId) {
                continue;
            }
            $locationMetadataMap[$locMessageId][] = $metaMessageId;
        }

        $locations = [];
        $locationList = $data['LocatieLijst'] ?? [];

        foreach ($locationList as $location) {
            $code = $location['Code'] ?? null;
            $name = $location['Naam'] ?? $code;
            $lat = $location['Lat'] ?? null;
            $lon = $location['Lon'] ?? null;
            $messageId = $location['Locatie_MessageID'] ?? null;

            if (null === $code || null === $lat || null === $lon) {
                continue;
            }

            // Resolve compartimenten and grootheden via the join table
            $compartimenten = [];
            $grootheden = [];
            $metadataIds = $locationMetadataMap[$messageId] ?? [];
            foreach ($metadataIds as $metaId) {
                $meta = $metadataLookup[$metaId] ?? null;
                if (null === $meta) {
                    continue;
                }
                if (null !== $meta['compartiment'] && !\in_array($meta['compartiment'], $compartimenten, true)) {
                    $compartimenten[] = $meta['compartiment'];
                }
                if (null !== $meta['grootheid'] && !\in_array($meta['grootheid'], $grootheden, true)) {
                    $grootheden[] = $meta['grootheid'];
                }
            }

            sort($compartimenten);
            sort($grootheden);

            $locations[] = [
                'code' => $code,
                'name' => $name,
                'latitude' => (float) $lat,
                'longitude' => (float) $lon,
                'compartimenten' => $compartimenten,
                'grootheden' => $grootheden,
            ];
        }

        return $locations;
    }

    /**
     * Fetch all available raw measurements for a location.
     *
     * @return array<int, array{grootheid: string, compartiment: string, value: float, timestamp: string}>|null
     */
    public function fetchRawMeasurements(string $locationCode): ?array
    {
        $this->lastError = null;

        // Fetch all grootheden that might be available
        $grootheden = [
            'T', 'WATHTE', 'Hm0', 'Hmax', 'Tm02', 'Tm01', 'Th3', 'Th0', 'Fp',
            'WINDSHD', 'WINDRTG', 'WINDST', 'STROOMSHD', 'STROOMRTG',
            'SALNTT', 'GELDHD', 'LUCHTDK', 'Q',
        ];
        $compartimenten = ['OW', 'LT'];

        $metadataList = [];
        foreach ($compartimenten as $comp) {
            foreach ($grootheden as $groot) {
                $metadataList[] = [
                    'AquoMetadata' => [
                        'Compartiment' => ['Code' => $comp],
                        'Grootheid' => ['Code' => $groot],
                    ],
                ];
            }
        }

        $payload = [
            'LocatieLijst' => [
                ['Code' => $locationCode],
            ],
            'AquoPlusWaarnemingMetadataLijst' => $metadataList,
        ];

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.self::LATEST_OBSERVATIONS_PATH, [
                'json' => $payload,
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            if (204 === $response->getStatusCode()) {
                $this->lastError = 'No measurement data available for this location';

                return null;
            }

            $data = $response->toArray();

            return $this->normalizeRawMeasurements($data);
        } catch (\Throwable $e) {
            $this->lastError = 'RWS API error: '.$e->getMessage();
            $this->logger->error('RWS API raw measurements request failed', [
                'location' => $locationCode,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Normalize raw measurements from the API response.
     *
     * Returns one entry per grootheid/compartiment combination with the most recent value.
     *
     * @param array<string, mixed> $data
     *
     * @return array<int, array{grootheid: string, compartiment: string, value: float, timestamp: string}>
     */
    private function normalizeRawMeasurements(array $data): array
    {
        // Use a map to keep only the most recent value per grootheid/compartiment
        $resultMap = [];
        $observations = $data['WaarnemingenLijst'] ?? [];

        foreach ($observations as $observation) {
            $metadata = $observation['AquoMetadata'] ?? [];
            $measurements = $observation['MetingenLijst'] ?? [];
            $grootheid = $metadata['Grootheid']['Code'] ?? null;
            $compartiment = $metadata['Compartiment']['Code'] ?? null;

            if (null === $grootheid || null === $compartiment || [] === $measurements) {
                continue;
            }

            $key = $grootheid.'|'.$compartiment;

            // Get the most recent measurement from this observation
            foreach ($measurements as $measurement) {
                $value = $measurement['Meetwaarde']['Waarde_Numeriek'] ?? null;
                $timestamp = $measurement['Tijdstip'] ?? null;

                if (null === $value || null === $timestamp) {
                    continue;
                }

                // Only keep if newer than existing entry for this key
                if (!isset($resultMap[$key]) || $timestamp > $resultMap[$key]['timestamp']) {
                    $resultMap[$key] = [
                        'grootheid' => $grootheid,
                        'compartiment' => $compartiment,
                        'value' => (float) $value,
                        'timestamp' => $timestamp,
                    ];
                }
            }
        }

        return array_values($resultMap);
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
