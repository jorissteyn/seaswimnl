<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Infrastructure\ApiPlatform\Dto\LocationOutput;
use Seaswim\Infrastructure\ApiPlatform\State\LocationProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LocationProviderTest extends TestCase
{
    private RwsLocationRepositoryInterface $locationRepository;
    private LocationProvider $provider;
    private Operation $operation;

    protected function setUp(): void
    {
        $this->locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $this->provider = new LocationProvider($this->locationRepository);
        $this->operation = $this->createMock(Operation::class);
    }

    public function testProvideReturnsAllLocationsWhenNoIdSpecified(): void
    {
        // Arrange
        $locations = [
            new RwsLocation('vlissingen', 'Vlissingen', 51.4424, 3.5968),
            new RwsLocation('scheveningen', 'Scheveningen', 52.1, 4.3),
            new RwsLocation('ijmuiden', 'IJmuiden', 52.5, 4.6),
        ];

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($locations);

        // Act
        $result = $this->provider->provide($this->operation, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContainsOnlyInstancesOf(LocationOutput::class, $result);
        $this->assertSame('vlissingen', $result[0]->id);
        $this->assertSame('Vlissingen', $result[0]->name);
        $this->assertSame(51.4424, $result[0]->latitude);
        $this->assertSame(3.5968, $result[0]->longitude);
        $this->assertSame('scheveningen', $result[1]->id);
        $this->assertSame('ijmuiden', $result[2]->id);
    }

    public function testProvideReturnsEmptyArrayWhenNoLocationsExist(): void
    {
        // Arrange
        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        // Act
        $result = $this->provider->provide($this->operation, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProvideReturnsSingleLocationById(): void
    {
        // Arrange
        $location = new RwsLocation('scheveningen', 'Scheveningen', 52.1, 4.3);
        $uriVariables = ['id' => 'scheveningen'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('scheveningen')
            ->willReturn($location);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(LocationOutput::class, $result);
        $this->assertSame('scheveningen', $result->id);
        $this->assertSame('Scheveningen', $result->name);
        $this->assertSame(52.1, $result->latitude);
        $this->assertSame(4.3, $result->longitude);
    }

    public function testProvideThrowsNotFoundWhenLocationNotFound(): void
    {
        // Arrange
        $uriVariables = ['id' => 'non-existent'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('non-existent')
            ->willReturn(null);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Location not found');

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }

    public function testProvideReturnsLocationWithCompartimentenAndGrootheden(): void
    {
        // Arrange
        $location = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.4424,
            3.5968,
            ['OW', 'LW'],
            ['T', 'WATHTE', 'Hm0'],
            'sea'
        );
        $uriVariables = ['id' => 'vlissingen'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('vlissingen')
            ->willReturn($location);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(LocationOutput::class, $result);
        $this->assertSame('vlissingen', $result->id);
        $this->assertSame('Vlissingen', $result->name);
        $this->assertSame(51.4424, $result->latitude);
        $this->assertSame(3.5968, $result->longitude);
    }

    public function testProvideHandlesEmptyContextArray(): void
    {
        // Arrange
        $locations = [new RwsLocation('test', 'Test', 50.0, 4.0)];

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($locations);

        // Act
        $result = $this->provider->provide($this->operation, [], []);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testProvideHandlesNonEmptyContextArray(): void
    {
        // Arrange
        $locations = [new RwsLocation('test', 'Test', 50.0, 4.0)];
        $context = ['some_key' => 'some_value'];

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($locations);

        // Act
        $result = $this->provider->provide($this->operation, [], $context);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testProvideReturnsLocationWithExtremeCoordinates(): void
    {
        // Arrange
        $location = new RwsLocation('arctic-station', 'Arctic Station', 89.9999, -179.9999);
        $uriVariables = ['id' => 'arctic-station'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('arctic-station')
            ->willReturn($location);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(LocationOutput::class, $result);
        $this->assertSame(89.9999, $result->latitude);
        $this->assertSame(-179.9999, $result->longitude);
    }

    public function testProvideReturnsLocationWithZeroCoordinates(): void
    {
        // Arrange
        $location = new RwsLocation('equator-prime', 'Equator Prime Meridian', 0.0, 0.0);
        $uriVariables = ['id' => 'equator-prime'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('equator-prime')
            ->willReturn($location);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(LocationOutput::class, $result);
        $this->assertSame(0.0, $result->latitude);
        $this->assertSame(0.0, $result->longitude);
    }

    public function testProvideReturnsLocationWithSpecialCharactersInName(): void
    {
        // Arrange
        $location = new RwsLocation(
            'hoek-van-holland',
            'Hoek van Holland - Älämölö',
            51.98,
            4.12
        );
        $uriVariables = ['id' => 'hoek-van-holland'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('hoek-van-holland')
            ->willReturn($location);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(LocationOutput::class, $result);
        $this->assertSame('Hoek van Holland - Älämölö', $result->name);
    }

    public function testProvideHandlesNumericStringId(): void
    {
        // Arrange
        $location = new RwsLocation('12345', 'Numeric ID Location', 52.0, 4.0);
        $uriVariables = ['id' => '12345'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('12345')
            ->willReturn($location);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(LocationOutput::class, $result);
        $this->assertSame('12345', $result->id);
    }

    public function testProvidePreservesLocationOrderWhenReturningAll(): void
    {
        // Arrange
        $locations = [
            new RwsLocation('a', 'Location A', 50.0, 3.0),
            new RwsLocation('b', 'Location B', 51.0, 4.0),
            new RwsLocation('c', 'Location C', 52.0, 5.0),
            new RwsLocation('d', 'Location D', 53.0, 6.0),
        ];

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($locations);

        // Act
        $result = $this->provider->provide($this->operation, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(4, $result);
        $this->assertSame('a', $result[0]->id);
        $this->assertSame('b', $result[1]->id);
        $this->assertSame('c', $result[2]->id);
        $this->assertSame('d', $result[3]->id);
    }

    public function testProvideHandlesVeryLongLocationName(): void
    {
        // Arrange
        $longName = str_repeat('Very Long Location Name ', 50);
        $location = new RwsLocation('long-name', $longName, 52.0, 4.0);
        $uriVariables = ['id' => 'long-name'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('long-name')
            ->willReturn($location);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(LocationOutput::class, $result);
        $this->assertSame($longName, $result->name);
    }

    public function testProvideMapsSingleLocationToOutputCorrectly(): void
    {
        // Arrange
        $location = new RwsLocation(
            'test-mapping',
            'Test Mapping Location',
            52.123456,
            4.654321,
            ['COMP1'],
            ['GROOT1'],
            'river'
        );
        $uriVariables = ['id' => 'test-mapping'];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('test-mapping')
            ->willReturn($location);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(LocationOutput::class, $result);
        $this->assertSame('test-mapping', $result->id);
        $this->assertSame('Test Mapping Location', $result->name);
        $this->assertSame(52.123456, $result->latitude);
        $this->assertSame(4.654321, $result->longitude);
    }

    public function testProvideDoesNotCallFindByIdWhenIdNotProvided(): void
    {
        // Arrange
        $this->locationRepository->expects($this->never())
            ->method('findById');

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        // Act
        $this->provider->provide($this->operation, []);
    }

    public function testProvideDoesNotCallFindAllWhenIdProvided(): void
    {
        // Arrange
        $location = new RwsLocation('test', 'Test', 50.0, 4.0);
        $uriVariables = ['id' => 'test'];

        $this->locationRepository->expects($this->never())
            ->method('findAll');

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->willReturn($location);

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }

    public function testProvideHandlesEmptyStringId(): void
    {
        // Arrange
        $uriVariables = ['id' => ''];

        $this->locationRepository->expects($this->once())
            ->method('findById')
            ->with('')
            ->willReturn(null);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Location not found');

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }
}
