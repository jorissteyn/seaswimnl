<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ExternalApi;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\WaterQuality;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\RijkswaterstaatAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RijkswaterstaatAdapterTest extends TestCase
{
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    public function testGetConditionsReturnsWaterConditions(): void
    {
        $client = $this->createMock(RwsHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWaterData')
            ->with('vlissingen')
            ->willReturn([
                'waterTemperature' => 18.5,
                'waterHeight' => 0.45,
                'waveHeight' => 0.8,
                'timestamp' => '2024-12-20T10:00:00+01:00',
            ]);

        $adapter = new RijkswaterstaatAdapter($client, $this->cache, 900);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNotNull($conditions);
        $this->assertSame(18.5, $conditions->getTemperature()->getCelsius());
        $this->assertSame(0.45, $conditions->getWaterHeight()->getMeters());
        $this->assertSame(0.8, $conditions->getWaveHeight()->getMeters());
    }

    public function testGetConditionsReturnsNullOnApiFailure(): void
    {
        $client = $this->createMock(RwsHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWaterData')
            ->willReturn(null);

        $adapter = new RijkswaterstaatAdapter($client, $this->cache, 900);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNull($conditions);
    }

    public function testGetConditionsReturnsCachedData(): void
    {
        $client = $this->createMock(RwsHttpClientInterface::class);
        $client->expects($this->once())
            ->method('fetchWaterData')
            ->willReturn([
                'waterTemperature' => 18.5,
                'waterHeight' => 0.45,
                'waveHeight' => 0.8,
                'timestamp' => '2024-12-20T10:00:00+01:00',
            ]);

        $adapter = new RijkswaterstaatAdapter($client, $this->cache, 900);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        // First call should hit the API
        $conditions1 = $adapter->getConditions($location);
        // Second call should use cache
        $conditions2 = $adapter->getConditions($location);

        $this->assertNotNull($conditions1);
        $this->assertNotNull($conditions2);
        $this->assertSame($conditions1->getTemperature()->getCelsius(), $conditions2->getTemperature()->getCelsius());
    }

    public function testGetConditionsReturnsStaleDataOnApiFailure(): void
    {
        $client = $this->createMock(RwsHttpClientInterface::class);
        $adapter = new RijkswaterstaatAdapter($client, $this->cache, 900);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        // First call succeeds
        $client->expects($this->exactly(2))
            ->method('fetchWaterData')
            ->willReturnOnConsecutiveCalls(
                [
                    'waterTemperature' => 18.5,
                    'waterHeight' => 0.45,
                    'waveHeight' => 0.8,
                    'timestamp' => '2024-12-20T10:00:00+01:00',
                ],
                null, // Second call fails
            );

        $conditions1 = $adapter->getConditions($location);
        $this->assertNotNull($conditions1);

        // Clear the main cache item to simulate expiry
        $this->cache->deleteItem('rws_water_vlissingen');

        // Second call should return stale data
        $conditions2 = $adapter->getConditions($location);
        $this->assertNotNull($conditions2);
        $this->assertSame(18.5, $conditions2->getTemperature()->getCelsius());
    }

    public function testMapsWaterQualityCorrectly(): void
    {
        $client = $this->createMock(RwsHttpClientInterface::class);
        $client->method('fetchWaterData')
            ->willReturn([
                'waterTemperature' => 18.5,
                'waterHeight' => 0.45,
                'waveHeight' => 0.8,
                'quality' => 'good',
                'timestamp' => '2024-12-20T10:00:00+01:00',
            ]);

        $adapter = new RijkswaterstaatAdapter($client, $this->cache, 900);
        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60);

        $conditions = $adapter->getConditions($location);

        $this->assertNotNull($conditions);
        $this->assertSame(WaterQuality::Good, $conditions->getQuality());
    }
}
