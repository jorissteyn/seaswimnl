<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Seaswim\Application\Port\TidalInfoProviderInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Application\Port\WeatherStationRepositoryInterface;
use Seaswim\Application\UseCase\GetConditionsForSwimmingSpot;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\Service\ComfortIndexCalculator;
use Seaswim\Domain\Service\NearestRwsLocationFinder;
use Seaswim\Domain\Service\NearestRwsLocationMatcher;
use Seaswim\Domain\Service\SafetyScoreCalculator;
use Seaswim\Domain\Service\WeatherStationMatcher;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\Sunpower;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WindSpeed;
use Seaswim\Infrastructure\ApiPlatform\Dto\ConditionsOutput;
use Seaswim\Infrastructure\ApiPlatform\State\ConditionsProvider;
use Seaswim\Infrastructure\Service\LocationBlacklist;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConditionsProviderTest extends TestCase
{
    private ConditionsProvider $provider;
    private Operation $operation;
    private SwimmingSpotRepositoryInterface $swimmingSpotRepository;
    private RwsLocationRepositoryInterface $locationRepository;
    private WaterConditionsProviderInterface $waterProvider;
    private WeatherConditionsProviderInterface $weatherProvider;
    private TidalInfoProviderInterface $tidalProvider;
    private NearestRwsLocationMatcher $rwsLocationMatcher;
    private WeatherStationMatcher $weatherStationMatcher;
    private WeatherStationRepositoryInterface $weatherStationRepository;

    protected function setUp(): void
    {
        $this->swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $this->locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $this->waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $this->weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $this->tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $this->weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);

        // Create real instances of final services
        $blacklist = new LocationBlacklist('/tmp');  // Use temp dir for tests
        $this->rwsLocationMatcher = new NearestRwsLocationMatcher($this->locationRepository, $blacklist);
        $rwsLocationFinder = new NearestRwsLocationFinder($blacklist);
        $this->weatherStationMatcher = new WeatherStationMatcher($this->weatherStationRepository);

        $getConditions = new GetConditionsForSwimmingSpot(
            $this->swimmingSpotRepository,
            $this->locationRepository,
            $this->waterProvider,
            $this->weatherProvider,
            $this->tidalProvider,
            new SafetyScoreCalculator(),
            new ComfortIndexCalculator(),
            $this->rwsLocationMatcher,
            $rwsLocationFinder,
            $this->weatherStationMatcher
        );

        $this->provider = new ConditionsProvider($getConditions);
        $this->operation = $this->createMock(Operation::class);
    }

    public function testProvideThrowsNotFoundWhenSwimmingSpotIdNotSpecified(): void
    {
        // Arrange
        $uriVariables = [];

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Swimming spot not specified');

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }

    public function testProvideThrowsNotFoundWhenSwimmingSpotIdIsNull(): void
    {
        // Arrange
        $uriVariables = ['swimmingSpot' => null];

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Swimming spot not specified');

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }

    public function testProvideThrowsNotFoundWhenConditionsAreNull(): void
    {
        // Arrange
        $uriVariables = ['swimmingSpot' => 'scheveningen'];

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with('scheveningen')
            ->willReturn(null);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Swimming spot not found');

        // Act
        $this->provider->provide($this->operation, $uriVariables);
    }

    public function testProvideReturnsConditionsOutputWithCompleteData(): void
    {
        // Arrange
        $swimmingSpotId = 'scheveningen';
        $uriVariables = ['swimmingSpot' => $swimmingSpotId];
        $measuredAt = new \DateTimeImmutable('2024-01-15 14:30:00', new \DateTimeZone('UTC'));
        $swimmingSpot = new SwimmingSpot('scheveningen', 'Scheveningen', 52.1, 4.3);
        $location = new RwsLocation('scheveningen', 'Scheveningen', 52.1, 4.3);

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.5),
            $measuredAt
        );

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(22.0),
            WindSpeed::fromMetersPerSecond(5.0),
            'W',
            Sunpower::fromWattsPerSquareMeter(600.0),
            $measuredAt
        );

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with($swimmingSpotId)
            ->willReturn($swimmingSpot);

        $this->locationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([$location]);

        $this->weatherStationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([]);

        $this->waterProvider->expects($this->once())
            ->method('getConditions')
            ->with($location)
            ->willReturn($waterConditions);

        $this->weatherProvider->expects($this->once())
            ->method('getConditions')
            ->with($location)
            ->willReturn($weatherConditions);

        $this->tidalProvider->expects($this->once())
            ->method('getTidalInfo')
            ->with($location)
            ->willReturn(null);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(ConditionsOutput::class, $result);
        $this->assertSame($swimmingSpotId, $result->locationId);
        $this->assertNotNull($result->water);
        $this->assertSame(18.5, $result->water->temperature);
        $this->assertSame(0.8, $result->water->waveHeight);
        $this->assertSame(0.5, $result->water->waterHeight);
        $this->assertSame('2024-01-15T14:30:00+00:00', $result->water->measuredAt);
        $this->assertNotNull($result->weather);
        $this->assertSame(22.0, $result->weather->airTemperature);
        $this->assertSame(18.0, $result->weather->windSpeed);
        $this->assertSame('W', $result->weather->windDirection);
        $this->assertSame(600.0, $result->weather->sunpower);
        $this->assertSame('Good', $result->weather->sunpowerLevel);
        $this->assertSame('2024-01-15T14:30:00+00:00', $result->weather->measuredAt);
        $this->assertNotNull($result->metrics);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $result->updatedAt);
    }

    public function testProvideReturnsConditionsOutputWithNullWaterConditions(): void
    {
        // Arrange
        $swimmingSpotId = 'remote-beach';
        $uriVariables = ['swimmingSpot' => $swimmingSpotId];
        $swimmingSpot = new SwimmingSpot('remote-beach', 'Remote Beach', 55.0, 5.0);
        $location = new RwsLocation('remote', 'Remote', 55.0, 5.0);
        $measuredAt = new \DateTimeImmutable('2024-01-15 14:30:00', new \DateTimeZone('UTC'));

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(20.0),
            WindSpeed::fromMetersPerSecond(3.0),
            'N',
            Sunpower::fromWattsPerSquareMeter(400.0),
            $measuredAt
        );

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with($swimmingSpotId)
            ->willReturn($swimmingSpot);

        $this->locationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([$location]);

        $this->weatherStationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([]);

        $this->waterProvider->expects($this->once())
            ->method('getConditions')
            ->with($location)
            ->willReturn(null);

        $this->waterProvider->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        $this->weatherProvider->expects($this->once())
            ->method('getConditions')
            ->with($location)
            ->willReturn($weatherConditions);

        $this->tidalProvider->expects($this->once())
            ->method('getTidalInfo')
            ->with($location)
            ->willReturn(null);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(ConditionsOutput::class, $result);
        $this->assertNull($result->water);
        $this->assertNotNull($result->weather);
        $this->assertNotNull($result->metrics);
    }

    public function testProvideReturnsConditionsOutputWithNullWeatherConditions(): void
    {
        // Arrange
        $swimmingSpotId = 'test-spot';
        $uriVariables = ['swimmingSpot' => $swimmingSpotId];
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('test', 'Test', 52.0, 4.0);
        $measuredAt = new \DateTimeImmutable('2024-01-15 14:30:00', new \DateTimeZone('UTC'));

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(17.0),
            WaveHeight::fromMeters(1.2),
            WaterHeight::fromMeters(0.3),
            $measuredAt
        );

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with($swimmingSpotId)
            ->willReturn($swimmingSpot);

        $this->locationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([$location]);

        $this->weatherStationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([]);

        $this->waterProvider->expects($this->once())
            ->method('getConditions')
            ->with($location)
            ->willReturn($waterConditions);

        $this->weatherProvider->expects($this->once())
            ->method('getConditions')
            ->with($location)
            ->willReturn(null);

        $this->weatherProvider->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        $this->tidalProvider->expects($this->once())
            ->method('getTidalInfo')
            ->with($location)
            ->willReturn(null);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(ConditionsOutput::class, $result);
        $this->assertNotNull($result->water);
        $this->assertNull($result->weather);
        $this->assertNotNull($result->metrics);
    }

    public function testProvideReturnsConditionsOutputWithBothNullConditions(): void
    {
        // Arrange
        $swimmingSpotId = 'no-data-spot';
        $uriVariables = ['swimmingSpot' => $swimmingSpotId];
        $swimmingSpot = new SwimmingSpot('no-data-spot', 'No Data Spot', 52.0, 4.0);
        $location = new RwsLocation('no-data', 'No Data', 52.0, 4.0);

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with($swimmingSpotId)
            ->willReturn($swimmingSpot);

        $this->locationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([$location]);

        $this->weatherStationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([]);

        $this->waterProvider->expects($this->once())
            ->method('getConditions')
            ->with($location)
            ->willReturn(null);

        $this->waterProvider->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        $this->weatherProvider->expects($this->once())
            ->method('getConditions')
            ->with($location)
            ->willReturn(null);

        $this->weatherProvider->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        $this->tidalProvider->expects($this->once())
            ->method('getTidalInfo')
            ->with($location)
            ->willReturn(null);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        // Assert
        $this->assertInstanceOf(ConditionsOutput::class, $result);
        $this->assertNull($result->water);
        $this->assertNull($result->weather);
        $this->assertNotNull($result->metrics);
    }

    public function testProvideHandlesEmptyContextArray(): void
    {
        // Arrange
        $swimmingSpotId = 'test-spot';
        $uriVariables = ['swimmingSpot' => $swimmingSpotId];
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('test', 'Test', 52.0, 4.0);

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->with($swimmingSpotId)
            ->willReturn($swimmingSpot);

        $this->locationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([$location]);

        $this->weatherStationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([]);

        $this->waterProvider->expects($this->once())
            ->method('getConditions')
            ->willReturn(null);

        $this->waterProvider->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        $this->weatherProvider->expects($this->once())
            ->method('getConditions')
            ->willReturn(null);

        $this->weatherProvider->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        $this->tidalProvider->expects($this->once())
            ->method('getTidalInfo')
            ->willReturn(null);

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables, []);

        // Assert
        $this->assertInstanceOf(ConditionsOutput::class, $result);
    }

    public function testProvideGeneratesCurrentTimestampForUpdatedAt(): void
    {
        // Arrange
        $swimmingSpotId = 'test-spot';
        $uriVariables = ['swimmingSpot' => $swimmingSpotId];
        $swimmingSpot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('test', 'Test', 52.0, 4.0);

        $this->swimmingSpotRepository->expects($this->once())
            ->method('findById')
            ->willReturn($swimmingSpot);

        $this->locationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([$location]);

        $this->weatherStationRepository->expects($this->any())
            ->method('findAll')
            ->willReturn([]);

        $this->waterProvider->expects($this->once())
            ->method('getConditions')
            ->willReturn(null);

        $this->waterProvider->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        $this->weatherProvider->expects($this->once())
            ->method('getConditions')
            ->willReturn(null);

        $this->weatherProvider->expects($this->once())
            ->method('getLastError')
            ->willReturn(null);

        $this->tidalProvider->expects($this->once())
            ->method('getTidalInfo')
            ->willReturn(null);

        $beforeCall = new \DateTimeImmutable();

        // Act
        $result = $this->provider->provide($this->operation, $uriVariables);

        $afterCall = new \DateTimeImmutable();

        // Assert
        $resultTime = new \DateTimeImmutable($result->updatedAt);
        $this->assertGreaterThanOrEqual($beforeCall->getTimestamp(), $resultTime->getTimestamp());
        $this->assertLessThanOrEqual($afterCall->getTimestamp(), $resultTime->getTimestamp());
    }
}
