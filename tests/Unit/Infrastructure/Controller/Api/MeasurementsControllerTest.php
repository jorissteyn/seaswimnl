<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Controller\Api;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Seaswim\Domain\Service\NearestRwsLocationFinder;
use Seaswim\Domain\Service\NearestRwsLocationMatcher;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Infrastructure\Controller\Api\MeasurementsController;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;
use Seaswim\Infrastructure\Service\LocationBlacklist;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class MeasurementsControllerTest extends TestCase
{
    private function createController(
        RwsHttpClientInterface $rwsClient,
        RwsLocationRepositoryInterface $locationRepository,
        SwimmingSpotRepositoryInterface $swimmingSpotRepository
    ): MeasurementsController {
        $blacklist = new LocationBlacklist('/tmp');
        $matcher = new NearestRwsLocationMatcher($locationRepository, $blacklist);
        $finder = new NearestRwsLocationFinder($blacklist);

        $controller = new MeasurementsController(
            $rwsClient,
            $locationRepository,
            $swimmingSpotRepository,
            $matcher,
            $finder
        );

        // Set up minimal container for json() method
        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->with('serializer')->willReturn($serializer);
        $controller->setContainer($container);

        return $controller;
    }

    public function testGetMeasurementsReturnsNotFoundWhenSwimmingSpotDoesNotExist(): void
    {
        // Arrange
        $swimmingSpotId = 'non-existent-spot';

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with($swimmingSpotId)
            ->willReturn(null);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements($swimmingSpotId);

        // Assert
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Swimming spot not found', $data['error']);
    }

    public function testGetMeasurementsReturnsNotFoundWhenNoRwsLocationFound(): void
    {
        // Arrange
        $swimmingSpotId = 'zandvoort';
        $swimmingSpot = new SwimmingSpot('zandvoort', 'Zandvoort', 52.37, 4.53);

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements($swimmingSpotId);

        // Assert
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No RWS location found near this swimming spot', $data['error']);
    }

    public function testGetMeasurementsReturnsSuccessWithPrimaryLocationMeasurements(): void
    {
        // Arrange
        $swimmingSpotId = 'zandvoort';
        $swimmingSpot = new SwimmingSpot('zandvoort', 'Zandvoort', 52.37, 4.53);
        $primaryLocation = new RwsLocation(
            'IJMDBTHVN',
            'IJmuiden',
            52.46,
            4.56,
            ['OW'],
            ['T', 'WATHTE', 'WINDSHD']
        );

        $rawMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 18.5,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'WATHTE',
                'compartiment' => 'OW',
                'value' => 120,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$primaryLocation]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements($swimmingSpotId);

        // Assert
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('swimmingSpot', $data);
        $this->assertSame('zandvoort', $data['swimmingSpot']['id']);
        $this->assertSame('Zandvoort', $data['swimmingSpot']['name']);

        $this->assertArrayHasKey('measurements', $data);
        $this->assertCount(2, $data['measurements']);

        // Verify first measurement structure
        $firstMeasurement = $data['measurements'][0];
        $this->assertArrayHasKey('grootheid', $firstMeasurement);
        $this->assertArrayHasKey('compartiment', $firstMeasurement);
        $this->assertArrayHasKey('value', $firstMeasurement);
        $this->assertArrayHasKey('timestamp', $firstMeasurement);
        $this->assertArrayHasKey('location', $firstMeasurement);

        // Verify location info
        $this->assertSame('IJMDBTHVN', $firstMeasurement['location']['id']);
        $this->assertSame('IJmuiden', $firstMeasurement['location']['name']);
        $this->assertIsFloat($firstMeasurement['location']['distanceKm']);
    }

    public function testGetMeasurementsFormatsGrootheidInformationCorrectly(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.0, ['OW'], ['T']);

        $rawMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 20.0,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);
        $measurement = $data['measurements'][0];

        $this->assertSame('T', $measurement['grootheid']['code']);
        $this->assertSame('Temperatuur', $measurement['grootheid']['dutch']);
        $this->assertSame('Temperature', $measurement['grootheid']['english']);
        $this->assertSame('°C', $measurement['grootheid']['unit']);
        $this->assertSame('temperature', $measurement['grootheid']['category']);
    }

    public function testGetMeasurementsFormatsCompartimentInformationCorrectly(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.0, ['OW'], ['T']);

        $rawMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 20.0,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);
        $measurement = $data['measurements'][0];

        $this->assertSame('OW', $measurement['compartiment']['code']);
        $this->assertSame('Oppervlaktewater', $measurement['compartiment']['dutch']);
        $this->assertSame('Surface water', $measurement['compartiment']['english']);
    }

    public function testGetMeasurementsHandlesUnknownGrootheidCode(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.0, ['OW'], ['UNKNOWN']);

        $rawMeasurements = [
            [
                'grootheid' => 'UNKNOWN_CODE',
                'compartiment' => 'OW',
                'value' => 100,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);
        $measurement = $data['measurements'][0];

        // Should use code as fallback when not found
        $this->assertSame('UNKNOWN_CODE', $measurement['grootheid']['code']);
        $this->assertSame('UNKNOWN_CODE', $measurement['grootheid']['dutch']);
        $this->assertSame('UNKNOWN_CODE', $measurement['grootheid']['english']);
        $this->assertNull($measurement['grootheid']['unit']);
        $this->assertSame('other', $measurement['grootheid']['category']);
    }

    public function testGetMeasurementsHandlesUnknownCompartimentCode(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.0, ['XX'], ['T']);

        $rawMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'UNKNOWN_COMP',
                'value' => 20.0,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);
        $measurement = $data['measurements'][0];

        // Should use code as fallback when not found
        $this->assertSame('UNKNOWN_COMP', $measurement['compartiment']['code']);
        $this->assertSame('UNKNOWN_COMP', $measurement['compartiment']['dutch']);
        $this->assertSame('UNKNOWN_COMP', $measurement['compartiment']['english']);
    }

    public function testGetMeasurementsHandlesNullPrimaryMeasurementData(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.0, ['OW'], ['T']);

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn(null);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('measurements', $data);
        $this->assertCount(0, $data['measurements']);
    }

    public function testGetMeasurementsFetchesFallbackLocationForMissingWaveCapabilities(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);

        // Primary location missing Hm0 capability (but at same coordinates for testing)
        $primaryLocation = new RwsLocation(
            'PRIMARY',
            'Primary Location',
            52.0,
            4.0,
            ['OW'],
            ['T', 'WINDSHD'], // Missing Hm0, Tm02, Th3
            RwsLocation::WATER_TYPE_SEA
        );

        // Fallback location with Hm0 capability (very close for testing)
        $fallbackLocation = new RwsLocation(
            'FALLBACK',
            'Fallback Location',
            52.001,
            4.001,
            ['OW'],
            ['Hm0', 'Tm02', 'Th3'],
            RwsLocation::WATER_TYPE_SEA
        );

        $primaryMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 18.5,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $fallbackMeasurements = [
            [
                'grootheid' => 'Hm0',
                'compartiment' => 'OW',
                'value' => 85,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'Tm02',
                'compartiment' => 'OW',
                'value' => 5.2,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'Th3',
                'compartiment' => 'OW',
                'value' => 270,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')
            ->willReturnMap([
                ['PRIMARY', $primaryMeasurements],
                ['FALLBACK', $fallbackMeasurements],
            ]);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$primaryLocation, $fallbackLocation]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertGreaterThanOrEqual(4, count($data['measurements']));

        // Should have measurements from both locations
        $locationIds = array_unique(array_map(fn ($m) => $m['location']['id'], $data['measurements']));
        $this->assertContains('PRIMARY', $locationIds);
        $this->assertContains('FALLBACK', $locationIds);
    }

    public function testGetMeasurementsSortsMeasurementsByCategory(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.0, ['OW'], ['T', 'Hm0', 'WATHTE', 'WINDSHD']);

        $rawMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 18.5,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'WINDSHD',
                'compartiment' => 'LT',
                'value' => 5.5,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'Hm0',
                'compartiment' => 'OW',
                'value' => 85,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'WATHTE',
                'compartiment' => 'OW',
                'value' => 120,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);
        $categories = array_map(fn ($m) => $m['grootheid']['category'], $data['measurements']);

        // Expected order: water_level, waves, temperature, wind
        $this->assertSame('water_level', $categories[0]);
        $this->assertSame('waves', $categories[1]);
        $this->assertSame('temperature', $categories[2]);
        $this->assertSame('wind', $categories[3]);
    }

    public function testGetMeasurementsSortsByCodeWhenCategoriesAreEqual(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.0, ['OW'], ['Tm02', 'Hm0', 'Hmax']);

        $rawMeasurements = [
            [
                'grootheid' => 'Tm02',
                'compartiment' => 'OW',
                'value' => 5.2,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'Hmax',
                'compartiment' => 'OW',
                'value' => 120,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'Hm0',
                'compartiment' => 'OW',
                'value' => 85,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);
        $codes = array_map(fn ($m) => $m['grootheid']['code'], $data['measurements']);

        // All are in 'waves' category, so should be sorted alphabetically by code
        $this->assertSame('Hm0', $codes[0]);
        $this->assertSame('Hmax', $codes[1]);
        $this->assertSame('Tm02', $codes[2]);
    }

    public function testGetMeasurementsHandlesEmptyMeasurementsArray(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.0, ['OW'], ['T']);

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn([]);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['measurements']);
    }

    public function testGetCodesReturnsAllMeasurementCodes(): void
    {
        // Arrange
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getCodes();

        // Assert
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('compartimenten', $data);
        $this->assertArrayHasKey('grootheden', $data);
        $this->assertArrayHasKey('categories', $data);

        // Verify structure of compartimenten
        $this->assertArrayHasKey('OW', $data['compartimenten']);
        $this->assertArrayHasKey('dutch', $data['compartimenten']['OW']);
        $this->assertArrayHasKey('english', $data['compartimenten']['OW']);
        $this->assertArrayHasKey('description', $data['compartimenten']['OW']);

        // Verify structure of grootheden
        $this->assertArrayHasKey('T', $data['grootheden']);
        $this->assertArrayHasKey('dutch', $data['grootheden']['T']);
        $this->assertArrayHasKey('english', $data['grootheden']['T']);
        $this->assertArrayHasKey('unit', $data['grootheden']['T']);
        $this->assertArrayHasKey('category', $data['grootheden']['T']);

        // Verify categories
        $this->assertArrayHasKey('water_level', $data['categories']);
        $this->assertArrayHasKey('waves', $data['categories']);
        $this->assertArrayHasKey('temperature', $data['categories']);
    }

    public function testGetMeasurementsReturnsJsonResponse(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test', 52.0, 4.0, ['OW'], ['T']);

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn([]);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testGetCodesReturnsJsonResponse(): void
    {
        // Arrange
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getCodes();

        // Assert
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testGetMeasurementsPreservesTimestampFormat(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test', 52.0, 4.0, ['OW'], ['T']);

        $timestamp = '2024-01-15T14:30:45+01:00';
        $rawMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 18.5,
                'timestamp' => $timestamp,
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);
        $this->assertSame($timestamp, $data['measurements'][0]['timestamp']);
    }

    public function testGetMeasurementsPreservesNumericValues(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test', 52.0, 4.0, ['OW'], ['T', 'WATHTE']);

        $rawMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 18.567,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'WATHTE',
                'compartiment' => 'OW',
                'value' => 123,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturn($rawMeasurements);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);

        // Measurements are sorted - WATHTE (water_level) comes before T (temperature)
        $wathteMeasurement = array_values(array_filter($data['measurements'], fn ($m) => 'WATHTE' === $m['grootheid']['code']))[0];
        $tempMeasurement = array_values(array_filter($data['measurements'], fn ($m) => 'T' === $m['grootheid']['code']))[0];

        $this->assertSame(123, $wathteMeasurement['value']);
        $this->assertSame(18.567, $tempMeasurement['value']);
    }

    public function testGetMeasurementsRespects20KmMaximumDistanceLimit(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);

        // Location more than 20km away (different latitude/longitude)
        $farLocation = new RwsLocation('FAR', 'Far Location', 53.0, 5.0, ['OW'], ['T']);

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$farLocation]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No RWS location found near this swimming spot', $data['error']);
    }

    public function testGetMeasurementsFiltersOnlyRequestedCapabilityFromFallback(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $primaryLocation = new RwsLocation(
            'PRIMARY',
            'Primary',
            52.0,
            4.0,
            ['OW'],
            ['T'],
            RwsLocation::WATER_TYPE_SEA
        );
        $fallbackLocation = new RwsLocation(
            'FALLBACK',
            'Fallback',
            52.001,
            4.001,
            ['OW'],
            ['Hm0', 'Tm02'],
            RwsLocation::WATER_TYPE_SEA
        );

        $primaryMeasurements = [
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 20.0,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $fallbackMeasurements = [
            [
                'grootheid' => 'Hm0',
                'compartiment' => 'OW',
                'value' => 85,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'Tm02',
                'compartiment' => 'OW',
                'value' => 5.2,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
            [
                'grootheid' => 'T',
                'compartiment' => 'OW',
                'value' => 19.5,
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchRawMeasurements')->willReturnMap([
            ['PRIMARY', $primaryMeasurements],
            ['FALLBACK', $fallbackMeasurements],
        ]);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$primaryLocation, $fallbackLocation]);
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($swimmingSpot);

        $controller = $this->createController($rwsClient, $locationRepository, $swimmingSpotRepository);

        // Act
        $response = $controller->getMeasurements('test-spot');

        // Assert
        $data = json_decode($response->getContent(), true);

        // Should include T from PRIMARY, Hm0 and Tm02 from FALLBACK
        // But NOT T from FALLBACK (since PRIMARY already has it)
        $this->assertCount(3, $data['measurements']);

        $codes = array_map(fn ($m) => $m['grootheid']['code'], $data['measurements']);
        $this->assertContains('T', $codes);
        $this->assertContains('Hm0', $codes);
        $this->assertContains('Tm02', $codes);

        // Verify T comes from PRIMARY, not FALLBACK
        $tempMeasurement = array_filter($data['measurements'], fn ($m) => 'T' === $m['grootheid']['code']);
        $this->assertSame('PRIMARY', reset($tempMeasurement)['location']['id']);
    }
}
