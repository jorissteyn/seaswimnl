<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ExternalApi\Client;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Seaswim\Infrastructure\ExternalApi\Client\BuienradarHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BuienradarHttpClientTest extends TestCase
{
    private function createSampleFeed(): array
    {
        return [
            'actual' => [
                'stationmeasurements' => [
                    [
                        'stationid' => 6260,
                        'stationname' => 'Meetstation De Bilt',
                        'lat' => 52.10,
                        'lon' => 5.18,
                        'temperature' => 8.5,
                        'windspeed' => 4.5,
                        'winddirection' => 'W',
                        'winddirectiondegrees' => 270,
                        'humidity' => 78,
                        'timestamp' => '2024-01-15T14:00:00',
                    ],
                    [
                        'stationid' => 6310,
                        'stationname' => 'Meetstation Vlissingen',
                        'lat' => 51.44,
                        'lon' => 3.60,
                        'temperature' => 9.0,
                        'windspeed' => 6.2,
                        'winddirection' => 'NW',
                        'winddirectiondegrees' => 315,
                        'humidity' => 82,
                        'timestamp' => '2024-01-15T14:00:00',
                    ],
                ],
            ],
        ];
    }

    public function testFetchStationsExtractsFromFeed(): void
    {
        $mockResponse = new MockResponse(json_encode($this->createSampleFeed()));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new BuienradarHttpClient($httpClient, new NullLogger(), 'https://data.buienradar.nl/2.0/feed/json', 30);

        $stations = $client->fetchStations();

        $this->assertIsArray($stations);
        $this->assertCount(2, $stations);

        // Check first station
        $this->assertSame('6260', $stations[0]['code']);
        $this->assertSame('De Bilt', $stations[0]['name']); // "Meetstation " prefix removed
        $this->assertSame(52.10, $stations[0]['latitude']);
        $this->assertSame(5.18, $stations[0]['longitude']);

        // Check second station
        $this->assertSame('6310', $stations[1]['code']);
        $this->assertSame('Vlissingen', $stations[1]['name']);
    }

    public function testFetchWeatherDataReturnsNormalizedData(): void
    {
        $mockResponse = new MockResponse(json_encode($this->createSampleFeed()));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new BuienradarHttpClient($httpClient, new NullLogger(), 'https://data.buienradar.nl/2.0/feed/json', 30);

        $data = $client->fetchWeatherData('6260');

        $this->assertIsArray($data);
        $this->assertSame(8.5, $data['temperature']);
        $this->assertSame(4.5, $data['windSpeed']);
        $this->assertSame('W', $data['windDirection']);
        $this->assertSame(78, $data['humidity']);
        $this->assertStringContainsString('2024-01-15', $data['timestamp']);
    }

    public function testFetchWeatherDataReturnsNullForUnknownStation(): void
    {
        $mockResponse = new MockResponse(json_encode($this->createSampleFeed()));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new BuienradarHttpClient($httpClient, new NullLogger(), 'https://data.buienradar.nl/2.0/feed/json', 30);

        $data = $client->fetchWeatherData('9999');

        $this->assertNull($data);
    }

    public function testFetchStationsHandlesEmptyFeed(): void
    {
        $mockResponse = new MockResponse(json_encode(['actual' => ['stationmeasurements' => []]]));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new BuienradarHttpClient($httpClient, new NullLogger(), 'https://data.buienradar.nl/2.0/feed/json', 30);

        $stations = $client->fetchStations();

        $this->assertIsArray($stations);
        $this->assertEmpty($stations);
    }

    public function testFetchStationsHandlesApiError(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new BuienradarHttpClient($httpClient, new NullLogger(), 'https://data.buienradar.nl/2.0/feed/json', 30);

        $stations = $client->fetchStations();

        $this->assertNull($stations);
    }

    public function testFeedIsCachedWithinSameRequest(): void
    {
        $callCount = 0;
        $mockResponse = function () use (&$callCount) {
            ++$callCount;

            return new MockResponse(json_encode($this->createSampleFeed()));
        };

        $httpClient = new MockHttpClient($mockResponse);
        $client = new BuienradarHttpClient($httpClient, new NullLogger(), 'https://data.buienradar.nl/2.0/feed/json', 30);

        // Multiple calls should only trigger one HTTP request
        $client->fetchStations();
        $client->fetchWeatherData('6260');
        $client->fetchWeatherData('6310');

        $this->assertSame(1, $callCount);
    }

    public function testHandlesMissingMeasurementFields(): void
    {
        $feed = [
            'actual' => [
                'stationmeasurements' => [
                    [
                        'stationid' => 6260,
                        'stationname' => 'Meetstation De Bilt',
                        'lat' => 52.10,
                        'lon' => 5.18,
                        'temperature' => 8.5,
                        // windspeed, winddirection, humidity missing
                    ],
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($feed));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new BuienradarHttpClient($httpClient, new NullLogger(), 'https://data.buienradar.nl/2.0/feed/json', 30);

        $data = $client->fetchWeatherData('6260');

        $this->assertIsArray($data);
        $this->assertSame(8.5, $data['temperature']);
        $this->assertNull($data['windSpeed']);
        $this->assertNull($data['windDirection']);
        $this->assertNull($data['humidity']);
    }
}
