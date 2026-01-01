<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\Service\NearestRwsLocationMatcher;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Infrastructure\Service\LocationBlacklist;

final class NearestRwsLocationMatcherTest extends TestCase
{
    private NearestRwsLocationMatcher $matcher;
    private RwsLocationRepositoryInterface $repository;
    private LocationBlacklist $blacklist;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RwsLocationRepositoryInterface::class);

        // Create temporary directory for blacklist file
        $this->tempDir = sys_get_temp_dir().'/seaswim_test_'.uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir.'/data');

        // Create empty blacklist file
        file_put_contents($this->tempDir.'/data/blacklist.txt', '');

        $this->blacklist = new LocationBlacklist($this->tempDir);
        $this->matcher = new NearestRwsLocationMatcher($this->repository, $this->blacklist);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        if (is_dir($this->tempDir)) {
            if (file_exists($this->tempDir.'/data/blacklist.txt')) {
                unlink($this->tempDir.'/data/blacklist.txt');
            }
            if (is_dir($this->tempDir.'/data')) {
                rmdir($this->tempDir.'/data');
            }
            rmdir($this->tempDir);
        }
    }

    public function testFindsNearestLocation(): void
    {
        // Swimming spot in Vlissingen
        $spot = new SwimmingSpot('vlissingen-strand', 'Vlissingen Strand', 51.45, 3.61);

        $nearLocation = new RwsLocation('vlissingen', 'Vlissingen havenmond', 51.44, 3.60, [], []);
        $farLocation = new RwsLocation('hoekvanholland', 'Hoek van Holland', 51.98, 4.12, [], []);

        $this->repository->method('findAll')->willReturn([
            $farLocation,
            $nearLocation,
        ]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertSame($nearLocation, $result['location']);
        $this->assertLessThan(5, $result['distanceKm']);
    }

    public function testReturnsDistanceInKilometers(): void
    {
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertIsFloat($result['distanceKm']);
        $this->assertGreaterThan(0, $result['distanceKm']);
    }

    public function testFindsClosestOfMultipleLocations(): void
    {
        $spot = new SwimmingSpot('rotterdam-strand', 'Rotterdam Strand', 51.97, 4.11);

        $vlissingenLocation = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60, [], []);
        $rotterdamLocation = new RwsLocation('hoekvanholland', 'Hoek van Holland', 51.98, 4.12, [], []);
        $denhelderLocation = new RwsLocation('denhelder', 'Den Helder', 52.96, 4.78, [], []);

        $this->repository->method('findAll')->willReturn([
            $denhelderLocation,
            $vlissingenLocation,
            $rotterdamLocation,
        ]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertSame($rotterdamLocation, $result['location']);
    }

    public function testReturnsNullWhenNoLocations(): void
    {
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);

        $this->repository->method('findAll')->willReturn([]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNull($result);
    }

    public function testDistanceIsRoundedToOneDecimal(): void
    {
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        // Check that distance is rounded (has at most 1 decimal place)
        $this->assertSame(round($result['distanceKm'], 1), $result['distanceKm']);
    }

    public function testSkipsBlacklistedLocations(): void
    {
        // Add 'vlissingen' to blacklist
        file_put_contents($this->tempDir.'/data/blacklist.txt', "vlissingen\n");
        $this->blacklist = new LocationBlacklist($this->tempDir);
        $this->matcher = new NearestRwsLocationMatcher($this->repository, $this->blacklist);

        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);

        $nearButBlacklisted = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60, [], []);
        // Location within 20km but further than the blacklisted one
        $farButNotBlacklisted = new RwsLocation('breskens', 'Breskens', 51.40, 3.55, [], []);

        $this->repository->method('findAll')->willReturn([
            $nearButBlacklisted,
            $farButNotBlacklisted,
        ]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertSame($farButNotBlacklisted, $result['location']);
    }

    public function testReturnsNullWhenAllLocationsBlacklisted(): void
    {
        // Add both locations to blacklist
        file_put_contents($this->tempDir.'/data/blacklist.txt', "loc1\nloc2\n");
        $this->blacklist = new LocationBlacklist($this->tempDir);
        $this->matcher = new NearestRwsLocationMatcher($this->repository, $this->blacklist);

        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);

        $location1 = new RwsLocation('loc1', 'Location 1', 51.44, 3.60, [], []);
        $location2 = new RwsLocation('loc2', 'Location 2', 51.46, 3.62, [], []);

        $this->repository->method('findAll')->willReturn([
            $location1,
            $location2,
        ]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNull($result);
    }

    public function testSkipsLocationsBeyondMaximumDistance(): void
    {
        // Swimming spot in Vlissingen
        $spot = new SwimmingSpot('vlissingen-strand', 'Vlissingen Strand', 51.45, 3.61);

        // Location very far away (more than 20km)
        $farLocation = new RwsLocation('denhelder', 'Den Helder', 52.96, 4.78, [], []);

        $this->repository->method('findAll')->willReturn([$farLocation]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenAllLocationsBeyondMaximumDistance(): void
    {
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);

        // All locations far away (more than 20km from spot)
        $farLocation1 = new RwsLocation('denhelder', 'Den Helder', 52.96, 4.78, [], []);
        $farLocation2 = new RwsLocation('delfzijl', 'Delfzijl', 53.33, 6.93, [], []);

        $this->repository->method('findAll')->willReturn([
            $farLocation1,
            $farLocation2,
        ]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNull($result);
    }

    public function testReturnsLocationJustWithinMaximumDistance(): void
    {
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);

        // Location approximately 19km away (just within 20km limit)
        $location = new RwsLocation('nearby', 'Nearby Location', 51.60, 3.70, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertSame($location, $result['location']);
        $this->assertLessThanOrEqual(20.0, $result['distanceKm']);
    }

    public function testDistanceCalculationAccuracy(): void
    {
        // Known distance: Vlissingen (51.44, 3.60) to Rotterdam area (51.98, 4.12)
        // Expected distance: approximately 60-70km
        $spot = new SwimmingSpot('vlissingen', 'Vlissingen', 51.44, 3.60);
        $location = new RwsLocation('rotterdam', 'Rotterdam Area', 51.98, 4.12, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        // This location should be too far away (beyond 20km limit)
        $this->assertNull($result);
    }

    public function testHandlesIdenticalCoordinates(): void
    {
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);
        $location = new RwsLocation('exact-same', 'Exact Same Location', 51.45, 3.61, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertSame($location, $result['location']);
        $this->assertEquals(0.0, $result['distanceKm']);
    }

    public function testSelectsFirstLocationWhenMultipleHaveIdenticalDistance(): void
    {
        $spot = new SwimmingSpot('center', 'Center Point', 52.00, 4.00);

        // Two locations equidistant from spot
        $location1 = new RwsLocation('north', 'North Location', 52.01, 4.00, [], []);
        $location2 = new RwsLocation('south', 'South Location', 51.99, 4.00, [], []);

        $this->repository->method('findAll')->willReturn([
            $location1,
            $location2,
        ]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        // Should return the first one encountered with minimum distance
        $this->assertSame($location1, $result['location']);
    }

    public function testHandlesVerySmallDistances(): void
    {
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45000, 3.61000);
        // Location 0.001 degrees away (approximately 100 meters)
        $location = new RwsLocation('very-close', 'Very Close', 51.45100, 3.61000, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertSame($location, $result['location']);
        $this->assertLessThan(1.0, $result['distanceKm']);
    }

    public function testReturnsArrayWithCorrectStructure(): void
    {
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);
        $location = new RwsLocation('vlissingen', 'Vlissingen', 51.44, 3.60, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('location', $result);
        $this->assertArrayHasKey('distanceKm', $result);
        $this->assertInstanceOf(RwsLocation::class, $result['location']);
        $this->assertIsFloat($result['distanceKm']);
    }

    public function testBlacklistIsConsultedForEachLocation(): void
    {
        // Add loc2 to blacklist, ensure it's skipped
        file_put_contents($this->tempDir.'/data/blacklist.txt', "loc2\n");
        $this->blacklist = new LocationBlacklist($this->tempDir);
        $this->matcher = new NearestRwsLocationMatcher($this->repository, $this->blacklist);

        $spot = new SwimmingSpot('test-spot', 'Test Spot', 51.45, 3.61);

        $location1 = new RwsLocation('loc1', 'Location 1', 51.44, 3.60, [], []);
        $location2 = new RwsLocation('loc2', 'Location 2', 51.43, 3.59, [], []); // Closer but blacklisted
        $location3 = new RwsLocation('loc3', 'Location 3', 51.47, 3.63, [], []);

        $this->repository->method('findAll')->willReturn([
            $location1,
            $location2,
            $location3,
        ]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        // Should return loc1, not loc2 (even though loc2 is closer)
        $this->assertSame($location1, $result['location']);
    }

    public function testHandlesNegativeCoordinates(): void
    {
        // Test with southern hemisphere coordinates
        $spot = new SwimmingSpot('southern-spot', 'Southern Spot', -33.86, 151.21);
        $location = new RwsLocation('sydney', 'Sydney Harbor', -33.85, 151.20, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        $this->assertNotNull($result);
        $this->assertSame($location, $result['location']);
        $this->assertGreaterThan(0, $result['distanceKm']);
    }

    public function testHandlesCoordinatesAcrossPrimeMeridian(): void
    {
        // Test with coordinates that cross the prime meridian
        $spot = new SwimmingSpot('london', 'London', 51.51, -0.13);
        $location = new RwsLocation('dover', 'Dover', 51.13, 1.31, [], []);

        $this->repository->method('findAll')->willReturn([$location]);

        $result = $this->matcher->findNearestLocation($spot);

        // Dover is approximately 120km from London, beyond 20km limit
        $this->assertNull($result);
    }
}
