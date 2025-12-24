<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\BuienradarStationRepositoryInterface;
use Seaswim\Domain\Service\BuienradarStationMatcher;
use Seaswim\Domain\ValueObject\BuienradarStation;
use Seaswim\Domain\ValueObject\Location;

final class BuienradarStationMatcherTest extends TestCase
{
    private BuienradarStationMatcher $matcher;
    private BuienradarStationRepositoryInterface $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(BuienradarStationRepositoryInterface::class);
        $this->matcher = new BuienradarStationMatcher($this->repository);
    }

    public function testFindsNearestStation(): void
    {
        $vlissingenStation = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);
        $deBiltStation = new BuienradarStation('6260', 'De Bilt', 52.10, 5.18);

        $this->repository->method('findAll')->willReturn([
            $deBiltStation,
            $vlissingenStation,
        ]);

        // RWS location near Vlissingen
        $location = new Location('vlissingen', 'Vlissingen havenmond', 51.45, 3.61, [], []);

        $result = $this->matcher->findNearestStation($location);

        $this->assertNotNull($result);
        $this->assertSame($vlissingenStation, $result['station']);
        $this->assertLessThan(5, $result['distanceKm']); // Should be very close
    }

    public function testReturnsDistanceInKilometers(): void
    {
        $station = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);

        $this->repository->method('findAll')->willReturn([$station]);

        // Location about 10km away
        $location = new Location('test', 'Test Location', 51.50, 3.70, [], []);

        $result = $this->matcher->findNearestStation($location);

        $this->assertNotNull($result);
        $this->assertIsFloat($result['distanceKm']);
        $this->assertGreaterThan(0, $result['distanceKm']);
    }

    public function testFindsClosestOfMultipleStations(): void
    {
        $vlissingenStation = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);
        $deBiltStation = new BuienradarStation('6260', 'De Bilt', 52.10, 5.18);
        $rotterdamStation = new BuienradarStation('6344', 'Rotterdam', 51.96, 4.45);

        $this->repository->method('findAll')->willReturn([
            $deBiltStation,
            $vlissingenStation,
            $rotterdamStation,
        ]);

        // Location near Rotterdam
        $location = new Location('hoekvanholland', 'Hoek van Holland', 51.98, 4.12, [], []);

        $result = $this->matcher->findNearestStation($location);

        $this->assertNotNull($result);
        $this->assertSame($rotterdamStation, $result['station']);
    }

    public function testReturnsNullWhenNoStations(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $location = new Location('vlissingen', 'Vlissingen', 51.44, 3.60, [], []);

        $result = $this->matcher->findNearestStation($location);

        $this->assertNull($result);
    }

    public function testDistanceIsRoundedToOneDecimal(): void
    {
        $station = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);

        $this->repository->method('findAll')->willReturn([$station]);

        $location = new Location('test', 'Test', 51.50, 3.70, [], []);

        $result = $this->matcher->findNearestStation($location);

        $this->assertNotNull($result);
        // Check that distance is rounded (has at most 1 decimal place)
        $this->assertSame(round($result['distanceKm'], 1), $result['distanceKm']);
    }
}
