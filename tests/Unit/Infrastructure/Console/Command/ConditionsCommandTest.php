<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

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
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WeatherStation;
use Seaswim\Domain\ValueObject\WindSpeed;
use Seaswim\Infrastructure\Console\Command\ConditionsCommand;
use Seaswim\Infrastructure\Service\LocationBlacklist;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConditionsCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/conditions_cmd_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir.'/blacklist.txt', '');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir.'/*'));
            rmdir($this->tempDir);
        }
    }

    public function testCommandIsProperlyConfigured(): void
    {
        $command = new ConditionsCommand($this->createUseCase());

        $this->assertSame('seaswim:conditions', $command->getName());
        $this->assertSame('Show water and weather conditions for a swimming spot', $command->getDescription());
    }

    public function testCommandHasRequiredSpotArgument(): void
    {
        $command = new ConditionsCommand($this->createUseCase());
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('spot'));
        $this->assertTrue($definition->getArgument('spot')->isRequired());
    }

    public function testCommandHasJsonOption(): void
    {
        $command = new ConditionsCommand($this->createUseCase());
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('json'));
        $this->assertFalse($definition->getOption('json')->acceptValue());
    }

    public function testExecuteReturnsFailureWhenSpotNotFound(): void
    {
        // Arrange
        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn(null);

        $useCase = $this->createUseCase($swimmingSpotRepository);
        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute(['spot' => 'nonexistent']);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $tester->getDisplay());
        $this->assertStringContainsString('nonexistent', $tester->getDisplay());
    }

    public function testExecuteReturnsSuccessWithValidSpot(): void
    {
        // Arrange
        $spot = new SwimmingSpot('vlissingen', 'Vlissingen', 51.44, 3.57);
        $location = new RwsLocation('VLSS', 'Vlissingen', 51.44, 3.57, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->with('vlissingen')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $measuredAt = new \DateTimeImmutable('2024-01-15 12:00:00', new \DateTimeZone('UTC'));

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.0),
            WaveHeight::fromMeters(0.5),
            WaterHeight::fromMeters(0.3),
            $measuredAt
        );

        $weatherStation = new WeatherStation('6310', 'Vlissingen', 51.44, 3.60);
        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(20.0),
            WindSpeed::fromMetersPerSecond(5.0),
            'W',
            Sunpower::fromWattsPerSquareMeter(500.0),
            $measuredAt,
            $weatherStation,
            2.5
        );

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn($waterConditions);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn($weatherConditions);

        $tideInfo = new TideInfo([
            new TideEvent(TideType::High, new \DateTimeImmutable('10:00'), 180.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('16:00'), 30.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('22:00'), 175.0),
        ], new \DateTimeImmutable('12:00'));

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn($tideInfo);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([$weatherStation]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute(['spot' => 'vlissingen']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Vlissingen', $output);
        $this->assertStringContainsString('Water Conditions', $output);
        $this->assertStringContainsString('Weather Conditions', $output);
        $this->assertStringContainsString('Tides', $output);
        $this->assertStringContainsString('Swim Metrics', $output);
    }

    public function testExecuteWithJsonOptionOutputsValidJson(): void
    {
        // Arrange
        $spot = new SwimmingSpot('test-spot', 'Test Spot', 52.0, 4.0);
        $location = new RwsLocation('TEST', 'Test', 52.0, 4.0, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $measuredAt = new \DateTimeImmutable('2024-01-15 12:00:00', new \DateTimeZone('UTC'));

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(15.0),
            WaveHeight::fromMeters(0.3),
            WaterHeight::fromMeters(0.2),
            $measuredAt
        );

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(18.0),
            WindSpeed::fromMetersPerSecond(3.0),
            'N',
            Sunpower::fromWattsPerSquareMeter(400.0),
            $measuredAt
        );

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn($waterConditions);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn($weatherConditions);

        $tideInfo = new TideInfo([
            new TideEvent(TideType::High, new \DateTimeImmutable('08:00'), 160.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('14:00'), 40.0),
        ], new \DateTimeImmutable('10:00'));

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn($tideInfo);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute(['spot' => 'test-spot', '--json' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = trim($tester->getDisplay());
        $json = json_decode($output, true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('water', $json);
        $this->assertArrayHasKey('weather', $json);
        $this->assertArrayHasKey('tides', $json);
        $this->assertArrayHasKey('metrics', $json);
    }

    public function testExecuteDisplaysWaterTemperature(): void
    {
        // Arrange
        $spot = new SwimmingSpot('beach', 'Beach', 52.0, 4.0);
        $location = new RwsLocation('BCH', 'Beach', 52.0, 4.0, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(19.5),
            WaveHeight::fromMeters(0.4),
            WaterHeight::fromMeters(0.1),
            new \DateTimeImmutable()
        );

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(22.0),
            WindSpeed::fromMetersPerSecond(4.0),
            'SW',
            Sunpower::fromWattsPerSquareMeter(600.0),
            new \DateTimeImmutable()
        );

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn($waterConditions);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn($weatherConditions);

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn(null);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $tester->execute(['spot' => 'beach']);

        // Assert
        $output = $tester->getDisplay();
        $this->assertStringContainsString('19.5°C', $output);
        $this->assertStringContainsString('22°C', $output);
    }

    public function testExecuteHandlesNullWaterConditions(): void
    {
        // Arrange
        $spot = new SwimmingSpot('remote', 'Remote', 53.0, 5.0);
        $location = new RwsLocation('RMT', 'Remote', 53.0, 5.0, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn(null);

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(20.0),
            WindSpeed::fromMetersPerSecond(3.0),
            'E',
            Sunpower::fromWattsPerSquareMeter(400.0),
            new \DateTimeImmutable()
        );

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn($weatherConditions);

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn(null);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute(['spot' => 'remote']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Water Conditions', $tester->getDisplay());
    }

    public function testExecuteHandlesNullWeatherConditions(): void
    {
        // Arrange - this test covers line 127 (weather error display)
        $spot = new SwimmingSpot('no-weather', 'No Weather', 52.0, 4.0);
        $location = new RwsLocation('NW', 'No Weather', 52.0, 4.0, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.0),
            WaveHeight::fromMeters(0.5),
            WaterHeight::fromMeters(0.2),
            new \DateTimeImmutable()
        );

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn($waterConditions);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn(null);

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn(null);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute(['spot' => 'no-weather']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Weather Conditions', $output);
        $this->assertStringContainsString('Failed to fetch weather conditions', $output);
    }

    public function testExecuteDisplaysLargeWaveHeight(): void
    {
        // Arrange - this test covers line 243 (wave height >= 1m formatting)
        $spot = new SwimmingSpot('big-waves', 'Big Waves', 52.0, 4.0);
        $location = new RwsLocation('BW', 'Big Waves', 52.0, 4.0, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(15.0),
            WaveHeight::fromMeters(1.5), // >= 1m triggers different formatting
            WaterHeight::fromMeters(0.3),
            new \DateTimeImmutable()
        );

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(18.0),
            WindSpeed::fromMetersPerSecond(8.0),
            'NW',
            Sunpower::fromWattsPerSquareMeter(300.0),
            new \DateTimeImmutable()
        );

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn($waterConditions);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn($weatherConditions);

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn(null);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $tester->execute(['spot' => 'big-waves']);

        // Assert
        $output = $tester->getDisplay();
        $this->assertStringContainsString('1.5 m', $output); // >= 1m shows as "X.X m"
    }

    public function testExecuteWithUnknownWindSpeed(): void
    {
        // Arrange - this test covers line 212 (N/A for unknown wind speed)
        $spot = new SwimmingSpot('no-wind', 'No Wind Data', 52.0, 4.0);
        $location = new RwsLocation('NWD', 'No Wind Data', 52.0, 4.0, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.0),
            WaveHeight::fromMeters(0.5),
            WaterHeight::fromMeters(0.2),
            new \DateTimeImmutable()
        );

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(20.0),
            WindSpeed::unknown(), // Unknown wind speed
            'N',
            Sunpower::fromWattsPerSquareMeter(500.0),
            new \DateTimeImmutable()
        );

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn($waterConditions);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn($weatherConditions);

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn(null);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $tester->execute(['spot' => 'no-wind']);

        // Assert
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Wind Speed', $output);
    }

    public function testJsonOutputContainsAllTideData(): void
    {
        // Arrange - this test covers lines 317-320 (JSON tide next high/low)
        $spot = new SwimmingSpot('tides-json', 'Tides JSON', 52.0, 4.0);
        $location = new RwsLocation('TJ', 'Tides JSON', 52.0, 4.0, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(18.0),
            WaveHeight::fromMeters(0.5),
            WaterHeight::fromMeters(0.2),
            new \DateTimeImmutable('2024-01-15 12:00:00', new \DateTimeZone('UTC'))
        );

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(20.0),
            WindSpeed::fromMetersPerSecond(5.0),
            'W',
            Sunpower::fromWattsPerSquareMeter(500.0),
            new \DateTimeImmutable('2024-01-15 12:00:00', new \DateTimeZone('UTC'))
        );

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn($waterConditions);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn($weatherConditions);

        // Create tide info with all tide types
        $tideInfo = new TideInfo([
            new TideEvent(TideType::High, new \DateTimeImmutable('2024-01-15 06:00:00'), 180.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2024-01-15 12:30:00'), 30.0),
            new TideEvent(TideType::High, new \DateTimeImmutable('2024-01-15 18:00:00'), 175.0),
            new TideEvent(TideType::Low, new \DateTimeImmutable('2024-01-16 00:30:00'), 35.0),
        ], new \DateTimeImmutable('2024-01-15 12:00:00'));

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn($tideInfo);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $tester->execute(['spot' => 'tides-json', '--json' => true]);

        // Assert
        $json = json_decode(trim($tester->getDisplay()), true);

        $this->assertArrayHasKey('tides', $json);
        $this->assertArrayHasKey('previous', $json['tides']);
        $this->assertArrayHasKey('next', $json['tides']);
        $this->assertArrayHasKey('nextHigh', $json['tides']);
        $this->assertArrayHasKey('nextLow', $json['tides']);
    }

    public function testJsonOutputContainsMetrics(): void
    {
        // Arrange
        $spot = new SwimmingSpot('metrics-test', 'Metrics Test', 52.5, 4.5);
        $location = new RwsLocation('MT', 'Metrics Test', 52.5, 4.5, [], [], 'sea');

        $swimmingSpotRepository = $this->createMock(SwimmingSpotRepositoryInterface::class);
        $swimmingSpotRepository->method('findById')->willReturn($spot);

        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn([$location]);

        $waterConditions = new WaterConditions(
            $location,
            Temperature::fromCelsius(20.0),
            WaveHeight::fromMeters(0.5),
            WaterHeight::fromMeters(0.2),
            new \DateTimeImmutable()
        );

        $weatherConditions = new WeatherConditions(
            $location,
            Temperature::fromCelsius(22.0),
            WindSpeed::fromMetersPerSecond(4.0),
            'W',
            Sunpower::fromWattsPerSquareMeter(500.0),
            new \DateTimeImmutable()
        );

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn($waterConditions);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')->willReturn($weatherConditions);

        $tidalProvider = $this->createMock(TidalInfoProviderInterface::class);
        $tidalProvider->method('getTidalInfo')->willReturn(null);

        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);
        $weatherStationRepository->method('findAll')->willReturn([]);

        $useCase = $this->createUseCase(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            $weatherStationRepository
        );

        $command = new ConditionsCommand($useCase);
        $tester = new CommandTester($command);

        // Act
        $tester->execute(['spot' => 'metrics-test', '--json' => true]);

        // Assert
        $json = json_decode(trim($tester->getDisplay()), true);

        $this->assertArrayHasKey('metrics', $json);
        $this->assertArrayHasKey('safetyScore', $json['metrics']);
        $this->assertArrayHasKey('safetyLabel', $json['metrics']);
        $this->assertArrayHasKey('comfortIndex', $json['metrics']);
        $this->assertArrayHasKey('comfortLabel', $json['metrics']);

        // Verify safety score is one of the valid values
        $this->assertContains($json['metrics']['safetyScore'], ['green', 'yellow', 'red']);
        $this->assertIsInt($json['metrics']['comfortIndex']);
        $this->assertGreaterThanOrEqual(1, $json['metrics']['comfortIndex']);
        $this->assertLessThanOrEqual(10, $json['metrics']['comfortIndex']);
    }

    private function createUseCase(
        ?SwimmingSpotRepositoryInterface $swimmingSpotRepository = null,
        ?RwsLocationRepositoryInterface $locationRepository = null,
        ?WaterConditionsProviderInterface $waterProvider = null,
        ?WeatherConditionsProviderInterface $weatherProvider = null,
        ?TidalInfoProviderInterface $tidalProvider = null,
        ?WeatherStationRepositoryInterface $weatherStationRepository = null,
    ): GetConditionsForSwimmingSpot {
        $swimmingSpotRepository ??= $this->createMock(SwimmingSpotRepositoryInterface::class);
        $locationRepository ??= $this->createMock(RwsLocationRepositoryInterface::class);
        $waterProvider ??= $this->createMock(WaterConditionsProviderInterface::class);
        $weatherProvider ??= $this->createMock(WeatherConditionsProviderInterface::class);
        $tidalProvider ??= $this->createMock(TidalInfoProviderInterface::class);
        $weatherStationRepository ??= $this->createMock(WeatherStationRepositoryInterface::class);

        $blacklist = new LocationBlacklist($this->tempDir.'/blacklist.txt');

        return new GetConditionsForSwimmingSpot(
            $swimmingSpotRepository,
            $locationRepository,
            $waterProvider,
            $weatherProvider,
            $tidalProvider,
            new SafetyScoreCalculator(),
            new ComfortIndexCalculator(),
            new NearestRwsLocationMatcher($locationRepository, $blacklist),
            new NearestRwsLocationFinder($blacklist),
            new WeatherStationMatcher($weatherStationRepository),
        );
    }
}
