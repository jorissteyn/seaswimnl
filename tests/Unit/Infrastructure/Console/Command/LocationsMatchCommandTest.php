<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\WeatherStationRepositoryInterface;
use Seaswim\Domain\Service\WeatherStationMatcher;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\WeatherStation;
use Seaswim\Infrastructure\Console\Command\LocationsMatchCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class LocationsMatchCommandTest extends TestCase
{
    private function createLocationRepositoryMock(array $locations = []): RwsLocationRepositoryInterface
    {
        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findAll')->willReturn($locations);

        return $repository;
    }

    private function createStationMatcher(array $stations = []): WeatherStationMatcher
    {
        $repository = $this->createMock(WeatherStationRepositoryInterface::class);
        $repository->method('findAll')->willReturn($stations);

        return new WeatherStationMatcher($repository);
    }

    public function testCommandIsProperlyConfigured(): void
    {
        // Arrange
        $repository = $this->createLocationRepositoryMock();
        $matcher = $this->createStationMatcher();
        $command = new LocationsMatchCommand($repository, $matcher);

        // Act
        $name = $command->getName();
        $description = $command->getDescription();

        // Assert
        $this->assertSame('seaswim:locations:match', $name);
        $this->assertSame('Show the nearest weather station for an RWS location', $description);
    }

    public function testCommandRequiresLocationArgument(): void
    {
        // Arrange
        $repository = $this->createLocationRepositoryMock();
        $matcher = $this->createStationMatcher();
        $command = new LocationsMatchCommand($repository, $matcher);

        // Act
        $definition = $command->getDefinition();

        // Assert
        $this->assertTrue($definition->hasArgument('location'));
        $this->assertTrue($definition->getArgument('location')->isRequired());
        $this->assertSame('RWS location code or name', $definition->getArgument('location')->getDescription());
    }

    public function testExecuteFindsLocationByIdAndDisplaysMatch(): void
    {
        // Arrange
        $location = new RwsLocation('HOEKVHLD', 'Hoek van Holland', 51.9775, 4.1225);
        $station = new WeatherStation('344', 'Rotterdam', 51.962, 4.447);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with('HOEKVHLD')
            ->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'HOEKVHLD']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('RWS Location', $output);
        $this->assertStringContainsString('HOEKVHLD', $output);
        $this->assertStringContainsString('Hoek van Holland', $output);
        $this->assertStringContainsString('51.9775', $output);
        $this->assertStringContainsString('4.1225', $output);
        $this->assertStringContainsString('Nearest Weather Station', $output);
        $this->assertStringContainsString('344', $output);
        $this->assertStringContainsString('Rotterdam', $output);
        $this->assertStringContainsString('km', $output);
    }

    public function testExecuteSearchesByNameWhenLocationNotFoundById(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Amsterdam Haven', 52.3676, 4.9041);
        $location2 = new RwsLocation('HOEKVHLD', 'Hoek van Holland', 51.9775, 4.1225);
        $station = new WeatherStation('344', 'Rotterdam', 51.962, 4.447);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with('hoek')
            ->willReturn(null);
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2]);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'hoek']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('HOEKVHLD', $output);
        $this->assertStringContainsString('Hoek van Holland', $output);
    }

    public function testExecuteSearchesByIdCaseInsensitively(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Amsterdam Haven', 52.3676, 4.9041);
        $location2 = new RwsLocation('HOEKVHLD', 'Hoek van Holland', 51.9775, 4.1225);
        $station = new WeatherStation('344', 'Rotterdam', 51.962, 4.447);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with('hoekvhld')
            ->willReturn(null);
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2]);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'hoekvhld']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('HOEKVHLD', $output);
    }

    public function testExecuteReturnsFailureWhenLocationNotFound(): void
    {
        // Arrange
        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with('UNKNOWN')
            ->willReturn(null);
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $matcher = $this->createStationMatcher();

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'UNKNOWN']);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Location "UNKNOWN" not found', $output);
    }

    public function testExecuteDisplaysWarningWhenNoWeatherStationsFound(): void
    {
        // Arrange
        $location = new RwsLocation('HOEKVHLD', 'Hoek van Holland', 51.9775, 4.1225);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with('HOEKVHLD')
            ->willReturn($location);

        // Create matcher with no stations
        $matcher = $this->createStationMatcher([]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'HOEKVHLD']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('RWS Location', $output);
        $this->assertStringContainsString('HOEKVHLD', $output);
        $this->assertStringContainsString('No weather stations found', $output);
    }

    public function testExecuteFindsFirstMatchingLocationByPartialName(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Rotterdam Haven', 51.9, 4.5);
        $location2 = new RwsLocation('LOC002', 'Rotterdam Centrum', 51.92, 4.48);
        $location3 = new RwsLocation('LOC003', 'Amsterdam Haven', 52.3676, 4.9041);
        $station = new WeatherStation('344', 'Rotterdam', 51.962, 4.447);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with('rotterdam')
            ->willReturn(null);
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location1, $location2, $location3]);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'rotterdam']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('LOC001', $output);
        $this->assertStringContainsString('Rotterdam Haven', $output);
    }

    public function testExecuteDisplaysLocationTableWithCorrectHeaders(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.1, 4.6);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'TEST']);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Latitude', $output);
        $this->assertStringContainsString('Longitude', $output);
    }

    public function testExecuteDisplaysWeatherStationTableWithCorrectHeaders(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.1, 4.6);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'TEST']);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Code', $output);
        $this->assertStringContainsString('Distance', $output);
    }

    public function testExecuteFormatsDistanceWithOneDecimalPlace(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.1, 4.6);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'TEST']);

        // Assert
        $output = $commandTester->getDisplay();
        // The WeatherStationMatcher rounds to 1 decimal place
        $this->assertStringContainsString('km', $output);
        $this->assertMatchesRegularExpression('/\d+\.\d km/', $output);
    }

    public function testExecuteHandlesLocationWithSpecialCharactersInName(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test-Location (Noord)', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.1, 4.6);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'TEST']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Test-Location (Noord)', $output);
    }

    public function testExecuteHandlesLocationWithNegativeCoordinates(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', -52.0, -4.5);
        $station = new WeatherStation('123', 'Test Station', -52.1, -4.6);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'TEST']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('-52', $output);
        $this->assertStringContainsString('-4.5', $output);
    }

    public function testExecuteDisplaysOutputInCorrectOrder(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.1, 4.6);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'TEST']);
        $output = $commandTester->getDisplay();

        // Assert
        $locationSectionPos = strpos($output, 'RWS Location');
        $stationSectionPos = strpos($output, 'Nearest Weather Station');

        $this->assertNotFalse($locationSectionPos, 'RWS Location section should be present');
        $this->assertNotFalse($stationSectionPos, 'Nearest Weather Station section should be present');
        $this->assertLessThan($stationSectionPos, $locationSectionPos, 'RWS Location section should appear before Weather Station section');
    }

    public function testCommandDoesNotAcceptAnyOptions(): void
    {
        // Arrange
        $repository = $this->createLocationRepositoryMock();
        $matcher = $this->createStationMatcher();
        $command = new LocationsMatchCommand($repository, $matcher);

        // Act
        $definition = $command->getDefinition();

        // Assert
        $options = $definition->getOptions();
        $customOptions = array_filter($options, function ($option) {
            $name = $option->getName();

            // These are inherited from base Command class
            return !in_array($name, ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction']);
        });
        $this->assertCount(0, $customOptions);
    }

    public function testExecuteUsesSymfonyStyleFormatting(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.1, 4.6);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'TEST']);

        // Assert - SymfonyStyle outputs tables with section headers and borders
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('RWS Location', $output);
        $this->assertStringContainsString('Nearest Weather Station', $output);
        // Check for table-like structure (rows with dashes for borders)
        $this->assertMatchesRegularExpression('/[-─]+/', $output, 'Output should contain table formatting');
    }

    public function testExecuteSearchMatchesPartOfLocationId(): void
    {
        // Arrange
        $location1 = new RwsLocation('AMSTERDAM_001', 'Amsterdam 1', 52.3, 4.9);
        $location2 = new RwsLocation('ROTTERDAM_001', 'Rotterdam 1', 51.9, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.4, 4.8);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->method('findAll')->willReturn([$location1, $location2]);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'AMSTERDAM']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('AMSTERDAM_001', $output);
        $this->assertStringContainsString('Amsterdam 1', $output);
    }

    public function testExecuteHandlesVerySmallDistance(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.001, 4.501);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'TEST']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('km', $output);
    }

    public function testExecuteHandlesVeryLargeDistance(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 40.0, -3.0);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'TEST']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('km', $output);
    }

    public function testExecuteErrorMessageIncludesSearchQuery(): void
    {
        // Arrange
        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->method('findAll')->willReturn([]);

        $matcher = $this->createStationMatcher();

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'NONEXISTENT_LOCATION']);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('NONEXISTENT_LOCATION', $output);
        $this->assertStringContainsString('not found', $output);
    }

    public function testExecuteSearchIsPartialAndCaseInsensitive(): void
    {
        // Arrange
        $location = new RwsLocation('HOEKVHLD', 'Hoek van Holland', 51.9775, 4.1225);
        $station = new WeatherStation('344', 'Rotterdam', 51.962, 4.447);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->method('findAll')->willReturn([$location]);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act - search using different case and partial name
        $exitCode = $commandTester->execute(['location' => 'VAN HOLLAND']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('HOEKVHLD', $output);
        $this->assertStringContainsString('Hoek van Holland', $output);
    }

    public function testExecuteHandlesZeroDistance(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station = new WeatherStation('123', 'Test Station', 52.0, 4.5);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'TEST']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('0.0 km', $output);
    }

    public function testExecuteStopsSearchingAfterFirstMatch(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'First Rotterdam', 51.9, 4.5);
        $location2 = new RwsLocation('LOC002', 'Second Rotterdam', 51.92, 4.48);
        $station = new WeatherStation('123', 'Test Station', 51.95, 4.52);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->method('findAll')->willReturn([$location1, $location2]);

        $matcher = $this->createStationMatcher([$station]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'rotterdam']);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('First Rotterdam', $output);
        $this->assertStringNotContainsString('Second Rotterdam', $output);
    }

    public function testExecuteHandlesMultipleStationsAndFindsNearest(): void
    {
        // Arrange
        $location = new RwsLocation('TEST', 'Test Location', 52.0, 4.5);
        $station1 = new WeatherStation('123', 'Far Station', 51.0, 3.0);
        $station2 = new WeatherStation('456', 'Near Station', 52.01, 4.51);
        $station3 = new WeatherStation('789', 'Medium Station', 52.5, 5.0);

        $repository = $this->createMock(RwsLocationRepositoryInterface::class);
        $repository->method('findById')->willReturn($location);

        $matcher = $this->createStationMatcher([$station1, $station2, $station3]);

        $command = new LocationsMatchCommand($repository, $matcher);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'TEST']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('456', $output);
        $this->assertStringContainsString('Near Station', $output);
    }
}
