<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Application\UseCase;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Application\UseCase\RefreshLocations;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final class RefreshLocationsTest extends TestCase
{
    public function testExecuteRefreshesLocations(): void
    {
        $repository = $this->createMock(LocationRepositoryInterface::class);
        $client = $this->createMock(RwsHttpClientInterface::class);

        $client->expects($this->once())
            ->method('fetchLocations')
            ->willReturn([
                ['code' => 'vlissingen', 'name' => 'Vlissingen', 'latitude' => 51.44, 'longitude' => 3.60],
                ['code' => 'hoekvanholland', 'name' => 'Hoek van Holland', 'latitude' => 51.98, 'longitude' => 4.12],
            ]);

        $repository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(fn ($locations) => 2 === \count($locations)));

        $useCase = new RefreshLocations($repository, $client);
        $count = $useCase->execute();

        $this->assertSame(2, $count);
    }

    public function testExecuteReturnsMinusOneOnApiFailure(): void
    {
        $repository = $this->createMock(LocationRepositoryInterface::class);
        $client = $this->createMock(RwsHttpClientInterface::class);

        $client->expects($this->once())
            ->method('fetchLocations')
            ->willReturn(null);

        $repository->expects($this->never())
            ->method('saveAll');

        $useCase = new RefreshLocations($repository, $client);
        $count = $useCase->execute();

        $this->assertSame(-1, $count);
    }
}
