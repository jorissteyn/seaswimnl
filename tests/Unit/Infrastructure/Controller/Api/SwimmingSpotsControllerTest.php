<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Controller\Api;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Infrastructure\Controller\Api\SwimmingSpotsController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SwimmingSpotsControllerTest extends TestCase
{
    private function createSwimmingSpot(
        string $id = 'test-spot',
        string $name = 'Test Beach',
        float $latitude = 52.1234,
        float $longitude = 4.5678
    ): SwimmingSpot {
        return new SwimmingSpot($id, $name, $latitude, $longitude);
    }

    private function createRepositoryMock(): SwimmingSpotRepositoryInterface
    {
        return $this->createMock(SwimmingSpotRepositoryInterface::class);
    }

    private function setupController(SwimmingSpotRepositoryInterface $repository): SwimmingSpotsController
    {
        $controller = new SwimmingSpotsController($repository);

        // Mock the container that AbstractController requires
        // When json() method checks container->has('serializer'), return false
        // This makes the controller use json_encode instead of the serializer
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with('serializer')
            ->willReturn(false);

        // Use reflection to set the container property since it's protected
        $reflection = new \ReflectionClass($controller);
        $containerProperty = $reflection->getParentClass()->getProperty('container');
        $containerProperty->setValue($controller, $container);

        return $controller;
    }

    public function testListReturnsEmptyArrayWhenNoSpotsExist(): void
    {
        // Arrange
        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('[]', $response->getContent());
    }

    public function testListReturnsSingleSwimmingSpotWithCorrectStructure(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            'scheveningen-beach',
            'Scheveningen Beach',
            52.1050,
            4.2750
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);

        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('latitude', $data[0]);
        $this->assertArrayHasKey('longitude', $data[0]);

        $this->assertSame('scheveningen-beach', $data[0]['id']);
        $this->assertSame('Scheveningen Beach', $data[0]['name']);
        $this->assertSame(52.1050, $data[0]['latitude']);
        $this->assertSame(4.2750, $data[0]['longitude']);
    }

    public function testListReturnsMultipleSwimmingSpotsInCorrectOrder(): void
    {
        // Arrange
        $spot1 = $this->createSwimmingSpot('spot-1', 'Beach One', 52.0, 4.0);
        $spot2 = $this->createSwimmingSpot('spot-2', 'Beach Two', 53.0, 5.0);
        $spot3 = $this->createSwimmingSpot('spot-3', 'Beach Three', 54.0, 6.0);

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot1, $spot2, $spot3]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(3, $data);

        $this->assertSame('spot-1', $data[0]['id']);
        $this->assertSame('spot-2', $data[1]['id']);
        $this->assertSame('spot-3', $data[2]['id']);
    }

    public function testListCallsRepositoryFindAllOnce(): void
    {
        // Arrange
        $repository = $this->createRepositoryMock();
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $controller = $this->setupController($repository);

        // Act
        $controller->list();

        // Assert - expectations verified by mock
    }

    public function testListOnlyIncludesExpectedFieldsInResponse(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            'test-spot',
            'Test Spot',
            52.5,
            4.5
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertCount(4, $data[0], 'Response should contain exactly 4 fields');

        $expectedKeys = ['id', 'name', 'latitude', 'longitude'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data[0]);
        }
    }

    public function testListHandlesSpotWithNegativeCoordinates(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            'southern-beach',
            'Southern Beach',
            -33.8688,
            -151.2093
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertSame(-33.8688, $data[0]['latitude']);
        $this->assertSame(-151.2093, $data[0]['longitude']);
    }

    public function testListHandlesSpotWithZeroCoordinates(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            'equator-beach',
            'Equator Beach',
            0.0,
            0.0
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        // JSON encodes 0.0 as 0 (integer), so we use assertEquals instead of assertSame
        $this->assertEquals(0.0, $data[0]['latitude']);
        $this->assertEquals(0.0, $data[0]['longitude']);
    }

    public function testListHandlesSpotWithVeryPreciseCoordinates(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            'precise-beach',
            'Precise Beach',
            52.123456789,
            4.987654321
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertSame(52.123456789, $data[0]['latitude']);
        $this->assertSame(4.987654321, $data[0]['longitude']);
    }

    public function testListHandlesSpotWithSpecialCharactersInName(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            'special-beach',
            'Beach with "quotes" & ampersands <tags>',
            52.0,
            4.0
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        // JSON encoding should properly escape special characters
        $this->assertSame('Beach with "quotes" & ampersands <tags>', $data[0]['name']);
    }

    public function testListHandlesSpotWithUnicodeCharactersInName(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            'unicode-beach',
            'Plage française avec émojis 🏖️🌊',
            52.0,
            4.0
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Plage française avec émojis 🏖️🌊', $data[0]['name']);
    }

    public function testListHandlesSpotWithEmptyStringId(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            '',
            'No ID Beach',
            52.0,
            4.0
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertSame('', $data[0]['id']);
    }

    public function testListHandlesSpotWithVeryLongName(): void
    {
        // Arrange
        $longName = str_repeat('Very Long Beach Name ', 50);
        $spot = $this->createSwimmingSpot(
            'long-name-beach',
            $longName,
            52.0,
            4.0
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertSame($longName, $data[0]['name']);
    }

    public function testListHandlesBoundaryCoordinateValues(): void
    {
        // Arrange - test maximum valid latitude/longitude values
        $spot1 = $this->createSwimmingSpot('north-pole', 'North Pole', 90.0, 180.0);
        $spot2 = $this->createSwimmingSpot('south-pole', 'South Pole', -90.0, -180.0);

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot1, $spot2]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);
        // JSON may encode whole number floats as integers, so use assertEquals
        $this->assertEquals(90.0, $data[0]['latitude']);
        $this->assertEquals(180.0, $data[0]['longitude']);
        $this->assertEquals(-90.0, $data[1]['latitude']);
        $this->assertEquals(-180.0, $data[1]['longitude']);
    }

    public function testListReturnsValidJsonResponse(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot();

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        // Verify it's valid JSON
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Response should be valid JSON');
    }

    public function testListResponseHasCorrectHttpStatusCode(): void
    {
        // Arrange
        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->isSuccessful());
    }

    public function testListCanBeCalledMultipleTimes(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot();

        $repository = $this->createRepositoryMock();
        $repository->expects($this->exactly(3))
            ->method('findAll')
            ->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response1 = $controller->list();
        $response2 = $controller->list();
        $response3 = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response1);
        $this->assertInstanceOf(JsonResponse::class, $response2);
        $this->assertInstanceOf(JsonResponse::class, $response3);

        $data1 = json_decode($response1->getContent(), true);
        $data2 = json_decode($response2->getContent(), true);
        $data3 = json_decode($response3->getContent(), true);

        $this->assertEquals($data1, $data2);
        $this->assertEquals($data2, $data3);
    }

    public function testListMapsAllSwimmingSpotFieldsCorrectly(): void
    {
        // Arrange
        $spots = [
            $this->createSwimmingSpot('id-1', 'Name 1', 10.5, 20.5),
            $this->createSwimmingSpot('id-2', 'Name 2', 30.5, 40.5),
            $this->createSwimmingSpot('id-3', 'Name 3', 50.5, 60.5),
        ];

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn($spots);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);

        for ($i = 0; $i < 3; ++$i) {
            $this->assertSame('id-'.($i + 1), $data[$i]['id']);
            $this->assertSame('Name '.($i + 1), $data[$i]['name']);
            $this->assertSame(10.5 + ($i * 20), $data[$i]['latitude']);
            $this->assertSame(20.5 + ($i * 20), $data[$i]['longitude']);
        }
    }

    public function testListPreservesDataTypesInJsonResponse(): void
    {
        // Arrange
        $spot = $this->createSwimmingSpot(
            'type-test',
            'Type Test Beach',
            52.123,
            4.456
        );

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn([$spot]);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $data = json_decode($response->getContent(), true);

        // Verify data types
        $this->assertIsString($data[0]['id']);
        $this->assertIsString($data[0]['name']);
        $this->assertIsFloat($data[0]['latitude']);
        $this->assertIsFloat($data[0]['longitude']);
    }

    public function testListHandlesLargeNumberOfSpots(): void
    {
        // Arrange
        $spots = [];
        for ($i = 0; $i < 1000; ++$i) {
            $spots[] = $this->createSwimmingSpot(
                "spot-{$i}",
                "Beach {$i}",
                52.0 + ($i * 0.001),
                4.0 + ($i * 0.001)
            );
        }

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn($spots);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1000, $data);
        $this->assertSame('spot-0', $data[0]['id']);
        $this->assertSame('spot-999', $data[999]['id']);
    }

    public function testControllerConstructorAcceptsRepository(): void
    {
        // Arrange
        $repository = $this->createRepositoryMock();

        // Act
        $controller = $this->setupController($repository);

        // Assert
        $this->assertInstanceOf(SwimmingSpotsController::class, $controller);
    }

    public function testListDoesNotModifyRepositoryData(): void
    {
        // Arrange
        $originalSpots = [
            $this->createSwimmingSpot('spot-1', 'Beach 1', 52.0, 4.0),
            $this->createSwimmingSpot('spot-2', 'Beach 2', 53.0, 5.0),
        ];

        $repository = $this->createRepositoryMock();
        $repository->method('findAll')->willReturn($originalSpots);

        $controller = $this->setupController($repository);

        // Act
        $response = $controller->list();

        // Assert - verify original data structure is intact
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertCount(2, $originalSpots, 'Original array should not be modified');
    }
}
