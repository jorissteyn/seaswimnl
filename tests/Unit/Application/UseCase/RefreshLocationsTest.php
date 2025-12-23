<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Application\UseCase;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\KnmiStationRepositoryInterface;
use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Application\UseCase\RefreshLocations;
use Seaswim\Infrastructure\ExternalApi\Client\KnmiHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final class RefreshLocationsTest extends TestCase
{
    public function testExecuteRefreshesLocationsAndStations(): void
    {
        $locationRepository = $this->createMock(LocationRepositoryInterface::class);
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $knmiStationRepository = $this->createMock(KnmiStationRepositoryInterface::class);
        $knmiClient = $this->createMock(KnmiHttpClientInterface::class);

        $rwsClient->expects($this->once())
            ->method('fetchLocations')
            ->willReturn([
                ['code' => 'vlissingen', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60],
                ['code' => 'hoekvanholland', 'name' => 'Hoek van Holland', 'latitude' => 51.98, 'longitude' => 4.12],
            ]);

        $knmiClient->expects($this->once())
            ->method('fetchStations')
            ->willReturn([
                ['code' => '310', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60],
                ['code' => '330', 'name' => 'Hoek van Holland', 'latitude' => 51.98, 'longitude' => 4.12],
            ]);

        $locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(fn ($locations) => 2 === \count($locations)));

        $knmiStationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(fn ($stations) => 2 === \count($stations)));

        $useCase = new RefreshLocations($locationRepository, $rwsClient, $knmiStationRepository, $knmiClient);
        $result = $useCase->execute();

        $this->assertSame(2, $result['locations']);
        $this->assertSame(2, $result['stations']);
    }

    public function testExecuteReturnsMinusOneOnRwsApiFailure(): void
    {
        $locationRepository = $this->createMock(LocationRepositoryInterface::class);
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $knmiStationRepository = $this->createMock(KnmiStationRepositoryInterface::class);
        $knmiClient = $this->createMock(KnmiHttpClientInterface::class);

        $rwsClient->expects($this->once())
            ->method('fetchLocations')
            ->willReturn(null);

        $knmiClient->expects($this->once())
            ->method('fetchStations')
            ->willReturn([
                ['code' => '310', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60],
            ]);

        $locationRepository->expects($this->never())
            ->method('saveAll');

        $useCase = new RefreshLocations($locationRepository, $rwsClient, $knmiStationRepository, $knmiClient);
        $result = $useCase->execute();

        $this->assertSame(-1, $result['locations']);
        $this->assertSame(1, $result['stations']);
    }

    public function testExecuteReturnsMinusOneOnKnmiApiFailure(): void
    {
        $locationRepository = $this->createMock(LocationRepositoryInterface::class);
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $knmiStationRepository = $this->createMock(KnmiStationRepositoryInterface::class);
        $knmiClient = $this->createMock(KnmiHttpClientInterface::class);

        $rwsClient->expects($this->once())
            ->method('fetchLocations')
            ->willReturn([
                ['code' => 'vlissingen', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60],
            ]);

        $knmiClient->expects($this->once())
            ->method('fetchStations')
            ->willReturn(null);

        $knmiStationRepository->expects($this->never())
            ->method('saveAll');

        $useCase = new RefreshLocations($locationRepository, $rwsClient, $knmiStationRepository, $knmiClient);
        $result = $useCase->execute();

        $this->assertSame(1, $result['locations']);
        $this->assertSame(-1, $result['stations']);
    }
}
