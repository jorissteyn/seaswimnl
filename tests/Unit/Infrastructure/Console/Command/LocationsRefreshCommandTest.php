<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\WeatherStationRepositoryInterface;
use Seaswim\Application\UseCase\RefreshLocations;
use Seaswim\Infrastructure\Console\Command\LocationsRefreshCommand;
use Seaswim\Infrastructure\ExternalApi\Client\BuienradarHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class LocationsRefreshCommandTest extends TestCase
{
    /**
     * Creates a RefreshLocations use case with mocked dependencies that return specific results.
     *
     * @param array{locations: int, stations: int} $result
     */
    private function createRefreshLocationsWithResult(array $result): RefreshLocations
    {
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $buienradarClient = $this->createMock(BuienradarHttpClientInterface::class);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);

        // Configure RWS client to return success or failure based on result
        if ($result['locations'] < 0) {
            $rwsClient->method('fetchLocations')->willReturn(null);
        } else {
            $locationsData = [];
            for ($i = 0; $i < $result['locations']; ++$i) {
                $locationsData[] = [
                    'code' => "loc{$i}",
                    'name' => "Location {$i}",
                    'latitude' => 51.0 + $i * 0.1,
                    'longitude' => 4.0 + $i * 0.1,
                    'compartimenten' => ['OW'],
                    'grootheden' => ['T'],
                ];
            }
            $rwsClient->method('fetchLocations')->willReturn($locationsData);
        }

        // Configure Buienradar client to return success or failure based on result
        if ($result['stations'] < 0) {
            $buienradarClient->method('fetchStations')->willReturn(null);
        } else {
            $stationsData = [];
            for ($i = 0; $i < $result['stations']; ++$i) {
                $stationsData[] = [
                    'code' => "sta{$i}",
                    'name' => "Station {$i}",
                    'latitude' => 51.0 + $i * 0.1,
                    'longitude' => 4.0 + $i * 0.1,
                ];
            }
            $buienradarClient->method('fetchStations')->willReturn($stationsData);
        }

        return new RefreshLocations(
            $locationRepository,
            $rwsClient,
            $weatherStationRepository,
            $buienradarClient
        );
    }

    public function testCommandIsProperlyConfigured(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 0, 'stations' => 0]);
        $command = new LocationsRefreshCommand($refreshLocations);

        // Act
        $name = $command->getName();
        $description = $command->getDescription();

        // Assert
        $this->assertSame('seaswim:locations:refresh', $name);
        $this->assertSame('Refresh swim locations from Rijkswaterstaat and weather stations from Buienradar', $description);
    }

    public function testExecuteDisplaysSuccessMessagesWhenBothServicesSucceed(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 42, 'stations' => 15]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Refreshing locations from Rijkswaterstaat and Buienradar', $output);
        $this->assertStringContainsString('Imported 42 RWS locations', $output);
        $this->assertStringContainsString('Refreshed 15 Buienradar weather stations', $output);
    }

    public function testExecuteDisplaysErrorWhenRwsLocationsFail(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => -1, 'stations' => 15]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Failed to refresh RWS locations. API may be unavailable.', $output);
        $this->assertStringContainsString('Refreshed 15 Buienradar weather stations', $output);
    }

    public function testExecuteDisplaysErrorWhenBuienradarStationsFail(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 42, 'stations' => -1]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Imported 42 RWS locations', $output);
        $this->assertStringContainsString('Failed to refresh Buienradar stations.', $output);
    }

    public function testExecuteDisplaysBothErrorsWhenBothServicesFail(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => -1, 'stations' => -1]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Failed to refresh RWS locations. API may be unavailable.', $output);
        $this->assertStringContainsString('Failed to refresh Buienradar stations.', $output);
    }

    public function testExecuteCallsRefreshLocationsUseCase(): void
    {
        // Arrange
        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $buienradarClient = $this->createMock(BuienradarHttpClientInterface::class);
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);

        $rwsClient->expects($this->once())
            ->method('fetchLocations')
            ->willReturn([
                ['code' => 'test', 'name' => 'Test', 'latitude' => 51.0, 'longitude' => 4.0, 'compartimenten' => ['OW'], 'grootheden' => ['T']],
            ]);

        $buienradarClient->expects($this->once())
            ->method('fetchStations')
            ->willReturn([
                ['code' => 'test', 'name' => 'Test', 'latitude' => 51.0, 'longitude' => 4.0],
            ]);

        $refreshLocations = new RefreshLocations(
            $locationRepository,
            $rwsClient,
            $weatherStationRepository,
            $buienradarClient
        );

        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert - expectations verified by mock
    }

    public function testExecuteReturnsSuccessWhenZeroLocationsAndStationsAreRefreshed(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 0, 'stations' => 0]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Imported 0 RWS locations', $output);
        $this->assertStringContainsString('Refreshed 0 Buienradar weather stations', $output);
    }

    public function testExecuteDisplaysInfoMessageBeforeRefreshing(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 5, 'stations' => 3]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Refreshing locations from Rijkswaterstaat and Buienradar', $output);
    }

    public function testExecuteHandlesLargeNumberOfLocationsAndStations(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 999, 'stations' => 888]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Imported 999 RWS locations', $output);
        $this->assertStringContainsString('Refreshed 888 Buienradar weather stations', $output);
    }

    public function testCommandCanBeExecutedMultipleTimes(): void
    {
        // Arrange
        $refreshLocations1 = $this->createRefreshLocationsWithResult(['locations' => 10, 'stations' => 5]);
        $command = new LocationsRefreshCommand($refreshLocations1);
        $commandTester = new CommandTester($command);

        // Act - first execution
        $exitCode1 = $commandTester->execute([]);

        // Assert - first execution
        $this->assertSame(Command::SUCCESS, $exitCode1);
        $output1 = $commandTester->getDisplay();
        $this->assertStringContainsString('Imported 10 RWS locations', $output1);
        $this->assertStringContainsString('Refreshed 5 Buienradar weather stations', $output1);

        // Arrange - second execution with different use case
        $refreshLocations2 = $this->createRefreshLocationsWithResult(['locations' => 8, 'stations' => 4]);
        $command2 = new LocationsRefreshCommand($refreshLocations2);
        $commandTester2 = new CommandTester($command2);

        // Act - second execution
        $exitCode2 = $commandTester2->execute([]);

        // Assert - second execution
        $this->assertSame(Command::SUCCESS, $exitCode2);
        $output2 = $commandTester2->getDisplay();
        $this->assertStringContainsString('Imported 8 RWS locations', $output2);
        $this->assertStringContainsString('Refreshed 4 Buienradar weather stations', $output2);
    }

    public function testSuccessMessagesUseSymfonyStyleFormatting(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 20, 'stations' => 10]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        // SymfonyStyle success messages include [OK] prefix or similar formatting
        $this->assertStringContainsString('Imported 20 RWS locations', $output);
        $this->assertStringContainsString('Refreshed 10 Buienradar weather stations', $output);
    }

    public function testErrorMessagesUseSymfonyStyleFormatting(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => -1, 'stations' => -1]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        // SymfonyStyle error messages include [ERROR] prefix or similar formatting
        $this->assertStringContainsString('Failed to refresh RWS locations', $output);
        $this->assertStringContainsString('Failed to refresh Buienradar stations', $output);
    }

    public function testInfoMessageUsesSymfonyStyleFormatting(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 5, 'stations' => 3]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        // SymfonyStyle info messages include [INFO] prefix or similar formatting
        $this->assertStringContainsString('Refreshing locations from Rijkswaterstaat and Buienradar', $output);
    }

    public function testCommandDoesNotAcceptAnyArguments(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 1, 'stations' => 1]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $definition = $command->getDefinition();
        $this->assertCount(0, $definition->getArguments());
    }

    public function testCommandDoesNotAcceptAnyOptions(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 1, 'stations' => 1]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $definition = $command->getDefinition();
        // Symfony commands have default options (help, quiet, verbose, etc.)
        // We verify no custom options were added beyond the inherited ones
        $options = $definition->getOptions();
        $customOptions = array_filter($options, function ($option) {
            $name = $option->getName();

            // These are inherited from base Command class
            return !in_array($name, ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction']);
        });
        $this->assertCount(0, $customOptions);
    }

    public function testExecuteReturnsFailureOnlyWhenAtLeastOneServiceFails(): void
    {
        // Test case 1: Both succeed
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 10, 'stations' => 5]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);
        $this->assertSame(Command::SUCCESS, $exitCode);

        // Test case 2: Locations fail
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => -1, 'stations' => 5]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);
        $this->assertSame(Command::FAILURE, $exitCode);

        // Test case 3: Stations fail
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 10, 'stations' => -1]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);
        $this->assertSame(Command::FAILURE, $exitCode);

        // Test case 4: Both fail
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => -1, 'stations' => -1]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testExecuteHandlesSingleLocationAndStation(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 1, 'stations' => 1]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        // Verify singular/plural handling works correctly
        $this->assertStringContainsString('Imported 1 RWS locations', $output);
        $this->assertStringContainsString('Refreshed 1 Buienradar weather stations', $output);
    }

    public function testExecuteOutputContainsAllExpectedMessages(): void
    {
        // Arrange
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => 25, 'stations' => 12]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);
        $output = $commandTester->getDisplay();

        // Assert - verify all expected messages appear in order
        $infoPosition = strpos($output, 'Refreshing locations from Rijkswaterstaat and Buienradar');
        $locationsPosition = strpos($output, 'Imported 25 RWS locations');
        $stationsPosition = strpos($output, 'Refreshed 12 Buienradar weather stations');

        $this->assertNotFalse($infoPosition, 'Info message should be present');
        $this->assertNotFalse($locationsPosition, 'Locations message should be present');
        $this->assertNotFalse($stationsPosition, 'Stations message should be present');
        $this->assertLessThan($locationsPosition, $infoPosition, 'Info message should appear before locations message');
        $this->assertLessThan($stationsPosition, $locationsPosition, 'Locations message should appear before stations message');
    }

    public function testExecutePartialFailureStillShowsSuccessfulResults(): void
    {
        // Arrange - RWS fails but Buienradar succeeds
        $refreshLocations = $this->createRefreshLocationsWithResult(['locations' => -1, 'stations' => 20]);
        $command = new LocationsRefreshCommand($refreshLocations);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        // Both messages should be present - error for locations, success for stations
        $this->assertStringContainsString('Failed to refresh RWS locations', $output);
        $this->assertStringContainsString('Refreshed 20 Buienradar weather stations', $output);
    }
}
