<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Service\NearestRwsLocationFinder;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Infrastructure\Service\LocationBlacklist;

final class NearestRwsLocationFinderTest extends TestCase
{
    private NearestRwsLocationFinder $finder;
    private LocationBlacklist $blacklist;
    private string $testProjectDir;

    protected function setUp(): void
    {
        // Create a temporary project directory with data/blacklist.txt
        $this->testProjectDir = sys_get_temp_dir().'/test-project-'.uniqid();
        mkdir($this->testProjectDir.'/data', 0777, true);
        file_put_contents($this->testProjectDir.'/data/blacklist.txt', '');

        $this->blacklist = new LocationBlacklist($this->testProjectDir);
        $this->finder = new NearestRwsLocationFinder($this->blacklist);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testProjectDir)) {
            if (file_exists($this->testProjectDir.'/data/blacklist.txt')) {
                unlink($this->testProjectDir.'/data/blacklist.txt');
            }
            if (is_dir($this->testProjectDir.'/data')) {
                rmdir($this->testProjectDir.'/data');
            }
            rmdir($this->testProjectDir);
        }
    }

    private function createBlacklistWithLocations(array $blacklistedIds): LocationBlacklist
    {
        $projectDir = sys_get_temp_dir().'/test-project-'.uniqid();
        mkdir($projectDir.'/data', 0777, true);
        file_put_contents($projectDir.'/data/blacklist.txt', implode("\n", $blacklistedIds));
        $blacklist = new LocationBlacklist($projectDir);

        // Clean up
        unlink($projectDir.'/data/blacklist.txt');
        rmdir($projectDir.'/data');
        rmdir($projectDir);

        return $blacklist;
    }

    public function testFindsNearestLocationWithCapability(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $nearbyLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['WATHTE', 'Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $farLocation = new RwsLocation(
            'hoekvanholland',
            'Hoek van Holland',
            51.98,
            4.12,
            [],
            ['WATHTE', 'Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $nearbyLocation, $farLocation];

        // Act
        $result = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($nearbyLocation, $result['location']);
        $this->assertIsFloat($result['distanceKm']);
        $this->assertGreaterThan(0, $result['distanceKm']);
    }

    public function testReturnsNullWhenNoLocationHasCapability(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $otherLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['WATHTE', 'T'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $otherLocation];

        // Act
        $result = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNull($result);
    }

    public function testSkipsSameLocation(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE', 'Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $nearbyLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['WATHTE', 'Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $nearbyLocation];

        // Act
        $result = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('breskens', $result['location']->getId());
    }

    public function testSkipsBlacklistedLocations(): void
    {
        // Arrange
        $blacklist = $this->createBlacklistWithLocations(['blacklisted']);
        $finder = new NearestRwsLocationFinder($blacklist);

        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $blacklistedLocation = new RwsLocation(
            'blacklisted',
            'Blacklisted Station',
            51.40,
            3.55,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $validLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.45,
            3.65,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $blacklistedLocation, $validLocation];

        // Act
        $result = $finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('breskens', $result['location']->getId());
    }

    public function testOnlyMatchesLocationsWithSameWaterBodyType(): void
    {
        // Arrange
        $seaLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $riverLocation = new RwsLocation(
            'lobith',
            'Lobith',
            51.45,
            3.61,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_RIVER
        );

        $anotherSeaLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$seaLocation, $riverLocation, $anotherSeaLocation];

        // Act
        $result = $this->finder->findNearest($seaLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('breskens', $result['location']->getId());
        $this->assertSame(RwsLocation::WATER_TYPE_SEA, $result['location']->getWaterBodyType());
    }

    public function testSkipsUnknownWaterBodyTypes(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_UNKNOWN
        );

        $unknownLocation = new RwsLocation(
            'nearby',
            'Nearby Station',
            51.40,
            3.55,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_UNKNOWN
        );

        $allLocations = [$sourceLocation, $unknownLocation];

        // Act
        $result = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNull($result);
    }

    public function testDistanceIsRoundedToTwoDecimals(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $nearbyLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $nearbyLocation];

        // Act
        $result = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame(round($result['distanceKm'], 2), $result['distanceKm']);
    }

    public function testReturnsNullWhenAllLocationsAreTooFarAway(): void
    {
        // Arrange - locations more than 20km apart
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $farLocation = new RwsLocation(
            'rotterdam',
            'Rotterdam',
            51.90,
            4.47,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $farLocation];

        // Act
        $result = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNull($result);
    }

    public function testReturnsNullForEmptyLocationArray(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        // Act
        $result = $this->finder->findNearest($sourceLocation, [], 'Hm0');

        // Assert
        $this->assertNull($result);
    }

    public function testFindNearestCandidatesReturnsMultipleLocationsSortedByDistance(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $nearLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $mediumLocation = new RwsLocation(
            'terneuzen',
            'Terneuzen',
            51.35,
            3.81,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $mediumFarLocation = new RwsLocation(
            'hansweert',
            'Hansweert',
            51.44,
            3.85,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $mediumFarLocation, $mediumLocation, $nearLocation];

        // Act
        $results = $this->finder->findNearestCandidates($sourceLocation, $allLocations, 'Hm0', 3);

        // Assert
        $this->assertCount(3, $results);
        $this->assertSame('breskens', $results[0]['location']->getId());
        $this->assertSame('hansweert', $results[1]['location']->getId());
        $this->assertSame('terneuzen', $results[2]['location']->getId());
        // Verify distances are sorted in ascending order
        $this->assertLessThan($results[1]['distanceKm'], $results[0]['distanceKm']);
        $this->assertLessThan($results[2]['distanceKm'], $results[1]['distanceKm']);
    }

    public function testFindNearestCandidatesRespectsLimit(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $locations = [];
        for ($i = 1; $i <= 5; ++$i) {
            $locations[] = new RwsLocation(
                "location{$i}",
                "Location {$i}",
                51.44 + ($i * 0.01),
                3.60 + ($i * 0.01),
                [],
                ['Hm0'],
                RwsLocation::WATER_TYPE_SEA
            );
        }

        $allLocations = array_merge([$sourceLocation], $locations);

        // Act
        $results = $this->finder->findNearestCandidates($sourceLocation, $allLocations, 'Hm0', 2);

        // Assert
        $this->assertCount(2, $results);
    }

    public function testFindNearestCandidatesDefaultsToFiveCandidates(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $locations = [];
        for ($i = 1; $i <= 10; ++$i) {
            $locations[] = new RwsLocation(
                "location{$i}",
                "Location {$i}",
                51.44 + ($i * 0.01),
                3.60 + ($i * 0.01),
                [],
                ['Hm0'],
                RwsLocation::WATER_TYPE_SEA
            );
        }

        $allLocations = array_merge([$sourceLocation], $locations);

        // Act
        $results = $this->finder->findNearestCandidates($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertCount(5, $results);
    }

    public function testFindNearestCandidatesReturnsEmptyArrayWhenNoMatch(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $otherLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['T'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $otherLocation];

        // Act
        $results = $this->finder->findNearestCandidates($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertEmpty($results);
    }

    public function testFindNearestCandidatesSkipsLocationsBeyondMaximumDistance(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $nearLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        // Location far away (> 20km)
        $farLocation = new RwsLocation(
            'rotterdam',
            'Rotterdam',
            51.90,
            4.47,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $nearLocation, $farLocation];

        // Act
        $results = $this->finder->findNearestCandidates($sourceLocation, $allLocations, 'Hm0', 10);

        // Assert
        $this->assertCount(1, $results);
        $this->assertSame('breskens', $results[0]['location']->getId());
    }

    public function testFindNearestHandlesLocationAtExactSameCoordinates(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        // Different location at same coordinates
        $sameCoordinates = new RwsLocation(
            'vlissingen-alt',
            'Vlissingen Alternative',
            51.44,
            3.60,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $sameCoordinates];

        // Act
        $result = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('vlissingen-alt', $result['location']->getId());
        $this->assertSame(0.0, $result['distanceKm']);
    }

    public function testFindNearestCandidatesFiltersMultipleCriteria(): void
    {
        // Arrange - test combination of filters: same location, blacklist, water type, capability
        $blacklist = $this->createBlacklistWithLocations(['blacklisted']);
        $finder = new NearestRwsLocationFinder($blacklist);

        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $validLocation = new RwsLocation(
            'valid',
            'Valid Station',
            51.40,
            3.55,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $blacklistedLocation = new RwsLocation(
            'blacklisted',
            'Blacklisted',
            51.41,
            3.56,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $wrongWaterType = new RwsLocation(
            'wrong-water',
            'Wrong Water Type',
            51.42,
            3.57,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_RIVER
        );

        $noCapability = new RwsLocation(
            'no-capability',
            'No Capability',
            51.43,
            3.58,
            [],
            ['T'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [
            $sourceLocation,
            $validLocation,
            $blacklistedLocation,
            $wrongWaterType,
            $noCapability,
        ];

        // Act
        $results = $finder->findNearestCandidates($sourceLocation, $allLocations, 'Hm0', 5);

        // Assert
        $this->assertCount(1, $results);
        $this->assertSame('valid', $results[0]['location']->getId());
    }

    public function testFindNearestUsesFirstCandidateFromFindNearestCandidates(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $nearLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $farLocation = new RwsLocation(
            'terneuzen',
            'Terneuzen',
            51.33,
            3.83,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $farLocation, $nearLocation];

        // Act
        $singleResult = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');
        $multipleResults = $this->finder->findNearestCandidates($sourceLocation, $allLocations, 'Hm0', 5);

        // Assert
        $this->assertNotNull($singleResult);
        $this->assertSame($multipleResults[0]['location']->getId(), $singleResult['location']->getId());
        $this->assertSame($multipleResults[0]['distanceKm'], $singleResult['distanceKm']);
    }

    public function testDistanceCalculationAccuracy(): void
    {
        // Arrange - Known distance between Amsterdam and Rotterdam is approximately 58km
        $amsterdam = new RwsLocation(
            'amsterdam',
            'Amsterdam',
            52.37,
            4.89,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_RIVER
        );

        $rotterdam = new RwsLocation(
            'rotterdam',
            'Rotterdam',
            51.92,
            4.48,
            [],
            ['Hm0'],
            RwsLocation::WATER_TYPE_RIVER
        );

        $allLocations = [$amsterdam, $rotterdam];

        // Act
        $result = $this->finder->findNearest($amsterdam, $allLocations, 'Hm0');

        // Assert
        $this->assertNull($result); // Distance is > 20km, so should be filtered out
    }

    public function testHandlesLocationsWithMultipleCapabilities(): void
    {
        // Arrange
        $sourceLocation = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.44,
            3.60,
            [],
            ['WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $multiCapabilityLocation = new RwsLocation(
            'breskens',
            'Breskens',
            51.40,
            3.55,
            [],
            ['WATHTE', 'Hm0', 'Tm02', 'Th3', 'T'],
            RwsLocation::WATER_TYPE_SEA
        );

        $allLocations = [$sourceLocation, $multiCapabilityLocation];

        // Act
        $result = $this->finder->findNearest($sourceLocation, $allLocations, 'Hm0');

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('breskens', $result['location']->getId());
    }
}
