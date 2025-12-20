<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ExternalApi;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Infrastructure\ExternalApi\Client\KnmiHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\KnmiAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class KnmiAdapterTest extends TestCase
{
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    public function testGetConditionsReturnsWeatherConditions(): void
    {
        $client = $this->createMock(KnmiHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWeatherData')
            ->with(51.44, 3.60)
            ->willReturn([
                'temperature' => 22.0,
                'windSpeed' => 5.0,
                'windDirection' => 'NW',
                'uvIndex' => 6,
                'timestamp' => '2024-12-20T10:00:00+01:00',
            ]);

        $adapter = new KnmiAdapter($client, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNotNull($conditions);
        $this->assertSame(22.0, $conditions->getAirTemperature()->getCelsius());
        $this->assertSame(5.0, $conditions->getWindSpeed()->getMetersPerSecond());
        $this->assertSame('NW', $conditions->getWindDirection());
        $this->assertSame(6, $conditions->getUvIndex()->getValue());
    }

    public function testGetConditionsReturnsNullOnApiFailure(): void
    {
        $client = $this->createMock(KnmiHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWeatherData')
            ->willReturn(null);

        $adapter = new KnmiAdapter($client, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNull($conditions);
    }

    public function testGetConditionsReturnsCachedData(): void
    {
        $client = $this->createMock(KnmiHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWeatherData')
            ->willReturn([
                'temperature' => 22.0,
                'windSpeed' => 5.0,
                'windDirection' => 'NW',
                'uvIndex' => 6,
                'timestamp' => '2024-12-20T10:00:00+01:00',
            ]);

        $adapter = new KnmiAdapter($client, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        // First call should hit the API
        $conditions1 = $adapter->getConditions($location);
        // Second call should use cache
        $conditions2 = $adapter->getConditions($location);

        $this->assertNotNull($conditions1);
        $this->assertNotNull($conditions2);
        $this->assertSame($conditions1->getAirTemperature()->getCelsius(), $conditions2->getAirTemperature()->getCelsius());
    }

    public function testGetConditionsReturnsStaleDataOnApiFailure(): void
    {
        $client = $this->createMock(KnmiHttpClientInterface::class);
        $adapter = new KnmiAdapter($client, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        // First call succeeds, second fails
        $client->expects($this->exactly(2))
            ->method('fetchWeatherData')
            ->willReturnOnConsecutiveCalls(
                [
                    'temperature' => 22.0,
                    'windSpeed' => 5.0,
                    'windDirection' => 'NW',
                    'uvIndex' => 6,
                    'timestamp' => '2024-12-20T10:00:00+01:00',
                ],
                null,
            );

        $conditions1 = $adapter->getConditions($location);
        $this->assertNotNull($conditions1);

        // Clear the main cache item to simulate expiry
        $this->cache->deleteItem('knmi_weather_vlissingen');

        // Second call should return stale data
        $conditions2 = $adapter->getConditions($location);
        $this->assertNotNull($conditions2);
        $this->assertSame(22.0, $conditions2->getAirTemperature()->getCelsius());
    }

    public function testHandlesPartialData(): void
    {
        $client = $this->createMock(KnmiHttpClientInterface::class);
        $client->method('fetchWeatherData')
            ->willReturn([
                'temperature' => 22.0,
                'timestamp' => '2024-12-20T10:00:00+01:00',
                // windSpeed, windDirection, uvIndex are missing
            ]);

        $adapter = new KnmiAdapter($client, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNotNull($conditions);
        $this->assertSame(22.0, $conditions->getAirTemperature()->getCelsius());
        $this->assertFalse($conditions->getWindSpeed()->isKnown());
        $this->assertNull($conditions->getWindDirection());
        $this->assertFalse($conditions->getUvIndex()->isKnown());
    }
}
