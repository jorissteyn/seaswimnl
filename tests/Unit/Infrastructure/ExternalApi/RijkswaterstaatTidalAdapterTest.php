<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ExternalApi;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Service\TideCalculator;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\TideEvent;
use Seaswim\Domain\ValueObject\TideInfo;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\RijkswaterstaatTidalAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RijkswaterstaatTidalAdapterTest extends TestCase
{
    private ArrayAdapter $cache;
    private RwsHttpClientInterface $mockClient;
    private TideCalculator $tideCalculator;
    private RijkswaterstaatTidalAdapter $adapter;
    private RwsLocation $location;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->mockClient = $this->createMock(RwsHttpClientInterface::class);
        $this->tideCalculator = new TideCalculator();
        $this->adapter = new RijkswaterstaatTidalAdapter(
            $this->mockClient,
            $this->tideCalculator,
            $this->cache,
            900
        );
        $this->location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60);
    }

    public function testGetTidalInfoReturnsValidTideInfo(): void
    {
        // Arrange
        $predictions = [
            ['timestamp' => '2024-01-01 06:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 09:00:00', 'height' => 200.0], // High tide
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0], // Low tide
            ['timestamp' => '2024-01-01 15:00:00', 'height' => 200.0], // High tide
            ['timestamp' => '2024-01-01 18:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->with(
                $this->equalTo('vlissingen'),
                $this->isInstanceOf(\DateTimeImmutable::class),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertInstanceOf(TideInfo::class, $tideInfo);
        $this->assertNotNull($tideInfo);
        $this->assertCount(3, $tideInfo->getEvents());
    }

    public function testGetTidalInfoReturnsNullWhenClientReturnsNull(): void
    {
        // Arrange
        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn(null);

        $this->mockClient->expects($this->once())
            ->method('getLastError')
            ->willReturn('API connection failed');

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNull($tideInfo);
        $this->assertSame('API connection failed', $this->adapter->getLastError());
    }

    public function testGetTidalInfoReturnsNullWhenPredictionsAreEmpty(): void
    {
        // Arrange
        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn([]);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNull($tideInfo);
        $this->assertSame('No tidal data available for this location', $this->adapter->getLastError());
    }

    public function testGetTidalInfoSetsCacheKey(): void
    {
        // Arrange
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 200.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $this->adapter->getTidalInfo($this->location);

        // Assert - cache should have the item
        $cacheItem = $this->cache->getItem('rws_tides_vlissingen');
        $this->assertTrue($cacheItem->isHit());
    }

    public function testGetTidalInfoReturnsCachedData(): void
    {
        // Arrange
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 200.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act - first call should hit the API
        $tideInfo1 = $this->adapter->getTidalInfo($this->location);

        // Second call should use cache (note: mockClient expects only one call)
        $tideInfo2 = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNotNull($tideInfo1);
        $this->assertNotNull($tideInfo2);
        // Verify both return the same data (event count should match)
        $this->assertCount(count($tideInfo1->getEvents()), $tideInfo2->getEvents());
    }

    public function testGetTidalInfoRequestsCorrectTimeRange(): void
    {
        // Arrange
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->with(
                $this->equalTo('vlissingen'),
                $this->callback(function (\DateTimeImmutable $start) {
                    // Verify start is approximately 12 hours before now
                    $now = new \DateTimeImmutable();
                    $diff = $now->getTimestamp() - $start->getTimestamp();

                    return $diff >= 43100 && $diff <= 43300; // ~12 hours with tolerance
                }),
                $this->callback(function (\DateTimeImmutable $end) {
                    // Verify end is approximately 12 hours after now
                    $now = new \DateTimeImmutable();
                    $diff = $end->getTimestamp() - $now->getTimestamp();

                    return $diff >= 43100 && $diff <= 43300; // ~12 hours with tolerance
                })
            )
            ->willReturn($predictions);

        // Act
        $this->adapter->getTidalInfo($this->location);
    }

    public function testGetTidalInfoPassesPredictionsToTideCalculator(): void
    {
        // Arrange
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 200.0], // High tide
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert - verify tide calculation produced expected results
        $this->assertNotNull($tideInfo);
        $events = $tideInfo->getEvents();
        $this->assertGreaterThan(0, count($events));
        $this->assertContainsOnlyInstancesOf(TideEvent::class, $events);
    }

    public function testGetTidalInfoHandlesComplexTidalPattern(): void
    {
        // Arrange - realistic tidal cycle
        $predictions = [
            ['timestamp' => '2024-01-01 00:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 01:00:00', 'height' => 130.0],
            ['timestamp' => '2024-01-01 02:00:00', 'height' => 110.0],
            ['timestamp' => '2024-01-01 03:00:00', 'height' => 95.0], // Low tide
            ['timestamp' => '2024-01-01 04:00:00', 'height' => 110.0],
            ['timestamp' => '2024-01-01 05:00:00', 'height' => 130.0],
            ['timestamp' => '2024-01-01 06:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 07:00:00', 'height' => 170.0],
            ['timestamp' => '2024-01-01 08:00:00', 'height' => 185.0],
            ['timestamp' => '2024-01-01 09:00:00', 'height' => 195.0], // High tide
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 185.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 170.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 130.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 110.0],
            ['timestamp' => '2024-01-01 15:00:00', 'height' => 95.0], // Low tide
            ['timestamp' => '2024-01-01 16:00:00', 'height' => 110.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNotNull($tideInfo);
        $events = $tideInfo->getEvents();
        $this->assertGreaterThanOrEqual(3, count($events));
    }

    public function testGetTidalInfoClearsLastErrorOnSuccess(): void
    {
        // Arrange - first call fails, second succeeds
        $this->mockClient->expects($this->exactly(2))
            ->method('fetchTidalPredictions')
            ->willReturnOnConsecutiveCalls(
                null,
                [
                    ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
                    ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
                    ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
                ]
            );

        $this->mockClient->expects($this->once())
            ->method('getLastError')
            ->willReturn('Previous error');

        // Act
        $tideInfo1 = $this->adapter->getTidalInfo($this->location);
        $this->assertNull($tideInfo1);
        $this->assertSame('Previous error', $this->adapter->getLastError());

        // Clear cache to force new fetch
        $this->cache->clear();

        $tideInfo2 = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNotNull($tideInfo2);
        $this->assertNull($this->adapter->getLastError());
    }

    public function testGetTidalInfoHandlesMinimalValidData(): void
    {
        // Arrange - just enough data to potentially detect one tide
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNotNull($tideInfo);
    }

    public function testGetTidalInfoHandlesNegativeHeights(): void
    {
        // Arrange - negative heights (below reference level NAP)
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => -50.0],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 0.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 50.0], // High tide
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 0.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => -50.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNotNull($tideInfo);
        $events = $tideInfo->getEvents();
        $this->assertGreaterThan(0, count($events));
    }

    public function testGetTidalInfoWithDifferentLocation(): void
    {
        // Arrange
        $hoekVanHolland = new RwsLocation('hoek-van-holland', 'Hoek van Holland', 51.98, 4.12);
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->with(
                $this->equalTo('hoek-van-holland'),
                $this->isInstanceOf(\DateTimeImmutable::class),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($hoekVanHolland);

        // Assert
        $this->assertNotNull($tideInfo);
    }

    public function testGetTidalInfoCachesPerLocation(): void
    {
        // Arrange
        $hoekVanHolland = new RwsLocation('hoek-van-holland', 'Hoek van Holland', 51.98, 4.12);
        $predictions1 = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];
        $predictions2 = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 200.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 250.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 200.0],
        ];

        $this->mockClient->expects($this->exactly(2))
            ->method('fetchTidalPredictions')
            ->willReturnCallback(function (string $locationCode) use ($predictions1, $predictions2) {
                return 'vlissingen' === $locationCode ? $predictions1 : $predictions2;
            });

        // Act - fetch for both locations
        $tideInfo1 = $this->adapter->getTidalInfo($this->location);
        $tideInfo2 = $this->adapter->getTidalInfo($hoekVanHolland);

        // Assert - both should have valid data
        $this->assertNotNull($tideInfo1);
        $this->assertNotNull($tideInfo2);

        // Verify separate cache keys exist
        $cacheItem1 = $this->cache->getItem('rws_tides_vlissingen');
        $cacheItem2 = $this->cache->getItem('rws_tides_hoek-van-holland');
        $this->assertTrue($cacheItem1->isHit());
        $this->assertTrue($cacheItem2->isHit());
    }

    public function testGetTidalInfoRespectsCacheTtl(): void
    {
        // Arrange - adapter with custom TTL
        $shortTtlAdapter = new RijkswaterstaatTidalAdapter(
            $this->mockClient,
            $this->tideCalculator,
            $this->cache,
            1 // 1 second TTL
        );

        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $shortTtlAdapter->getTidalInfo($this->location);

        // Assert - verify cache item was saved
        $cacheItem = $this->cache->getItem('rws_tides_vlissingen');
        $this->assertTrue($cacheItem->isHit());
    }

    public function testGetLastErrorReturnsNullInitially(): void
    {
        // Arrange - fresh adapter instance
        $freshAdapter = new RijkswaterstaatTidalAdapter(
            $this->mockClient,
            $this->tideCalculator,
            $this->cache,
            900
        );

        // Act & Assert
        $this->assertNull($freshAdapter->getLastError());
    }

    public function testGetTidalInfoHandlesFloatingPointHeights(): void
    {
        // Arrange - heights with decimal precision
        $predictions = [
            ['timestamp' => '2024-01-01 10:00:00', 'height' => 123.456],
            ['timestamp' => '2024-01-01 11:00:00', 'height' => 234.567],
            ['timestamp' => '2024-01-01 12:00:00', 'height' => 345.678],
            ['timestamp' => '2024-01-01 13:00:00', 'height' => 234.567],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 123.456],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNotNull($tideInfo);
        $this->assertInstanceOf(TideInfo::class, $tideInfo);
    }

    public function testGetTidalInfoHandlesClientErrorMessage(): void
    {
        // Arrange
        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn(null);

        $this->mockClient->expects($this->once())
            ->method('getLastError')
            ->willReturn('HTTP 500: Internal Server Error');

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNull($tideInfo);
        $this->assertSame('HTTP 500: Internal Server Error', $this->adapter->getLastError());
    }

    public function testGetTidalInfoHandlesClientNullErrorMessage(): void
    {
        // Arrange
        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn(null);

        $this->mockClient->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNull($tideInfo);
        $this->assertNull($this->adapter->getLastError());
    }

    public function testGetTidalInfoHandlesVeryLargeDataset(): void
    {
        // Arrange - 24 hours of data at 10-minute intervals
        $predictions = [];
        $baseTime = new \DateTimeImmutable('2024-01-01 00:00:00');

        for ($minutes = 0; $minutes < 1440; $minutes += 10) {
            // Simulate semi-diurnal tide
            $height = 150.0 + 50.0 * sin(($minutes / 360.0) * M_PI);
            $predictions[] = [
                'timestamp' => $baseTime->modify("+{$minutes} minutes")->format('Y-m-d H:i:s'),
                'height' => $height,
            ];
        }

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNotNull($tideInfo);
        $this->assertInstanceOf(TideInfo::class, $tideInfo);
    }

    public function testGetTidalInfoHandlesTimestampFormats(): void
    {
        // Arrange - various valid timestamp formats
        $predictions = [
            ['timestamp' => '2024-01-01T10:00:00+00:00', 'height' => 100.0],
            ['timestamp' => '2024-01-01T12:00:00Z', 'height' => 150.0],
            ['timestamp' => '2024-01-01 14:00:00', 'height' => 100.0],
        ];

        $this->mockClient->expects($this->once())
            ->method('fetchTidalPredictions')
            ->willReturn($predictions);

        // Act
        $tideInfo = $this->adapter->getTidalInfo($this->location);

        // Assert
        $this->assertNotNull($tideInfo);
    }
}
