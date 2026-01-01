<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Controller\Api;

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
use Seaswim\Domain\ValueObject\TideEvent;
use Seaswim\Domain\ValueObject\TideInfo;
use Seaswim\Domain\ValueObject\TideType;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaveDirection;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WavePeriod;
use Seaswim\Domain\ValueObject\WindSpeed;
use Seaswim\Infrastructure\Controller\Api\ConditionsController;
use Seaswim\Infrastructure\Service\LocationBlacklist;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class ConditionsControllerTest extends TestCase
{
    private function createControllerWithMockedUseCase(?array $returnValue): ConditionsController
    {
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);

        // Create real instances of final classes with dependencies
        $safetyCalculator = new SafetyScoreCalculator();
        $comfortCalculator = new ComfortIndexCalculator();
        $blacklist = new LocationBlacklist(__DIR__);
        $rwsLocationMatcher = new NearestRwsLocationMatcher($locationRepository, $blacklist);
        $rwsLocationFinder = new NearestRwsLocationFinder($blacklist);
        $weatherStationMatcher = new WeatherStationMatcher($weatherStationRepository);

        $useCase = new GetConditionsForSwimmingSpot(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $safetyCalculator,
            $comfortCalculator,
            $rwsLocationMatcher,
            $rwsLocationFinder,
            $weatherStationMatcher,
        );

        // Configure the mocks
        if (null === $returnValue) {
            $swimmingSpotRepository->method('findById')->willReturn(null);
        } else {
            $swimmingSpotRepository->method('findById')->willReturn($returnValue['swimmingSpot']);
            $locationRepository->method('findAll')->willReturn($returnValue['rwsLocation'] ? [$returnValue['rwsLocation']['location']] : []);
            $waterProvider->method('getConditions')->willReturn($returnValue['water'] ?? null);
            $weatherProvider->method('getConditions')->willReturn($returnValue['weather'] ?? null);
            $tidalProvider->method('getTidalInfo')->willReturn($returnValue['tides'] ?? null);
        }

        $controller = new ConditionsController($useCase);

        // Set up the container for AbstractController
        $container = new Container();
        $container->set('serializer', new Serializer([new ObjectNormalizer()], [new JsonEncoder()]));
        $container->set('request_stack', new RequestStack());

        $reflection = new \ReflectionClass($controller);
        $containerProperty = $reflection->getParentClass()->getProperty('container');
        $containerProperty->setAccessible(true);
        $containerProperty->setValue($controller, $container);

        return $controller;
    }

    public function testGetReturnsNotFoundWhenSwimmingSpotDoesNotExist(): void
    {
        // Arrange
        $controller = $this->createControllerWithMockedUseCase(null);

        // Act
        $response = $controller->get('non-existent-spot');

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('error', $content);
        $this->assertSame('Swimming spot not found', $content['error']);
    }

    public function testGetReturnsJsonResponseForValidSwimmingSpot(): void
    {
        // Arrange
        $measuredAt = new \DateTimeImmutable('2025-01-15 14:30:00');
        $swimmingSpot = new SwimmingSpot('scheveningen', 'Scheveningen', 52.1, 4.3);
        $rwsLocation = new RwsLocation('scheveningen', 'Scheveningen', 52.1, 4.3);

        $waterConditions = new WaterConditions(
            $rwsLocation,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.5),
            $measuredAt,
            WindSpeed::fromMetersPerSecond(5.0),
            'W',
            WavePeriod::fromSeconds(4.5),
            WaveDirection::fromDegrees(270.0),
        );

        $weatherConditions = new WeatherConditions(
            $rwsLocation,
            Temperature::fromCelsius(22.0),
            WindSpeed::fromMetersPerSecond(5.0),
            'W',
            Sunpower::fromWattsPerSquareMeter(600.0),
            $measuredAt,
        );

        $events = [
            new TideEvent(TideType::High, $measuredAt->modify('+2 hours'), 180),
            new TideEvent(TideType::Low, $measuredAt->modify('+8 hours'), 30),
        ];
        $tideInfo = new TideInfo($events, $measuredAt);

        $conditions = [
            'swimmingSpot' => $swimmingSpot,
            'rwsLocation' => ['location' => $rwsLocation, 'distanceKm' => 1.5],
            'water' => $waterConditions,
            'weather' => $weatherConditions,
            'tides' => $tideInfo,
        ];

        $controller = $this->createControllerWithMockedUseCase($conditions);

        // Act
        $response = $controller->get('scheveningen');

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);

        // Verify basic structure
        $this->assertArrayHasKey('swimmingSpot', $content);
        $this->assertArrayHasKey('water', $content);
        $this->assertArrayHasKey('weather', $content);
        $this->assertArrayHasKey('tides', $content);
        $this->assertArrayHasKey('metrics', $content);
    }

    public function testGetFormatsSwimmingSpotCorrectly(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('scheveningen', 'Scheveningen', 52.1, 4.3);
        $rwsLocation = new RwsLocation('scheveningen', 'Scheveningen', 52.1, 4.3);

        $conditions = [
            'swimmingSpot' => $swimmingSpot,
            'rwsLocation' => ['location' => $rwsLocation, 'distanceKm' => 1.5],
            'water' => null,
            'weather' => null,
            'tides' => null,
        ];

        $controller = $this->createControllerWithMockedUseCase($conditions);

        // Act
        $response = $controller->get('scheveningen');

        // Assert
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('swimmingSpot', $content);
        $this->assertSame('scheveningen', $content['swimmingSpot']['id']);
        $this->assertSame('Scheveningen', $content['swimmingSpot']['name']);
        $this->assertSame(52.1, $content['swimmingSpot']['latitude']);
        $this->assertSame(4.3, $content['swimmingSpot']['longitude']);
    }

    public function testGetFormatsWaterConditionsCorrectly(): void
    {
        // Arrange
        $measuredAt = new \DateTimeImmutable('2025-01-15 14:30:00');
        $swimmingSpot = new SwimmingSpot('scheveningen', 'Scheveningen', 52.1, 4.3);
        $rwsLocation = new RwsLocation('scheveningen', 'Scheveningen', 52.1, 4.3);

        $waterConditions = new WaterConditions(
            $rwsLocation,
            Temperature::fromCelsius(18.5),
            WaveHeight::fromMeters(0.8),
            WaterHeight::fromMeters(0.5),
            $measuredAt,
            WindSpeed::fromMetersPerSecond(5.0),
            'W',
            WavePeriod::fromSeconds(4.5),
            WaveDirection::fromDegrees(270.0),
        );

        $conditions = [
            'swimmingSpot' => $swimmingSpot,
            'rwsLocation' => ['location' => $rwsLocation, 'distanceKm' => 1.5],
            'water' => $waterConditions,
            'weather' => null,
            'tides' => null,
        ];

        $controller = $this->createControllerWithMockedUseCase($conditions);

        // Act
        $response = $controller->get('scheveningen');

        // Assert
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('water', $content);
        $water = $content['water'];

        $this->assertSame(18.5, $water['temperature']);
        $this->assertSame(0.8, $water['waveHeight']);
        $this->assertSame(0.5, $water['waterHeight']);
        $this->assertSame(4.5, $water['wavePeriod']);
        $this->assertEquals(270.0, $water['waveDirection']); // JSON encodes 270.0 as 270
        $this->assertSame('W', $water['waveDirectionCompass']);
        $this->assertEquals(18.0, $water['windSpeed']); // 5.0 m/s * 3.6 = 18.0 km/h, JSON encodes as 18
        $this->assertSame('W', $water['windDirection']);
        $this->assertSame($measuredAt->format('c'), $water['measuredAt']);
    }

    public function testGetFormatsWeatherConditionsCorrectly(): void
    {
        // Arrange
        $measuredAt = new \DateTimeImmutable('2025-01-15 14:30:00');
        $swimmingSpot = new SwimmingSpot('scheveningen', 'Scheveningen', 52.1, 4.3);
        $rwsLocation = new RwsLocation('scheveningen', 'Scheveningen', 52.1, 4.3);

        $weatherConditions = new WeatherConditions(
            $rwsLocation,
            Temperature::fromCelsius(22.0),
            WindSpeed::fromMetersPerSecond(5.0),
            'W',
            Sunpower::fromWattsPerSquareMeter(600.0),
            $measuredAt,
        );

        $conditions = [
            'swimmingSpot' => $swimmingSpot,
            'rwsLocation' => ['location' => $rwsLocation, 'distanceKm' => 1.5],
            'water' => null,
            'weather' => $weatherConditions,
            'tides' => null,
        ];

        $controller = $this->createControllerWithMockedUseCase($conditions);

        // Act
        $response = $controller->get('scheveningen');

        // Assert
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('weather', $content);
        $weather = $content['weather'];

        $this->assertEquals(22.0, $weather['airTemperature']); // JSON encodes 22.0 as 22
        $this->assertEquals(18.0, $weather['windSpeed']); // 5.0 m/s * 3.6 = 18.0 km/h, JSON encodes as 18
        $this->assertSame('W', $weather['windDirection']);
        $this->assertEquals(600.0, $weather['sunpower']); // JSON encodes 600.0 as 600
        $this->assertSame('Good', $weather['sunpowerLevel']); // 600 W/m² is 'Good' level
        $this->assertSame($measuredAt->format('c'), $weather['measuredAt']);
    }

    public function testGetFormatsTidalInfoCorrectly(): void
    {
        // Arrange
        $measuredAt = new \DateTimeImmutable('2025-01-15 14:30:00');
        $swimmingSpot = new SwimmingSpot('scheveningen', 'Scheveningen', 52.1, 4.3);
        $rwsLocation = new RwsLocation('scheveningen', 'Scheveningen', 52.1, 4.3);

        $highTideTime = $measuredAt->modify('+2 hours');
        $lowTideTime = $measuredAt->modify('+8 hours');

        $events = [
            new TideEvent(TideType::High, $highTideTime, 180),
            new TideEvent(TideType::Low, $lowTideTime, 30),
        ];
        $tideInfo = new TideInfo($events, $measuredAt);

        $conditions = [
            'swimmingSpot' => $swimmingSpot,
            'rwsLocation' => ['location' => $rwsLocation, 'distanceKm' => 1.5],
            'water' => null,
            'weather' => null,
            'tides' => $tideInfo,
        ];

        $controller = $this->createControllerWithMockedUseCase($conditions);

        // Act
        $response = $controller->get('scheveningen');

        // Assert
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('tides', $content);
        $tides = $content['tides'];

        // Verify next tide
        $this->assertArrayHasKey('next', $tides);
        $this->assertSame('high', $tides['next']['type']);
        $this->assertSame($highTideTime->format('c'), $tides['next']['time']);
        $this->assertSame($highTideTime->format('H:i'), $tides['next']['timeFormatted']);
        $this->assertSame(180, $tides['next']['heightCm']);

        // Verify next high tide
        $this->assertArrayHasKey('nextHigh', $tides);
        $this->assertSame($highTideTime->format('c'), $tides['nextHigh']['time']);

        // Verify next low tide
        $this->assertArrayHasKey('nextLow', $tides);
        $this->assertSame($lowTideTime->format('c'), $tides['nextLow']['time']);
    }

    public function testGetHandlesNullWaterConditions(): void
    {
        // Arrange
        $swimmingSpot = new SwimmingSpot('remote-beach', 'Remote Beach', 55.0, 5.0);
        $conditions = [
            'swimmingSpot' => $swimmingSpot,
            'rwsLocation' => null,
            'water' => null,
            'weather' => null,
            'tides' => null,
        ];

        $controller = $this->createControllerWithMockedUseCase($conditions);

        // Act
        $response = $controller->get('remote-beach');

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertNull($content['water']);
        $this->assertNull($content['weather']);
        $this->assertNull($content['tides']);
    }

    public function testGetReturnsCorrectHttpStatusCodes(): void
    {
        // Test 404 for non-existent spot
        $controller404 = $this->createControllerWithMockedUseCase(null);
        $response404 = $controller404->get('non-existent');
        $this->assertSame(404, $response404->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $response404->getStatusCode());

        // Test 200 for existing spot
        $swimmingSpot = new SwimmingSpot('test', 'Test Beach', 52.0, 4.0);
        $rwsLocation = new RwsLocation('test', 'Test', 52.0, 4.0);
        $conditions = [
            'swimmingSpot' => $swimmingSpot,
            'rwsLocation' => ['location' => $rwsLocation, 'distanceKm' => 1.0],
            'water' => null,
            'weather' => null,
            'tides' => null,
        ];
        $controller200 = $this->createControllerWithMockedUseCase($conditions);
        $response200 = $controller200->get('test');
        $this->assertSame(200, $response200->getStatusCode());
        $this->assertSame(Response::HTTP_OK, $response200->getStatusCode());
    }

    public function testGetReturnsJsonContentType(): void
    {
        // Arrange
        $controller = $this->createControllerWithMockedUseCase(null);

        // Act
        $response = $controller->get('non-existent');

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testGetHandlesSpecialCharactersInSwimmingSpotId(): void
    {
        // Arrange
        $controller = $this->createControllerWithMockedUseCase(null);

        // Act
        $response = $controller->get('t-gooi-beach');

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
