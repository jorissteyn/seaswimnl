<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Infrastructure\ApiPlatform\Dto\SwimmingSpotOutput;
use Seaswim\Infrastructure\ApiPlatform\State\SwimmingSpotProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SwimmingSpotProviderTest extends TestCase
{
    private SwimmingSpotRepositoryInterface $swimmingSpotRepository;
    private SwimmingSpotProvider $provider;
    private Operation $operation;

    protected function setUp(): void
    {
        $this->swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $this->provider = new SwimmingSpotProvider($this->swimmingSpotRepository);
        $this->operation = $this->createMock(Operation::class);
    }

    public function testProvideReturnsAllSpotsWhenNoIdSpecified(): void
    {
        // Arrange
        $spots = [
            new SwimmingSpot('scheveningen', 'Scheveningen', 52.1, 4.3),
            new SwimmingSpot('zandvoort', 'Zandvoort', 52.37, 4.53),
            new SwimmingSpot('bloemendaal', 'Bloemendaal', 52.42, 4.57),
        ];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($spots);

        // Act
        $result = $this->provider->provide($this->operation, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContainsOnlyInstancesOf(SwimmingSpotOutput::class, $result);
        $this->assertSame('scheveningen', $result[0]->id);
        $this->assertSame('Scheveningen', $result[0]->name);
        $this->assertSame(52.1, $result[0]->latitude);
        $this->assertSame(4.3, $result[0]->longitude);
        $this->assertSame('zandvoort', $result[1]->id);
        $this->assertSame('bloemendaal', $result[2]->id);
    }

    public function testProvideReturnsEmptyArrayWhenNoSpotsExist(): void
    {
        // Arrange
        $this->swimmingSpotRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        // Act
        $result = $this->provider->provide($this->operation, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProvideReturnsSingleSpotById(): void
    {
        // Arrange
        $spot = new SwimmingSpot('scheveningen', 'Scheveningen', 52.1, 4.3);
        $uriVariables = ['id' => 'scheveningen'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('scheveningen')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame('scheveningen', $result->id);
        $this->assertSame('Scheveningen', $result->name);
        $this->assertSame(52.1, $result->latitude);
        $this->assertSame(4.3, $result->longitude);
    }

    public function testProvideThrowsNotFoundWhenSpotNotFound(): void
    {
        // Arrange
        $uriVariables = ['id' => 'non-existent-beach'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('non-existent-beach')
            ->willReturn(null);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Swimming spot not found');

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }

    public function testProvideHandlesEmptyContextArray(): void
    {
        // Arrange
        $spots = [new SwimmingSpot('test', 'Test Beach', 50.0, 4.0)];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($spots);

        // Act
        $result = $this->provider->provide($this->operation, [], []);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testProvideHandlesNonEmptyContextArray(): void
    {
        // Arrange
        $spots = [new SwimmingSpot('test', 'Test Beach', 50.0, 4.0)];
        $context = ['some_key' => 'some_value'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($spots);

        // Act
        $result = $this->provider->provide($this->operation, [], $context);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testProvideReturnsSpotWithExtremeCoordinates(): void
    {
        // Arrange
        $spot = new SwimmingSpot('arctic-beach', 'Arctic Beach', 89.9999, -179.9999);
        $uriVariables = ['id' => 'arctic-beach'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('arctic-beach')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame(89.9999, $result->latitude);
        $this->assertSame(-179.9999, $result->longitude);
    }

    public function testProvideReturnsSpotWithZeroCoordinates(): void
    {
        // Arrange
        $spot = new SwimmingSpot('equator-beach', 'Equator Beach', 0.0, 0.0);
        $uriVariables = ['id' => 'equator-beach'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('equator-beach')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame(0.0, $result->latitude);
        $this->assertSame(0.0, $result->longitude);
    }

    public function testProvideReturnsSpotWithSpecialCharactersInName(): void
    {
        // Arrange
        $spot = new SwimmingSpot(
            'special-beach',
            'Bëäch wïth Spëcïäl Chäräctërs & Symbols!',
            52.0,
            4.0
        );
        $uriVariables = ['id' => 'special-beach'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('special-beach')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame('Bëäch wïth Spëcïäl Chäräctërs & Symbols!', $result->name);
    }

    public function testProvideHandlesNumericStringId(): void
    {
        // Arrange
        $spot = new SwimmingSpot('99999', 'Numeric ID Beach', 52.0, 4.0);
        $uriVariables = ['id' => '99999'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('99999')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame('99999', $result->id);
    }

    public function testProvidePreservesSpotOrderWhenReturningAll(): void
    {
        // Arrange
        $spots = [
            new SwimmingSpot('a', 'Beach A', 50.0, 3.0),
            new SwimmingSpot('b', 'Beach B', 51.0, 4.0),
            new SwimmingSpot('c', 'Beach C', 52.0, 5.0),
            new SwimmingSpot('d', 'Beach D', 53.0, 6.0),
        ];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($spots);

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

    public function testProvideHandlesVeryLongSpotName(): void
    {
        // Arrange
        $longName = str_repeat('Very Long Beach Name ', 50);
        $spot = new SwimmingSpot('long-name-beach', $longName, 52.0, 4.0);
        $uriVariables = ['id' => 'long-name-beach'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('long-name-beach')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame($longName, $result->name);
    }

    public function testProvideMapsSingleSpotToOutputCorrectly(): void
    {
        // Arrange
        $spot = new SwimmingSpot(
            'test-mapping',
            'Test Mapping Beach',
            52.123456,
            4.654321
        );
        $uriVariables = ['id' => 'test-mapping'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('test-mapping')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame('test-mapping', $result->id);
        $this->assertSame('Test Mapping Beach', $result->name);
        $this->assertSame(52.123456, $result->latitude);
        $this->assertSame(4.654321, $result->longitude);
    }

    public function testProvideDoesNotCallFindByIdWhenIdNotProvided(): void
    {
        // Arrange
        $this->swimmingSpotRepository->expects($this->never())
            ->method('findById');

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        // Act
        $this->provider->provide($this->operation, []);
    }

    public function testProvideDoesNotCallFindAllWhenIdProvided(): void
    {
        // Arrange
        $spot = new SwimmingSpot('test', 'Test', 50.0, 4.0);
        $uriVariables = ['id' => 'test'];

        $this->swimmingSpotRepository->expects($this->never())
            ->method('findAll');

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->willReturn($spot);

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }

    public function testProvideHandlesEmptyStringId(): void
    {
        // Arrange
        $uriVariables = ['id' => ''];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('')
            ->willReturn(null);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Swimming spot not found');

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }

    public function testProvideHandlesNegativeCoordinates(): void
    {
        // Arrange
        $spot = new SwimmingSpot(
            'southern-hemisphere',
            'Southern Hemisphere Beach',
            -33.8688,
            151.2093
        );
        $uriVariables = ['id' => 'southern-hemisphere'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('southern-hemisphere')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame(-33.8688, $result->latitude);
        $this->assertSame(151.2093, $result->longitude);
    }

    public function testProvideHandlesSingleSpotInCollection(): void
    {
        // Arrange
        $spots = [new SwimmingSpot('only-beach', 'Only Beach', 52.0, 4.0)];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($spots);

        // Act
        $result = $this->provider->provide($this->operation, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result[0]);
        $this->assertSame('only-beach', $result[0]->id);
    }

    public function testProvideHandlesLargeNumberOfSpots(): void
    {
        // Arrange
        $spots = [];
        for ($i = 0; $i < 100; ++$i) {
            $spots[] = new SwimmingSpot("beach-{$i}", "Beach {$i}", 52.0 + $i * 0.01, 4.0 + $i * 0.01);
        }

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($spots);

        // Act
        $result = $this->provider->provide($this->operation, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(100, $result);
        $this->assertContainsOnlyInstancesOf(SwimmingSpotOutput::class, $result);
        $this->assertSame('beach-0', $result[0]->id);
        $this->assertSame('beach-99', $result[99]->id);
    }

    public function testProvideHandlesIdWithSpecialCharacters(): void
    {
        // Arrange
        $spot = new SwimmingSpot('beach-with-dashes_and_underscores', 'Special ID Beach', 52.0, 4.0);
        $uriVariables = ['id' => 'beach-with-dashes_and_underscores'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('beach-with-dashes_and_underscores')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame('beach-with-dashes_and_underscores', $result->id);
    }

    public function testProvideReturnsSpotWithPreciseCoordinates(): void
    {
        // Arrange
        $spot = new SwimmingSpot(
            'precise-beach',
            'Precise Beach',
            52.123456789123456,
            4.987654321987654
        );
        $uriVariables = ['id' => 'precise-beach'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('precise-beach')
            ->willReturn($spot);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(SwimmingSpotOutput::class, $result);
        $this->assertSame(52.123456789123456, $result->latitude);
        $this->assertSame(4.987654321987654, $result->longitude);
    }
}
