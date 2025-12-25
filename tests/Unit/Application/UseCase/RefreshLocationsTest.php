<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Application\UseCase;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\BuienradarStationRepositoryInterface;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\UseCase\RefreshLocations;
use Seaswim\Infrastructure\ExternalApi\Client\BuienradarHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final class RefreshLocationsTest extends TestCase
{
    public function testExecuteRefreshesLocationsAndStations(): void
    {
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $buienradarStationRepository = $this->createMock(BuienradarStationRepositoryInterface::class);
        $buienradarClient = $this->createMock(BuienradarHttpClientInterface::class);

        $rwsClient->expects($this->once())
            ->method('fetchLocations')
            ->willReturn([
                // Has both required grootheden (WINDSHD, WINDRTG)
                ['code' => 'vlissingen', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60, 'compartimenten' => ['OW'], 'grootheden' => ['T', 'WATHTE', 'WINDSHD', 'WINDRTG']],
                // Has both required grootheden (WINDSHD, WINDRTG)
                ['code' => 'hoekvanholland', 'name' => 'Hoek van Holland', 'latitude' => 51.98, 'longitude' => 4.12, 'compartimenten' => ['OW'], 'grootheden' => ['T', 'WATHTE', 'Hm0', 'WINDSHD', 'WINDRTG']],
                // Missing WINDSHD - will be filtered out
                ['code' => 'ijmuiden', 'name' => 'IJmuiden', 'latitude' => 52.46, 'longitude' => 4.55, 'compartimenten' => ['OW'], 'grootheden' => ['T', 'WINDRTG']],
            ]);

        $buienradarClient->expects($this->once())
            ->method('fetchStations')
            ->willReturn([
                ['code' => '6310', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60],
                ['code' => '6330', 'name' => 'Hoek van Holland', 'latitude' => 51.98, 'longitude' => 4.12],
            ]);

        $locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(fn ($locations) => 2 === \count($locations)));

        $buienradarStationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(fn ($stations) => 2 === \count($stations)));

        $useCase = new RefreshLocations($locationRepository, $rwsClient, $buienradarStationRepository, $buienradarClient);
        $result = $useCase->execute();

        $this->assertIsArray($result['locations']);
        $this->assertSame(2, $result['locations']['imported']);
        $this->assertSame(3, $result['locations']['total']);
        $this->assertArrayHasKey('filterSteps', $result['locations']);
        $this->assertSame(2, $result['stations']);
    }

    public function testExecuteReturnsMinusOneOnRwsApiFailure(): void
    {
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $buienradarStationRepository = $this->createMock(BuienradarStationRepositoryInterface::class);
        $buienradarClient = $this->createMock(BuienradarHttpClientInterface::class);

        $rwsClient->expects($this->once())
            ->method('fetchLocations')
            ->willReturn(null);

        $buienradarClient->expects($this->once())
            ->method('fetchStations')
            ->willReturn([
                ['code' => '6310', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60],
            ]);

        $locationRepository->expects($this->never())
            ->method('saveAll');

        $useCase = new RefreshLocations($locationRepository, $rwsClient, $buienradarStationRepository, $buienradarClient);
        $result = $useCase->execute();

        $this->assertSame(-1, $result['locations']);
        $this->assertSame(1, $result['stations']);
    }

    public function testExecuteReturnsMinusOneOnBuienradarApiFailure(): void
    {
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $buienradarStationRepository = $this->createMock(BuienradarStationRepositoryInterface::class);
        $buienradarClient = $this->createMock(BuienradarHttpClientInterface::class);

        $rwsClient->expects($this->once())
            ->method('fetchLocations')
            ->willReturn([
                // Has both required grootheden (WINDSHD, WINDRTG)
                ['code' => 'vlissingen', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60, 'compartimenten' => ['OW'], 'grootheden' => ['T', 'WATHTE', 'WINDSHD', 'WINDRTG']],
            ]);

        $buienradarClient->expects($this->once())
            ->method('fetchStations')
            ->willReturn(null);

        $buienradarStationRepository->expects($this->never())
            ->method('saveAll');

        $useCase = new RefreshLocations($locationRepository, $rwsClient, $buienradarStationRepository, $buienradarClient);
        $result = $useCase->execute();

        $this->assertIsArray($result['locations']);
        $this->assertSame(1, $result['locations']['imported']);
        $this->assertSame(-1, $result['stations']);
    }
}
