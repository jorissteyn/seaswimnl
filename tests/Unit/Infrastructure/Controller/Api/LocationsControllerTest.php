<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Controller\Api;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Infrastructure\Controller\Api\LocationsController;
use Seaswim\Infrastructure\Service\LocationBlacklist;
use Symfony\Component\HttpFoundation\JsonResponse;

final class LocationsControllerTest extends TestCase
{
    private RwsLocationRepositoryInterface $locationRepository;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $this->tempDir = sys_get_temp_dir().'/seaswim_controller_test_'.uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir.'/data', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
    }

    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeTempDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createBlacklistFile(string $content): void
    {
        file_put_contents($this->tempDir.'/data/blacklist.txt', $content);
    }

    private function createController(): LocationsController
    {
        $blacklist = new LocationBlacklist($this->tempDir);
        $controller = new LocationsController($this->locationRepository, $blacklist);

        // Mock the container for AbstractController::json() method
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')->willReturn(false); // No serializer, will use JsonResponse default encoding

        // Use reflection to set the container property
        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $property->setValue($controller, $container);

        return $controller;
    }

    public function testListReturnsJsonResponseWithAllNonBlacklistedLocations(): void
    {
        // Arrange
        $this->createBlacklistFile('EURPFM');

        $location1 = new RwsLocation(
            id: 'HOEKVHLD',
            name: 'Hoek van Holland',
            latitude: 51.9775,
            longitude: 4.1225,
        );

        $location2 = new RwsLocation(
            id: 'EURPFM',
            name: 'Europlatform',
            latitude: 51.9989,
            longitude: 3.2758,
        );

        $location3 = new RwsLocation(
            id: 'SCHEVNGN',
            name: 'Scheveningen',
            latitude: 52.1033,
            longitude: 4.2633,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2, $location3]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        // Verify first location (HOEKVHLD)
        $this->assertSame('HOEKVHLD', $data[0]['id']);
        $this->assertSame('Hoek van Holland', $data[0]['name']);
        $this->assertSame(51.9775, $data[0]['latitude']);
        $this->assertSame(4.1225, $data[0]['longitude']);

        // Verify second location (SCHEVNGN) - EURPFM should be filtered out
        $this->assertSame('SCHEVNGN', $data[1]['id']);
        $this->assertSame('Scheveningen', $data[1]['name']);
        $this->assertSame(52.1033, $data[1]['latitude']);
        $this->assertSame(4.2633, $data[1]['longitude']);
    }

    public function testListReturnsEmptyArrayWhenNoLocationsExist(): void
    {
        // Arrange
        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function testListReturnsEmptyArrayWhenAllLocationsAreBlacklisted(): void
    {
        // Arrange
        $this->createBlacklistFile("BLACKLISTED1\nBLACKLISTED2");

        $location1 = new RwsLocation(
            id: 'BLACKLISTED1',
            name: 'Blacklisted Location 1',
            latitude: 51.0,
            longitude: 4.0,
        );

        $location2 = new RwsLocation(
            id: 'BLACKLISTED2',
            name: 'Blacklisted Location 2',
            latitude: 52.0,
            longitude: 5.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function testListFiltersOutBlacklistedLocationsCorrectly(): void
    {
        // Arrange
        $this->createBlacklistFile("LOC2\nLOC4");

        $location1 = new RwsLocation(id: 'LOC1', name: 'Location 1', latitude: 51.0, longitude: 4.0);
        $location2 = new RwsLocation(id: 'LOC2', name: 'Location 2', latitude: 52.0, longitude: 5.0);
        $location3 = new RwsLocation(id: 'LOC3', name: 'Location 3', latitude: 53.0, longitude: 6.0);
        $location4 = new RwsLocation(id: 'LOC4', name: 'Location 4', latitude: 54.0, longitude: 7.0);

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2, $location3, $location4]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame('LOC1', $data[0]['id']);
        $this->assertSame('LOC3', $data[1]['id']);
    }

    public function testListReturnsOnlyRequiredFieldsInResponse(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'TEST123',
            name: 'Test Location',
            latitude: 51.5,
            longitude: 4.5,
            compartimenten: ['OW', 'NVT'],
            grootheden: ['T', 'WATHTE', 'Hm0'],
            waterBodyType: RwsLocation::WATER_TYPE_SEA,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);

        // Verify only the required fields are present
        $locationData = $data[0];
        $this->assertArrayHasKey('id', $locationData);
        $this->assertArrayHasKey('name', $locationData);
        $this->assertArrayHasKey('latitude', $locationData);
        $this->assertArrayHasKey('longitude', $locationData);

        // Verify optional fields are not exposed
        $this->assertArrayNotHasKey('compartimenten', $locationData);
        $this->assertArrayNotHasKey('grootheden', $locationData);
        $this->assertArrayNotHasKey('waterBodyType', $locationData);

        // Verify values
        $this->assertSame('TEST123', $locationData['id']);
        $this->assertSame('Test Location', $locationData['name']);
        $this->assertSame(51.5, $locationData['latitude']);
        $this->assertSame(4.5, $locationData['longitude']);
    }

    public function testListHandlesLocationsWithNegativeCoordinates(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'NEG_COORDS',
            name: 'Negative Coordinates',
            latitude: -51.5,
            longitude: -4.5,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame(-51.5, $data[0]['latitude']);
        $this->assertSame(-4.5, $data[0]['longitude']);
    }

    public function testListHandlesLocationsWithZeroCoordinates(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'ZERO_COORDS',
            name: 'Zero Coordinates',
            latitude: 0.0,
            longitude: 0.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        // JSON encodes 0.0 as integer 0
        $this->assertEquals(0, $data[0]['latitude']);
        $this->assertEquals(0, $data[0]['longitude']);
    }

    public function testListHandlesLocationsWithVeryPreciseCoordinates(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'PRECISE',
            name: 'Precise Location',
            latitude: 51.123456789,
            longitude: 4.987654321,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame(51.123456789, $data[0]['latitude']);
        $this->assertSame(4.987654321, $data[0]['longitude']);
    }

    public function testListHandlesLocationsWithSpecialCharactersInName(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'SPECIAL',
            name: "Location with 'quotes' & \"special\" chars: <test>",
            latitude: 51.0,
            longitude: 4.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        // JsonResponse should properly encode special characters
        $this->assertSame("Location with 'quotes' & \"special\" chars: <test>", $data[0]['name']);
    }

    public function testListHandlesLocationsWithUnicodeCharactersInName(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'UNICODE',
            name: 'Scheveningen — café Bülow',
            latitude: 52.0,
            longitude: 4.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Scheveningen — café Bülow', $data[0]['name']);
    }

    public function testListHandlesEmptyLocationName(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'EMPTY_NAME',
            name: '',
            latitude: 51.0,
            longitude: 4.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('', $data[0]['name']);
    }

    public function testListPreservesLocationOrderExceptForFilteredLocations(): void
    {
        // Arrange
        $this->createBlacklistFile('SECOND');

        $location1 = new RwsLocation(id: 'FIRST', name: 'First', latitude: 51.0, longitude: 4.0);
        $location2 = new RwsLocation(id: 'SECOND', name: 'Second', latitude: 52.0, longitude: 5.0);
        $location3 = new RwsLocation(id: 'THIRD', name: 'Third', latitude: 53.0, longitude: 6.0);

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2, $location3]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame('FIRST', $data[0]['id']);
        $this->assertSame('THIRD', $data[1]['id']);
    }

    public function testListCallsRepositoryExactlyOnce(): void
    {
        // Arrange
        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $controller = $this->createController();

        // Act
        $controller->list();

        // Assert - expectations verified by mock
    }

    public function testListHandlesLargeNumberOfLocations(): void
    {
        // Arrange
        $locations = [];
        for ($i = 0; $i < 100; ++$i) {
            $locations[] = new RwsLocation(
                id: "LOC{$i}",
                name: "Location {$i}",
                latitude: 51.0 + $i * 0.01,
                longitude: 4.0 + $i * 0.01,
            );
        }

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($locations);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(100, $data);
        $this->assertSame('LOC0', $data[0]['id']);
        $this->assertSame('LOC99', $data[99]['id']);
    }

    public function testListReturnsJsonWithCorrectContentType(): void
    {
        // Arrange
        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testListHandlesMixOfBlacklistedAndNonBlacklistedLocations(): void
    {
        // Arrange
        $this->createBlacklistFile("BAD1\nBAD2");

        $locations = [
            new RwsLocation(id: 'GOOD1', name: 'Good 1', latitude: 51.0, longitude: 4.0),
            new RwsLocation(id: 'BAD1', name: 'Bad 1', latitude: 51.1, longitude: 4.1),
            new RwsLocation(id: 'GOOD2', name: 'Good 2', latitude: 51.2, longitude: 4.2),
            new RwsLocation(id: 'BAD2', name: 'Bad 2', latitude: 51.3, longitude: 4.3),
            new RwsLocation(id: 'GOOD3', name: 'Good 3', latitude: 51.4, longitude: 4.4),
        ];

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($locations);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(3, $data);
        $this->assertSame('GOOD1', $data[0]['id']);
        $this->assertSame('GOOD2', $data[1]['id']);
        $this->assertSame('GOOD3', $data[2]['id']);
    }

    public function testListReturnsNewArrayWithReindexedKeys(): void
    {
        // Arrange
        $this->createBlacklistFile("LOC1\nLOC3");

        $location1 = new RwsLocation(id: 'LOC1', name: 'Location 1', latitude: 51.0, longitude: 4.0);
        $location2 = new RwsLocation(id: 'LOC2', name: 'Location 2', latitude: 52.0, longitude: 5.0);
        $location3 = new RwsLocation(id: 'LOC3', name: 'Location 3', latitude: 53.0, longitude: 6.0);

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2, $location3]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        // array_values should reindex the array, so the remaining location should be at index 0
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayNotHasKey(1, $data);
        $this->assertSame('LOC2', $data[0]['id']);
    }

    public function testListHandlesSingleLocation(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'SINGLE',
            name: 'Single Location',
            latitude: 51.5,
            longitude: 4.5,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('SINGLE', $data[0]['id']);
        $this->assertSame('Single Location', $data[0]['name']);
    }

    public function testListReturnsValidJsonStructure(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'TEST',
            name: 'Test Location',
            latitude: 51.0,
            longitude: 4.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $content = $response->getContent();
        $this->assertJson($content);

        // Verify it decodes without errors
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testListDoesNotModifyOriginalRepositoryData(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'IMMUTABLE',
            name: 'Immutable Location',
            latitude: 51.0,
            longitude: 4.0,
        );

        $locations = [$location];
        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($locations);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        // The original location object should not be modified
        $this->assertSame('IMMUTABLE', $location->getId());
        $this->assertSame('Immutable Location', $location->getName());

        // The response should contain the data
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('IMMUTABLE', $data[0]['id']);
    }

    public function testListHandlesLocationsWithVeryLongNames(): void
    {
        // Arrange
        $longName = str_repeat('A very long location name ', 50);
        $location = new RwsLocation(
            id: 'LONG',
            name: $longName,
            latitude: 51.0,
            longitude: 4.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame($longName, $data[0]['name']);
    }

    public function testListHandlesLocationIdWithSpecialCharacters(): void
    {
        // Arrange
        $location = new RwsLocation(
            id: 'ID-WITH-DASHES_AND_UNDERSCORES.123',
            name: 'Special ID Location',
            latitude: 51.0,
            longitude: 4.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('ID-WITH-DASHES_AND_UNDERSCORES.123', $data[0]['id']);
    }

    public function testListHandlesExtremeLatitudeLongitudeValues(): void
    {
        // Arrange
        $location1 = new RwsLocation(id: 'NORTH', name: 'North Pole', latitude: 90.0, longitude: 0.0);
        $location2 = new RwsLocation(id: 'SOUTH', name: 'South Pole', latitude: -90.0, longitude: 0.0);
        $location3 = new RwsLocation(id: 'DATE_LINE', name: 'Date Line', latitude: 0.0, longitude: 180.0);
        $location4 = new RwsLocation(id: 'PRIME', name: 'Prime Meridian', latitude: 0.0, longitude: -180.0);

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2, $location3, $location4]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(4, $data);
        // JSON encodes whole numbers like 90.0 as integers
        $this->assertEquals(90, $data[0]['latitude']);
        $this->assertEquals(-90, $data[1]['latitude']);
        $this->assertEquals(180, $data[2]['longitude']);
        $this->assertEquals(-180, $data[3]['longitude']);
    }

    public function testListHandlesBlacklistWithComments(): void
    {
        // Arrange
        $this->createBlacklistFile("# Comment\nLOC2\n# Another comment\nLOC4");

        $location1 = new RwsLocation(id: 'LOC1', name: 'Location 1', latitude: 51.0, longitude: 4.0);
        $location2 = new RwsLocation(id: 'LOC2', name: 'Location 2', latitude: 52.0, longitude: 5.0);
        $location3 = new RwsLocation(id: 'LOC3', name: 'Location 3', latitude: 53.0, longitude: 6.0);
        $location4 = new RwsLocation(id: 'LOC4', name: 'Location 4', latitude: 54.0, longitude: 7.0);

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2, $location3, $location4]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame('LOC1', $data[0]['id']);
        $this->assertSame('LOC3', $data[1]['id']);
    }

    public function testListHandlesNoBlacklistFile(): void
    {
        // Arrange - don't create a blacklist file
        $location = new RwsLocation(
            id: 'LOC1',
            name: 'Location 1',
            latitude: 51.0,
            longitude: 4.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('LOC1', $data[0]['id']);
    }

    public function testListHandlesEmptyBlacklistFile(): void
    {
        // Arrange
        $this->createBlacklistFile('');

        $location = new RwsLocation(
            id: 'LOC1',
            name: 'Location 1',
            latitude: 51.0,
            longitude: 4.0,
        );

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $controller = $this->createController();

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('LOC1', $data[0]['id']);
    }
}
