<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ExternalApi;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\BuienradarStationRepositoryInterface;
use Seaswim\Domain\Service\BuienradarStationMatcher;
use Seaswim\Domain\ValueObject\BuienradarStation;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Infrastructure\ExternalApi\BuienradarAdapter;
use Seaswim\Infrastructure\ExternalApi\Client\BuienradarHttpClientInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class BuienradarAdapterTest extends TestCase
{
    private ArrayAdapter $cache;
    private BuienradarStationMatcher $stationMatcher;
    private BuienradarStationRepositoryInterface $stationRepository;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->stationRepository = $this->createMock(BuienradarStationRepositoryInterface::class);
        $this->stationMatcher = new BuienradarStationMatcher($this->stationRepository);
    }

    public function testGetConditionsReturnsWeatherConditions(): void
    {
        $client = $this->createMock(BuienradarHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWeatherData')
            ->with('6310')
            ->willReturn([
                'temperature' => 22.0,
                'windSpeed' => 5.0,
                'windDirection' => 'NW',
                'humidity' => 78,
                'timestamp' => '2024-12-20T10:00:00+01:00',
            ]);

        $vlissingenStation = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);
        $this->stationRepository->method('findAll')->willReturn([$vlissingenStation]);

        $adapter = new BuienradarAdapter($client, $this->stationMatcher, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen havenmond', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNotNull($conditions);
        $this->assertSame(22.0, $conditions->getAirTemperature()->getCelsius());
        $this->assertSame(5.0, $conditions->getWindSpeed()->getMetersPerSecond());
        $this->assertSame('NW', $conditions->getWindDirection());
    }

    public function testGetConditionsDefaultsToDeBiltWhenNoStationMatch(): void
    {
        $client = $this->createMock(BuienradarHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWeatherData')
            ->with('6260') // De Bilt
            ->willReturn([
                'temperature' => 18.0,
                'windSpeed' => 3.0,
                'windDirection' => 'SW',
                'humidity' => 65,
                'timestamp' => '2024-12-20T10:00:00+01:00',
            ]);

        $this->stationRepository->method('findAll')->willReturn([
            new BuienradarStation('6260', 'De Bilt', 52.10, 5.18),
        ]);

        $adapter = new BuienradarAdapter($client, $this->stationMatcher, $this->cache, 1800);
        $location = new Location('offshore', 'Offshore platform XYZ', 52.00, 4.00);

        $conditions = $adapter->getConditions($location);

        $this->assertNotNull($conditions);
        $this->assertSame(18.0, $conditions->getAirTemperature()->getCelsius());
    }

    public function testGetConditionsReturnsCachedData(): void
    {
        $client = $this->createMock(BuienradarHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWeatherData')
            ->willReturn([
                'temperature' => 22.0,
                'windSpeed' => 5.0,
                'windDirection' => 'NW',
                'humidity' => 78,
                'timestamp' => '2024-12-20T10:00:00+01:00',
            ]);

        $vlissingenStation = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);
        $this->stationRepository->method('findAll')->willReturn([$vlissingenStation]);

        $adapter = new BuienradarAdapter($client, $this->stationMatcher, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen havenmond', 51.44, 3.60);

        // First call should hit the API
        $conditions1 = $adapter->getConditions($location);
        // Second call should use cache
        $conditions2 = $adapter->getConditions($location);

        $this->assertNotNull($conditions1);
        $this->assertNotNull($conditions2);
        $this->assertSame($conditions1->getAirTemperature()->getCelsius(), $conditions2->getAirTemperature()->getCelsius());
    }

    public function testGetConditionsReturnsNullOnApiFailure(): void
    {
        $client = $this->createMock(BuienradarHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWeatherData')
            ->willReturn(null);

        $vlissingenStation = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);
        $this->stationRepository->method('findAll')->willReturn([$vlissingenStation]);

        $adapter = new BuienradarAdapter($client, $this->stationMatcher, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen havenmond', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNull($conditions);
    }

    public function testHandlesPartialData(): void
    {
        $client = $this->createMock(BuienradarHttpClientInterface::class);
        $client->method('fetchWeatherData')
            ->willReturn([
                'temperature' => 22.0,
                'timestamp' => '2024-12-20T10:00:00+01:00',
                // windSpeed, windDirection, humidity are missing
            ]);

        $vlissingenStation = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);
        $this->stationRepository->method('findAll')->willReturn([$vlissingenStation]);

        $adapter = new BuienradarAdapter($client, $this->stationMatcher, $this->cache, 1800);
        $location = new Location('vlissingen', 'Vlissingen havenmond', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNotNull($conditions);
        $this->assertSame(22.0, $conditions->getAirTemperature()->getCelsius());
        $this->assertFalse($conditions->getWindSpeed()->isKnown());
        $this->assertNull($conditions->getWindDirection());
    }
}
