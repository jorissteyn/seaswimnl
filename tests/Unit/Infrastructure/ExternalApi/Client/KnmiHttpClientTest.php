<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ExternalApi\Client;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Seaswim\Infrastructure\ExternalApi\Client\KnmiHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KnmiHttpClientTest extends TestCase
{
    public function testFetchStationsReturnsHardcodedList(): void
    {
        $httpClient = new MockHttpClient();
        $client = new KnmiHttpClient($httpClient, new NullLogger(), 'https://www.daggegevens.knmi.nl', 30);

        $stations = $client->fetchStations();

        $this->assertIsArray($stations);
        $this->assertNotEmpty($stations);

        // Check that key stations are present
        $stationCodes = array_column($stations, 'code');
        $this->assertContains('260', $stationCodes); // De Bilt
        $this->assertContains('310', $stationCodes); // Vlissingen
        $this->assertContains('210', $stationCodes); // Valkenburg
    }

    public function testFetchHourlyDataNormalizesResponse(): void
    {
        $mockResponse = new MockResponse(json_encode([
            [
                'station_code' => 260,
                'date' => '20240115',
                'hour' => 14,
                'T' => 85,      // 8.5°C
                'FH' => 45,     // 4.5 m/s
                'DD' => 270,    // West
                'U' => 78,      // 78%
            ],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new KnmiHttpClient($httpClient, new NullLogger(), 'https://www.daggegevens.knmi.nl', 30);

        $data = $client->fetchHourlyData('260', new \DateTimeImmutable('2024-01-15 14:00:00'));

        $this->assertIsArray($data);
        $this->assertEqualsWithDelta(8.5, $data['temperature'], 0.01);
        $this->assertEqualsWithDelta(4.5, $data['windSpeed'], 0.01);
        $this->assertSame('W', $data['windDirection']);
        $this->assertSame(78, $data['humidity']);
    }

    public function testFetchHourlyDataHandlesEmptyResponse(): void
    {
        $mockResponse = new MockResponse(json_encode([]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new KnmiHttpClient($httpClient, new NullLogger(), 'https://www.daggegevens.knmi.nl', 30);

        $data = $client->fetchHourlyData('260', new \DateTimeImmutable());

        $this->assertNull($data);
    }

    public function testFetchHourlyDataHandlesApiError(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new KnmiHttpClient($httpClient, new NullLogger(), 'https://www.daggegevens.knmi.nl', 30);

        $data = $client->fetchHourlyData('260', new \DateTimeImmutable());

        $this->assertNull($data);
    }

    public function testWindDirectionConversion(): void
    {
        $testCases = [
            [360, 'N'],
            [90, 'E'],
            [180, 'S'],
            [270, 'W'],
            [45, 'NE'],
            [135, 'SE'],
            [225, 'SW'],
            [315, 'NW'],
            [0, 'Variable'],
        ];

        foreach ($testCases as [$degrees, $expectedDirection]) {
            $mockResponse = new MockResponse(json_encode([
                ['date' => '20240115', 'hour' => 14, 'T' => 100, 'FH' => 50, 'DD' => $degrees, 'U' => 80],
            ]));

            $httpClient = new MockHttpClient($mockResponse);
            $client = new KnmiHttpClient($httpClient, new NullLogger(), 'https://www.daggegevens.knmi.nl', 30);

            $data = $client->fetchHourlyData('260', new \DateTimeImmutable('2024-01-15 14:00:00'));

            $this->assertSame($expectedDirection, $data['windDirection'], "Failed for {$degrees} degrees");
        }
    }
}
