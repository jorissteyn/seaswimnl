<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ExternalApi\Client;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RwsHttpClientTest extends TestCase
{
    private const BASE_URL = 'https://waterwebservices.rijkswaterstaat.nl';
    private const TIMEOUT = 30;

    // ========== fetchWaterData Tests ==========

    public function testFetchWaterDataReturnsNormalizedData(): void
    {
        $apiResponse = $this->createWaterDataResponse();
        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('HOEKVHLD');

        $this->assertIsArray($data);
        $this->assertSame(12.5, $data['waterTemperature']);
        $this->assertSame(1.23, $data['waterHeight']); // Converted from cm to meters
        $this->assertSame(0.50, $data['waveHeight']); // Converted from cm to meters
        $this->assertSame(4.5, $data['wavePeriod']);
        $this->assertEquals(180.0, $data['waveDirection']); // Wave direction in degrees
        $this->assertSame(7.8, $data['windSpeed']);
        $this->assertSame('W', $data['windDirection']);
        $this->assertNotNull($data['timestamp']);
        $this->assertIsArray($data['raw']);
    }

    public function testFetchWaterDataHandlesNoContentResponse(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('UNKNOWN');

        $this->assertNull($data);
        $this->assertSame('No water data available for this location', $client->getLastError());
    }

    public function testFetchWaterDataHandlesApiException(): void
    {
        $mockResponse = new MockResponse('Server error', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('HOEKVHLD');

        $this->assertNull($data);
        $this->assertStringContainsString('RWS API error:', $client->getLastError());
    }

    public function testFetchWaterDataHandlesTransportException(): void
    {
        $mockResponse = new MockResponse('', ['error' => 'Network timeout']);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('HOEKVHLD');

        $this->assertNull($data);
        $this->assertStringContainsString('RWS API error:', $client->getLastError());
    }

    public function testFetchWaterDataSelectsMostRecentMeasurement(): void
    {
        $apiResponse = [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        ['Tijdstip' => '2024-01-15T10:00:00', 'Meetwaarde' => ['Waarde_Numeriek' => 10.0]],
                        ['Tijdstip' => '2024-01-15T12:00:00', 'Meetwaarde' => ['Waarde_Numeriek' => 12.5]],
                        ['Tijdstip' => '2024-01-15T11:00:00', 'Meetwaarde' => ['Waarde_Numeriek' => 11.0]],
                    ],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('HOEKVHLD');

        // Should pick the most recent temperature (12.5 at 12:00:00)
        $this->assertSame(12.5, $data['waterTemperature']);
    }

    public function testFetchWaterDataIgnoresAirTemperature(): void
    {
        $apiResponse = [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'LT'], // Air temperature, not water
                    ],
                    'MetingenLijst' => [
                        ['Tijdstip' => '2024-01-15T12:00:00', 'Meetwaarde' => ['Waarde_Numeriek' => 20.0]],
                    ],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('HOEKVHLD');

        // Should not include air temperature
        $this->assertNull($data['waterTemperature']);
    }

    public function testFetchWaterDataHandlesEmptyMeasurements(): void
    {
        $apiResponse = [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('HOEKVHLD');

        $this->assertNull($data['waterTemperature']);
    }

    public function testFetchWaterDataHandlesMissingValues(): void
    {
        $apiResponse = [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        ['Tijdstip' => '2024-01-15T12:00:00'], // Missing Meetwaarde
                        ['Meetwaarde' => ['Waarde_Numeriek' => 12.5]], // Missing Tijdstip
                    ],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('HOEKVHLD');

        $this->assertNull($data['waterTemperature']);
    }

    public function testFetchWaterDataConvertsWindDegreesToCompass(): void
    {
        $testCases = [
            ['degrees' => 0, 'expected' => 'N'],
            ['degrees' => 45, 'expected' => 'NO'],
            ['degrees' => 90, 'expected' => 'O'],
            ['degrees' => 135, 'expected' => 'ZO'],
            ['degrees' => 180, 'expected' => 'Z'],
            ['degrees' => 225, 'expected' => 'ZW'],
            ['degrees' => 270, 'expected' => 'W'],
            ['degrees' => 315, 'expected' => 'NW'],
        ];

        foreach ($testCases as $testCase) {
            $apiResponse = [
                'WaarnemingenLijst' => [
                    [
                        'AquoMetadata' => [
                            'Grootheid' => ['Code' => 'WINDRTG'],
                            'Compartiment' => ['Code' => 'LT'],
                        ],
                        'MetingenLijst' => [
                            [
                                'Tijdstip' => '2024-01-15T12:00:00',
                                'Meetwaarde' => ['Waarde_Numeriek' => $testCase['degrees']],
                            ],
                        ],
                    ],
                ],
            ];

            $mockResponse = new MockResponse(json_encode($apiResponse));
            $httpClient = new MockHttpClient($mockResponse);
            $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

            $data = $client->fetchWaterData('HOEKVHLD');

            $this->assertSame(
                $testCase['expected'],
                $data['windDirection'],
                sprintf('Expected %d degrees to convert to %s', $testCase['degrees'], $testCase['expected'])
            );
        }
    }

    public function testFetchWaterDataIncludesRawMetadata(): void
    {
        $apiResponse = $this->createWaterDataResponse();
        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $data = $client->fetchWaterData('HOEKVHLD');

        $this->assertArrayHasKey('raw', $data);
        $this->assertArrayHasKey('waterTemperature', $data['raw']);
        $this->assertArrayHasKey('code', $data['raw']['waterTemperature']);
        $this->assertArrayHasKey('value', $data['raw']['waterTemperature']);
        $this->assertArrayHasKey('unit', $data['raw']['waterTemperature']);
    }

    public function testFetchWaterDataUsesCorrectEndpoint(): void
    {
        $mockResponse = new MockResponse(json_encode(['WaarnemingenLijst' => []]));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $client->fetchWaterData('HOEKVHLD');

        // Verify the request was made
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen', $mockResponse->getRequestUrl());
    }

    // ========== fetchLocations Tests ==========

    public function testFetchLocationsReturnsNormalizedData(): void
    {
        $apiResponse = $this->createLocationsResponse();
        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        $this->assertIsArray($locations);
        $this->assertCount(1, $locations);

        $location = $locations[0];
        $this->assertSame('HOEKVHLD', $location['code']);
        $this->assertSame('Hoek van Holland', $location['name']);
        $this->assertSame(51.9775, $location['latitude']);
        $this->assertSame(4.1225, $location['longitude']);
        $this->assertIsArray($location['compartimenten']);
        $this->assertIsArray($location['grootheden']);
        $this->assertContains('OW', $location['compartimenten']);
        $this->assertContains('T', $location['grootheden']);
    }

    public function testFetchLocationsHandlesHttpError(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        $this->assertNull($locations);
        $this->assertStringContainsString('RWS API returned HTTP 500', $client->getLastError());
    }

    public function testFetchLocationsHandlesHttpException(): void
    {
        $mockResponse = new MockResponse('Bad Request', ['http_code' => 400]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        $this->assertNull($locations);
        $this->assertStringContainsString('RWS API', $client->getLastError());
    }

    public function testFetchLocationsHandlesTransportException(): void
    {
        $mockResponse = new MockResponse('', ['error' => 'Connection timeout']);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        $this->assertNull($locations);
        $this->assertStringContainsString('RWS API error:', $client->getLastError());
    }

    public function testFetchLocationsHandlesMissingCoordinates(): void
    {
        $apiResponse = [
            'LocatieLijst' => [
                ['Code' => 'LOC1', 'Naam' => 'Location 1', 'Lat' => 51.0], // Missing Lon
                ['Code' => 'LOC2', 'Naam' => 'Location 2', 'Lon' => 4.0], // Missing Lat
                ['Code' => 'LOC3', 'Naam' => 'Location 3', 'Lat' => 52.0, 'Lon' => 5.0], // Valid
            ],
            'AquoMetadataLijst' => [],
            'AquoMetadataLocatieLijst' => [],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        $this->assertIsArray($locations);
        $this->assertCount(1, $locations); // Only LOC3 should be included
        $this->assertSame('LOC3', $locations[0]['code']);
    }

    public function testFetchLocationsHandlesMissingLocationCode(): void
    {
        $apiResponse = [
            'LocatieLijst' => [
                ['Naam' => 'Location 1', 'Lat' => 51.0, 'Lon' => 4.0], // Missing Code
            ],
            'AquoMetadataLijst' => [],
            'AquoMetadataLocatieLijst' => [],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        $this->assertIsArray($locations);
        $this->assertEmpty($locations);
    }

    public function testFetchLocationsJoinsMetadataCorrectly(): void
    {
        $apiResponse = [
            'LocatieLijst' => [
                [
                    'Code' => 'LOC1',
                    'Naam' => 'Location 1',
                    'Lat' => 51.0,
                    'Lon' => 4.0,
                    'Locatie_MessageID' => 1,
                ],
            ],
            'AquoMetadataLijst' => [
                [
                    'AquoMetadata_MessageID' => 10,
                    'Compartiment' => ['Code' => 'OW'],
                    'Grootheid' => ['Code' => 'T'],
                ],
                [
                    'AquoMetadata_MessageID' => 11,
                    'Compartiment' => ['Code' => 'OW'],
                    'Grootheid' => ['Code' => 'WATHTE'],
                ],
            ],
            'AquoMetadataLocatieLijst' => [
                ['Locatie_MessageID' => 1, 'AquoMetaData_MessageID' => 10],
                ['Locatie_MessageID' => 1, 'AquoMetaData_MessageID' => 11],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        $this->assertCount(1, $locations);
        $this->assertSame(['OW'], $locations[0]['compartimenten']);
        $this->assertEqualsCanonicalizing(['T', 'WATHTE'], $locations[0]['grootheden']);
    }

    public function testFetchLocationsSortsCompartimentenAndGrootheden(): void
    {
        $apiResponse = [
            'LocatieLijst' => [
                [
                    'Code' => 'LOC1',
                    'Naam' => 'Location 1',
                    'Lat' => 51.0,
                    'Lon' => 4.0,
                    'Locatie_MessageID' => 1,
                ],
            ],
            'AquoMetadataLijst' => [
                ['AquoMetadata_MessageID' => 1, 'Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'WATHTE']],
                ['AquoMetadata_MessageID' => 2, 'Compartiment' => ['Code' => 'LT'], 'Grootheid' => ['Code' => 'T']],
                ['AquoMetadata_MessageID' => 3, 'Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'Hm0']],
            ],
            'AquoMetadataLocatieLijst' => [
                ['Locatie_MessageID' => 1, 'AquoMetaData_MessageID' => 1],
                ['Locatie_MessageID' => 1, 'AquoMetaData_MessageID' => 2],
                ['Locatie_MessageID' => 1, 'AquoMetaData_MessageID' => 3],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        // Should be sorted alphabetically
        $this->assertSame(['LT', 'OW'], $locations[0]['compartimenten']);
        $this->assertSame(['Hm0', 'T', 'WATHTE'], $locations[0]['grootheden']);
    }

    public function testFetchLocationsDeduplicatesMetadata(): void
    {
        $apiResponse = [
            'LocatieLijst' => [
                [
                    'Code' => 'LOC1',
                    'Naam' => 'Location 1',
                    'Lat' => 51.0,
                    'Lon' => 4.0,
                    'Locatie_MessageID' => 1,
                ],
            ],
            'AquoMetadataLijst' => [
                ['AquoMetadata_MessageID' => 1, 'Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'T']],
                ['AquoMetadata_MessageID' => 2, 'Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'T']],
            ],
            'AquoMetadataLocatieLijst' => [
                ['Locatie_MessageID' => 1, 'AquoMetaData_MessageID' => 1],
                ['Locatie_MessageID' => 1, 'AquoMetaData_MessageID' => 2],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $locations = $client->fetchLocations();

        $this->assertCount(1, $locations[0]['compartimenten']);
        $this->assertCount(1, $locations[0]['grootheden']);
        $this->assertSame(['OW'], $locations[0]['compartimenten']);
        $this->assertSame(['T'], $locations[0]['grootheden']);
    }

    // ========== fetchTidalPredictions Tests ==========

    public function testFetchTidalPredictionsReturnsNormalizedData(): void
    {
        $apiResponse = $this->createTidalPredictionsResponse();
        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $start = new \DateTimeImmutable('2024-01-15T00:00:00+00:00');
        $end = new \DateTimeImmutable('2024-01-15T23:59:59+00:00');
        $predictions = $client->fetchTidalPredictions('HOEKVHLD', $start, $end);

        $this->assertIsArray($predictions);
        $this->assertCount(2, $predictions);
        $this->assertSame('2024-01-15T06:00:00+00:00', $predictions[0]['timestamp']);
        $this->assertSame(150.0, $predictions[0]['height']);
        $this->assertSame('2024-01-15T18:00:00+00:00', $predictions[1]['timestamp']);
        $this->assertSame(-50.0, $predictions[1]['height']);
    }

    public function testFetchTidalPredictionsHandlesEmptyResponse(): void
    {
        $apiResponse = ['WaarnemingenLijst' => []];
        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $start = new \DateTimeImmutable('2024-01-15T00:00:00+00:00');
        $end = new \DateTimeImmutable('2024-01-15T23:59:59+00:00');
        $predictions = $client->fetchTidalPredictions('HOEKVHLD', $start, $end);

        $this->assertIsArray($predictions);
        $this->assertEmpty($predictions);
    }

    public function testFetchTidalPredictionsHandlesApiException(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $start = new \DateTimeImmutable('2024-01-15T00:00:00+00:00');
        $end = new \DateTimeImmutable('2024-01-15T23:59:59+00:00');
        $predictions = $client->fetchTidalPredictions('HOEKVHLD', $start, $end);

        $this->assertNull($predictions);
        $this->assertStringContainsString('RWS API error:', $client->getLastError());
    }

    public function testFetchTidalPredictionsUsesCorrectEndpoint(): void
    {
        $mockResponse = new MockResponse(json_encode(['WaarnemingenLijst' => []]));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $start = new \DateTimeImmutable('2024-01-15T00:00:00+01:00');
        $end = new \DateTimeImmutable('2024-01-15T23:59:59+01:00');
        $client->fetchTidalPredictions('HOEKVHLD', $start, $end);

        // Verify the request was made to the correct endpoint
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/ONLINEWAARNEMINGENSERVICES/OphalenWaarnemingen', $mockResponse->getRequestUrl());
    }

    public function testFetchTidalPredictionsSkipsMissingValues(): void
    {
        $apiResponse = [
            'WaarnemingenLijst' => [
                [
                    'MetingenLijst' => [
                        ['Tijdstip' => '2024-01-15T06:00:00+00:00'], // Missing height
                        ['Meetwaarde' => ['Waarde_Numeriek' => 150.0]], // Missing timestamp
                        ['Tijdstip' => '2024-01-15T18:00:00+00:00', 'Meetwaarde' => ['Waarde_Numeriek' => -50.0]], // Valid
                    ],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $start = new \DateTimeImmutable('2024-01-15T00:00:00+00:00');
        $end = new \DateTimeImmutable('2024-01-15T23:59:59+00:00');
        $predictions = $client->fetchTidalPredictions('HOEKVHLD', $start, $end);

        $this->assertCount(1, $predictions);
        $this->assertSame('2024-01-15T18:00:00+00:00', $predictions[0]['timestamp']);
    }

    // ========== fetchRawMeasurements Tests ==========

    public function testFetchRawMeasurementsReturnsNormalizedData(): void
    {
        $apiResponse = $this->createRawMeasurementsResponse();
        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $measurements = $client->fetchRawMeasurements('HOEKVHLD');

        $this->assertIsArray($measurements);
        $this->assertCount(2, $measurements);

        $this->assertSame('T', $measurements[0]['grootheid']);
        $this->assertSame('OW', $measurements[0]['compartiment']);
        $this->assertSame(12.5, $measurements[0]['value']);
        $this->assertSame('2024-01-15T12:00:00+00:00', $measurements[0]['timestamp']);

        $this->assertSame('WATHTE', $measurements[1]['grootheid']);
        $this->assertSame('OW', $measurements[1]['compartiment']);
        $this->assertSame(123.0, $measurements[1]['value']);
        $this->assertSame('2024-01-15T12:05:00+00:00', $measurements[1]['timestamp']);
    }

    public function testFetchRawMeasurementsHandlesNoContentResponse(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $measurements = $client->fetchRawMeasurements('UNKNOWN');

        $this->assertNull($measurements);
        $this->assertSame('No measurement data available for this location', $client->getLastError());
    }

    public function testFetchRawMeasurementsHandlesApiException(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $measurements = $client->fetchRawMeasurements('HOEKVHLD');

        $this->assertNull($measurements);
        $this->assertStringContainsString('RWS API error:', $client->getLastError());
    }

    public function testFetchRawMeasurementsKeepsMostRecentPerType(): void
    {
        $apiResponse = [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        ['Tijdstip' => '2024-01-15T10:00:00+00:00', 'Meetwaarde' => ['Waarde_Numeriek' => 10.0]],
                        ['Tijdstip' => '2024-01-15T12:00:00+00:00', 'Meetwaarde' => ['Waarde_Numeriek' => 12.5]],
                    ],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $measurements = $client->fetchRawMeasurements('HOEKVHLD');

        $this->assertCount(1, $measurements);
        $this->assertSame(12.5, $measurements[0]['value']);
        $this->assertSame('2024-01-15T12:00:00+00:00', $measurements[0]['timestamp']);
    }

    public function testFetchRawMeasurementsSkipsMissingMetadata(): void
    {
        $apiResponse = [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        // Missing Compartiment
                    ],
                    'MetingenLijst' => [
                        ['Tijdstip' => '2024-01-15T12:00:00+00:00', 'Meetwaarde' => ['Waarde_Numeriek' => 12.5]],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        // Missing Grootheid
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        ['Tijdstip' => '2024-01-15T12:00:00+00:00', 'Meetwaarde' => ['Waarde_Numeriek' => 123.0]],
                    ],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $measurements = $client->fetchRawMeasurements('HOEKVHLD');

        $this->assertIsArray($measurements);
        $this->assertEmpty($measurements);
    }

    public function testFetchRawMeasurementsSkipsEmptyMeasurements(): void
    {
        $apiResponse = [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($apiResponse));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $measurements = $client->fetchRawMeasurements('HOEKVHLD');

        $this->assertIsArray($measurements);
        $this->assertEmpty($measurements);
    }

    // ========== Error Handling Tests ==========

    public function testGetLastErrorReturnsNullInitially(): void
    {
        $httpClient = new MockHttpClient();
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $this->assertNull($client->getLastError());
    }

    public function testGetLastErrorClearedOnSuccessfulRequest(): void
    {
        // First request fails
        $mockResponse1 = new MockResponse('', ['http_code' => 500]);
        $httpClient1 = new MockHttpClient($mockResponse1);
        $client = new RwsHttpClient($httpClient1, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $client->fetchWaterData('HOEKVHLD');
        $this->assertNotNull($client->getLastError());

        // Second request succeeds
        $mockResponse2 = new MockResponse(json_encode($this->createWaterDataResponse()));
        $httpClient2 = new MockHttpClient($mockResponse2);
        $client = new RwsHttpClient($httpClient2, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $client->fetchWaterData('HOEKVHLD');
        $this->assertNull($client->getLastError());
    }

    public function testErrorMessageIncludesContext(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new RwsHttpClient($httpClient, new NullLogger(), self::BASE_URL, self::TIMEOUT);

        $client->fetchWaterData('HOEKVHLD');

        $error = $client->getLastError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('RWS API', $error);
    }

    // ========== Helper Methods ==========

    private function createWaterDataResponse(): array
    {
        return [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 12.5],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'WATHTE'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 123.0],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'Hm0'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 50.0],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'WINDSHD'],
                        'Compartiment' => ['Code' => 'LT'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 7.8],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'WINDRTG'],
                        'Compartiment' => ['Code' => 'LT'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 270],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'Tm02'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 4.5],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'Th3'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 180.0],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createLocationsResponse(): array
    {
        return [
            'LocatieLijst' => [
                [
                    'Code' => 'HOEKVHLD',
                    'Naam' => 'Hoek van Holland',
                    'Lat' => 51.9775,
                    'Lon' => 4.1225,
                    'Locatie_MessageID' => 1,
                ],
            ],
            'AquoMetadataLijst' => [
                [
                    'AquoMetadata_MessageID' => 10,
                    'Compartiment' => ['Code' => 'OW'],
                    'Grootheid' => ['Code' => 'T'],
                ],
            ],
            'AquoMetadataLocatieLijst' => [
                ['Locatie_MessageID' => 1, 'AquoMetaData_MessageID' => 10],
            ],
        ];
    }

    private function createTidalPredictionsResponse(): array
    {
        return [
            'WaarnemingenLijst' => [
                [
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T06:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 150.0],
                        ],
                        [
                            'Tijdstip' => '2024-01-15T18:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => -50.0],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createRawMeasurementsResponse(): array
    {
        return [
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:00:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 12.5],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'WATHTE'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2024-01-15T12:05:00+00:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 123.0],
                        ],
                    ],
                ],
            ],
        ];
    }
}
