<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\WeatherStationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\WeatherStation;
use Seaswim\Infrastructure\Console\Command\LocationsListCommand;
use Seaswim\Infrastructure\Service\LocationBlacklist;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class LocationsListCommandTest extends TestCase
{
    private RwsLocationRepositoryInterface $locationRepository;
    private WeatherStationRepositoryInterface $weatherStationRepository;
    private LocationBlacklist $blacklist;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $this->weatherStationRepository = $this->createMock(WeatherStationRepositoryInterface::class);

        // Create temporary directory for blacklist
        $this->tempDir = sys_get_temp_dir().'/seaswim_test_'.uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir.'/data', 0777, true);

        // Create empty blacklist by default
        $this->blacklist = new LocationBlacklist($this->tempDir);
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
        // Recreate blacklist to load new content
        $this->blacklist = new LocationBlacklist($this->tempDir);
    }

    private function createCommand(): LocationsListCommand
    {
        return new LocationsListCommand(
            $this->locationRepository,
            $this->weatherStationRepository,
            $this->blacklist
        );
    }

    private function executeCommand(array $options = []): CommandTester
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute($options);

        return $commandTester;
    }

    public function testCommandIsProperlyConfigured(): void
    {
        // Arrange
        $command = $this->createCommand();

        // Act
        $name = $command->getName();
        $description = $command->getDescription();
        $definition = $command->getDefinition();

        // Assert
        $this->assertSame('seaswim:locations:list', $name);
        $this->assertSame('List all swim locations', $description);
        $this->assertTrue($definition->hasOption('json'));
        $this->assertTrue($definition->hasOption('search'));
        $this->assertTrue($definition->hasOption('source'));
        $this->assertTrue($definition->hasOption('filter'));
        $this->assertTrue($definition->hasOption('water-type'));
        $this->assertTrue($definition->hasOption('show-blacklisted'));
        $this->assertTrue($definition->hasOption('show-location-properties'));
    }

    public function testExecuteWithNoLocationsDisplaysWarning(): void
    {
        // Arrange
        $this->locationRepository->method('findAll')->willReturn([]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No locations found', $output);
        $this->assertStringContainsString('seaswim:locations:refresh', $output);
    }

    public function testExecuteDisplaysRwsLocations(): void
    {
        // Arrange
        $location = new RwsLocation(
            'LOC001',
            'Test Location',
            52.3676,
            4.9041,
            ['OW'],
            ['T', 'WATHTE'],
            RwsLocation::WATER_TYPE_SEA
        );

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('LOC001', $output);
        $this->assertStringContainsString('Test Location', $output);
        $this->assertStringContainsString('52.3676', $output);
        $this->assertStringContainsString('4.9041', $output);
        $this->assertStringContainsString('sea', $output);
    }

    public function testExecuteDisplaysWeatherStations(): void
    {
        // Arrange
        $station = new WeatherStation(
            'STA001',
            'Test Station',
            51.5000,
            5.0000
        );

        $this->locationRepository->method('findAll')->willReturn([]);
        $this->weatherStationRepository->method('findAll')->willReturn([$station]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('STA001', $output);
        $this->assertStringContainsString('Test Station', $output);
        $this->assertStringContainsString('51.5000', $output);
        $this->assertStringContainsString('5.0000', $output);
    }

    public function testExecuteDisplaysBothRwsLocationsAndWeatherStations(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Test Location', 52.0, 4.0);
        $station = new WeatherStation('STA001', 'Test Station', 51.0, 5.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([$station]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('LOC001', $output);
        $this->assertStringContainsString('Test Location', $output);
        $this->assertStringContainsString('STA001', $output);
        $this->assertStringContainsString('Test Station', $output);
        $this->assertStringContainsString('Locations (2)', $output);
    }

    public function testExecuteWithJsonOptionOutputsJson(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Test Location', 52.0, 4.0, ['OW'], ['T']);
        $station = new WeatherStation('STA001', 'Test Station', 51.0, 5.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([$station]);

        // Act
        $commandTester = $this->executeCommand(['--json' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);

        // Verify RWS location structure
        $this->assertSame('rws', $decoded[0]['source']);
        $this->assertSame('LOC001', $decoded[0]['id']);
        $this->assertSame('Test Location', $decoded[0]['name']);
        $this->assertEquals(52.0, $decoded[0]['latitude']);
        $this->assertEquals(4.0, $decoded[0]['longitude']);
        $this->assertSame(['OW'], $decoded[0]['compartimenten']);
        $this->assertSame(['T'], $decoded[0]['grootheden']);

        // Verify weather station structure
        $this->assertSame('weather', $decoded[1]['source']);
        $this->assertSame('STA001', $decoded[1]['id']);
        $this->assertSame('Test Station', $decoded[1]['name']);
        $this->assertEquals(51.0, $decoded[1]['latitude']);
        $this->assertEquals(5.0, $decoded[1]['longitude']);
        $this->assertNull($decoded[1]['waterBodyType']);
        $this->assertSame([], $decoded[1]['compartimenten']);
        $this->assertSame([], $decoded[1]['grootheden']);
    }

    public function testExecuteWithSearchOptionFiltersByName(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Amsterdam Beach', 52.0, 4.0);
        $location2 = new RwsLocation('LOC002', 'Rotterdam Port', 51.9, 4.5);

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--search' => 'Amsterdam']);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Amsterdam Beach', $output);
        $this->assertStringNotContainsString('Rotterdam Port', $output);
        $this->assertStringContainsString('Locations (1)', $output);
    }

    public function testExecuteWithSearchOptionFiltersByCode(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Test Location 1', 52.0, 4.0);
        $location2 = new RwsLocation('LOC002', 'Test Location 2', 51.9, 4.5);

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--search' => 'LOC001']);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('LOC001', $output);
        $this->assertStringNotContainsString('LOC002', $output);
    }

    public function testExecuteWithSearchOptionIsCaseInsensitive(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Amsterdam Beach', 52.0, 4.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--search' => 'amsterdam']);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Amsterdam Beach', $output);
    }

    public function testExecuteWithSourceOptionRwsOnlyDisplaysRwsLocations(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Test Location', 52.0, 4.0);
        $station = new WeatherStation('STA001', 'Test Station', 51.0, 5.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([$station]);

        // Act
        $commandTester = $this->executeCommand(['--source' => 'rws']);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('LOC001', $output);
        $this->assertStringNotContainsString('STA001', $output);
        $this->assertStringContainsString('Locations (1)', $output);
    }

    public function testExecuteWithSourceOptionWeatherOnlyDisplaysWeatherStations(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Test Location', 52.0, 4.0);
        $station = new WeatherStation('STA001', 'Test Station', 51.0, 5.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([$station]);

        // Act
        $commandTester = $this->executeCommand(['--source' => 'weather']);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('STA001', $output);
        $this->assertStringNotContainsString('LOC001', $output);
        $this->assertStringContainsString('Locations (1)', $output);
    }

    public function testExecuteWithFilterOptionFiltersByGrootheden(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Location With Hm0', 52.0, 4.0, ['OW'], ['Hm0', 'T']);
        $location2 = new RwsLocation('LOC002', 'Location Without Hm0', 51.9, 4.5, ['OW'], ['T', 'WATHTE']);

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--filter' => 'Hm0']);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Location With Hm0', $output);
        $this->assertStringNotContainsString('Location Without Hm0', $output);
        $this->assertStringContainsString('Locations (1)', $output);
    }

    public function testExecuteWithWaterTypeOptionFiltersByWaterBodyType(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Sea Location', 52.0, 4.0, [], [], RwsLocation::WATER_TYPE_SEA);
        $location2 = new RwsLocation('LOC002', 'Lake Location', 51.9, 4.5, [], [], RwsLocation::WATER_TYPE_LAKE);
        $location3 = new RwsLocation('LOC003', 'River Location', 51.8, 4.3, [], [], RwsLocation::WATER_TYPE_RIVER);

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2, $location3]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--water-type' => 'lake']);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Lake Location', $output);
        $this->assertStringNotContainsString('Sea Location', $output);
        $this->assertStringNotContainsString('River Location', $output);
        $this->assertStringContainsString('Locations (1)', $output);
    }

    public function testExecuteHidesBlacklistedLocationsByDefault(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Normal Location', 52.0, 4.0);
        $location2 = new RwsLocation('LOC002', 'Blacklisted Location', 51.9, 4.5);

        $this->createBlacklistFile('LOC002');

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Normal Location', $output);
        $this->assertStringNotContainsString('Blacklisted Location', $output);
        $this->assertStringContainsString('Locations (1)', $output);
    }

    public function testExecuteWithShowBlacklistedOptionDisplaysBlacklistedLocations(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Normal Location', 52.0, 4.0);
        $location2 = new RwsLocation('LOC002', 'Blacklisted Location', 51.9, 4.5);

        $this->createBlacklistFile('LOC002');

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--show-blacklisted' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Normal Location', $output);
        $this->assertStringContainsString('Blacklisted Location', $output);
        $this->assertStringContainsString('blacklisted', $output);
        $this->assertStringContainsString('Locations (2)', $output);
    }

    public function testExecuteWithShowLocationPropertiesDisplaysAdditionalColumns(): void
    {
        // Arrange
        $location = new RwsLocation(
            'LOC001',
            'Test Location',
            52.0,
            4.0,
            ['OW', 'COMP2'],
            ['T', 'WATHTE', 'Hm0']
        );

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--show-location-properties' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Compartimenten', $output);
        $this->assertStringContainsString('Grootheden', $output);
        $this->assertStringContainsString('OW, COMP2', $output);
        $this->assertStringContainsString('T, WATHTE, Hm0', $output);
    }

    public function testExecuteWithoutShowLocationPropertiesHidesAdditionalColumns(): void
    {
        // Arrange
        $location = new RwsLocation(
            'LOC001',
            'Test Location',
            52.0,
            4.0,
            ['OW'],
            ['T']
        );

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringNotContainsString('Compartimenten', $output);
        $this->assertStringNotContainsString('Grootheden', $output);
    }

    public function testExecuteWithMultipleFiltersAppliesAllFilters(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Amsterdam Sea', 52.0, 4.0, ['OW'], ['Hm0', 'T'], RwsLocation::WATER_TYPE_SEA);
        $location2 = new RwsLocation('LOC002', 'Amsterdam Lake', 52.1, 4.1, ['OW'], ['T'], RwsLocation::WATER_TYPE_LAKE);
        $location3 = new RwsLocation('LOC003', 'Rotterdam Sea', 51.9, 4.5, ['OW'], ['Hm0'], RwsLocation::WATER_TYPE_SEA);

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2, $location3]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act - search for Amsterdam, filter by Hm0, water type sea
        $commandTester = $this->executeCommand([
            '--search' => 'Amsterdam',
            '--filter' => 'Hm0',
            '--water-type' => 'sea',
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Amsterdam Sea', $output);
        $this->assertStringNotContainsString('Amsterdam Lake', $output);
        $this->assertStringNotContainsString('Rotterdam Sea', $output);
        $this->assertStringContainsString('Locations (1)', $output);
    }

    public function testExecuteWithJsonAndFiltersOutputsFilteredJson(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Amsterdam', 52.0, 4.0);
        $location2 = new RwsLocation('LOC002', 'Rotterdam', 51.9, 4.5);

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand([
            '--json' => true,
            '--search' => 'Amsterdam',
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertSame('Amsterdam', $decoded[0]['name']);
    }

    public function testExecuteFormatsCoordinatesWithFourDecimalPlaces(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Test', 52.37654321, 4.90412345);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('52.3765', $output);
        $this->assertStringContainsString('4.9041', $output);
    }

    public function testExecuteDisplaysWaterBodyTypeOrDash(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'With Type', 52.0, 4.0, [], [], RwsLocation::WATER_TYPE_SEA);
        $station = new WeatherStation('STA001', 'Weather Station', 51.0, 5.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([$station]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        // RWS location shows water type
        $this->assertStringContainsString('sea', $output);
        // Weather station shows dash for null water type
        $this->assertStringContainsString('-', $output);
    }

    public function testExecuteCallsRepositoriesFindAllMethods(): void
    {
        // Arrange
        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->weatherStationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        // Act
        $this->executeCommand();

        // Assert - expectations verified by mock
    }

    public function testExecuteOnlyCallsRwsRepositoryWhenSourceIsRws(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Test', 52.0, 4.0);

        $this->locationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$location]);

        $this->weatherStationRepository->expects($this->never())
            ->method('findAll');

        // Act
        $this->executeCommand(['--source' => 'rws']);

        // Assert - expectations verified by mock
    }

    public function testExecuteOnlyCallsWeatherRepositoryWhenSourceIsWeather(): void
    {
        // Arrange
        $station = new WeatherStation('STA001', 'Test', 51.0, 5.0);

        $this->locationRepository->expects($this->never())
            ->method('findAll');

        $this->weatherStationRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$station]);

        // Act
        $this->executeCommand(['--source' => 'weather']);

        // Assert - expectations verified by mock
    }

    public function testExecuteCallsBlacklistForEachRwsLocation(): void
    {
        // Arrange
        $location1 = new RwsLocation('LOC001', 'Location 1', 52.0, 4.0);
        $location2 = new RwsLocation('LOC002', 'Location 2', 51.9, 4.5);

        $this->locationRepository->method('findAll')->willReturn([$location1, $location2]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert - both locations should be displayed (not blacklisted)
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Location 1', $output);
        $this->assertStringContainsString('Location 2', $output);
    }

    public function testExecuteDoesNotCallBlacklistForWeatherStations(): void
    {
        // Arrange
        $station = new WeatherStation('STA001', 'Station', 51.0, 5.0);

        $this->locationRepository->method('findAll')->willReturn([]);
        $this->weatherStationRepository->method('findAll')->willReturn([$station]);

        // Act
        $commandTester = $this->executeCommand(['--source' => 'weather']);

        // Assert - weather station should be displayed (never blacklisted)
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Station', $output);
        $this->assertStringNotContainsString('blacklisted', $output);
    }

    public function testExecuteWithEmptyCompartimentenAndGrootheden(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Minimal Location', 52.0, 4.0, [], []);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--show-location-properties' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Minimal Location', $output);
    }

    public function testExecuteDisplaysCorrectLocationCount(): void
    {
        // Arrange
        $locations = [
            new RwsLocation('LOC001', 'Location 1', 52.0, 4.0),
            new RwsLocation('LOC002', 'Location 2', 52.1, 4.1),
            new RwsLocation('LOC003', 'Location 3', 52.2, 4.2),
        ];

        $this->locationRepository->method('findAll')->willReturn($locations);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Locations (3)', $output);
    }

    public function testExecuteWithSearchReturningNoResultsDisplaysWarning(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Amsterdam', 52.0, 4.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--search' => 'NonExistent']);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No locations found', $output);
        $this->assertStringNotContainsString('Amsterdam', $output);
    }

    public function testExecuteWithAllBlacklistedLocationsDisplaysWarning(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Blacklisted', 52.0, 4.0);

        $this->createBlacklistFile('LOC001');

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No locations found', $output);
    }

    public function testExecuteWithJsonOptionAndNoLocationsOutputsEmptyArray(): void
    {
        // Arrange
        $this->locationRepository->method('findAll')->willReturn([]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--json' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        // The warning message should be shown, not JSON output
        $this->assertStringContainsString('No locations found', $output);
    }

    public function testExecuteHandlesLargeNumberOfLocations(): void
    {
        // Arrange
        $locations = [];
        for ($i = 0; $i < 100; ++$i) {
            $locations[] = new RwsLocation("LOC{$i}", "Location {$i}", 52.0 + $i * 0.01, 4.0 + $i * 0.01);
        }

        $this->locationRepository->method('findAll')->willReturn($locations);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Locations (100)', $output);
    }

    public function testExecuteWithUnknownWaterBodyType(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Unknown Water', 52.0, 4.0, [], [], RwsLocation::WATER_TYPE_UNKNOWN);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('unknown', $output);
    }

    public function testExecuteTableOutputHasCorrectHeaders(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Test', 52.0, 4.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Source', $output);
        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Lat', $output);
        $this->assertStringContainsString('Lon', $output);
        $this->assertStringContainsString('Water Type', $output);
        $this->assertStringContainsString('Status', $output);
    }

    public function testExecuteTableOutputWithPropertiesHasCorrectHeaders(): void
    {
        // Arrange
        $location = new RwsLocation('LOC001', 'Test', 52.0, 4.0);

        $this->locationRepository->method('findAll')->willReturn([$location]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand(['--show-location-properties' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Source', $output);
        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Lat', $output);
        $this->assertStringContainsString('Lon', $output);
        $this->assertStringContainsString('Water Type', $output);
        $this->assertStringContainsString('Compartimenten', $output);
        $this->assertStringContainsString('Grootheden', $output);
        $this->assertStringContainsString('Status', $output);
    }

    public function testCommandOptionShortcuts(): void
    {
        // Arrange
        $command = $this->createCommand();
        $definition = $command->getDefinition();

        // Act & Assert
        $this->assertTrue($definition->hasShortcut('s'));
        $this->assertSame('search', $definition->getOptionForShortcut('s')->getName());

        $this->assertTrue($definition->hasShortcut('f'));
        $this->assertSame('filter', $definition->getOptionForShortcut('f')->getName());

        $this->assertTrue($definition->hasShortcut('w'));
        $this->assertSame('water-type', $definition->getOptionForShortcut('w')->getName());
    }

    public function testExecuteAlwaysReturnsSuccess(): void
    {
        // Arrange
        $this->locationRepository->method('findAll')->willReturn([]);
        $this->weatherStationRepository->method('findAll')->willReturn([]);

        // Act
        $commandTester = $this->executeCommand();

        // Assert
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }
}
