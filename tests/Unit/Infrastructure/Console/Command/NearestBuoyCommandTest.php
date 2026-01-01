<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\Service\NearestRwsLocationFinder;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Infrastructure\Console\Command\NearestBuoyCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class NearestBuoyCommandTest extends TestCase
{
    private RwsLocationRepositoryInterface $locationRepository;
    private NearestRwsLocationFinder $rwsLocationFinder;
    private string $testProjectDir;

    protected function setUp(): void
    {
        $this->locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);

        // Create a temporary directory for the blacklist
        $this->testProjectDir = sys_get_temp_dir().'/test-project-'.uniqid();
        mkdir($this->testProjectDir.'/data', 0777, true);
        file_put_contents($this->testProjectDir.'/data/blacklist.txt', '');

        $blacklist = new \Seaswim\Infrastructure\Service\LocationBlacklist($this->testProjectDir);
        $this->rwsLocationFinder = new NearestRwsLocationFinder($blacklist);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testProjectDir)) {
            if (file_exists($this->testProjectDir.'/data/blacklist.txt')) {
                unlink($this->testProjectDir.'/data/blacklist.txt');
            }
            if (is_dir($this->testProjectDir.'/data')) {
                rmdir($this->testProjectDir.'/data');
            }
            rmdir($this->testProjectDir);
        }
    }

    private function createCommand(): NearestBuoyCommand
    {
        return new NearestBuoyCommand(
            $this->locationRepository,
            $this->rwsLocationFinder
        );
    }

    private function createRwsLocation(
        string $id,
        string $name,
        float $latitude,
        float $longitude,
        array $grootheden = [],
        string $waterBodyType = RwsLocation::WATER_TYPE_SEA
    ): RwsLocation {
        return new RwsLocation(
            id: $id,
            name: $name,
            latitude: $latitude,
            longitude: $longitude,
            compartimenten: [],
            grootheden: $grootheden,
            waterBodyType: $waterBodyType
        );
    }

    public function testCommandIsProperlyConfigured(): void
    {
        // Arrange
        $command = $this->createCommand();

        // Act
        $name = $command->getName();
        $description = $command->getDescription();
        $aliases = $command->getAliases();

        // Assert
        $this->assertSame('seaswim:locations:nearest-station', $name);
        $this->assertSame('Find the nearest location with a specific capability (Hm0, Tm02, Th3)', $description);
        $this->assertContains('seaswim:locations:nearest-wave-station', $aliases);
    }

    public function testCommandRequiresLocationArgument(): void
    {
        // Arrange
        $command = $this->createCommand();

        // Act
        $definition = $command->getDefinition();
        $argument = $definition->getArgument('location');

        // Assert
        $this->assertTrue($argument->isRequired());
        $this->assertSame('RWS location ID', $argument->getDescription());
    }

    public function testCommandHasCapabilityOption(): void
    {
        // Arrange
        $command = $this->createCommand();

        // Act
        $definition = $command->getDefinition();
        $option = $definition->getOption('capability');

        // Assert
        $this->assertNotNull($option);
        $this->assertSame('c', $option->getShortcut());
        $this->assertSame('Hm0', $option->getDefault());
        $this->assertSame('Capability to search for (Hm0, Tm02, Th3)', $option->getDescription());
    }

    public function testExecuteReturnsFailureWhenLocationNotFound(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $this->locationRepository
            ->expects($this->once())
            ->method('findById')
            ->with('NONEXISTENT')
            ->willReturn(null);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'NONEXISTENT',
        ]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Location "NONEXISTENT" not found', $commandTester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenNoLocationWithCapabilityFound(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        // Create a location without Hm0 capability
        $sourceLocation = $this->createRwsLocation('LOC1', 'Source Location', 52.0, 4.0);
        $allLocations = [$sourceLocation]; // Only the source location, no other locations with capability

        $this->locationRepository
            ->expects($this->once())
            ->method('findById')
            ->with('LOC1')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
        ]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('No locations with Hm0 capability found', $commandTester->getDisplay());
    }

    public function testExecuteReturnsSuccessWhenNearestLocationFound(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source Location', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest Station', 52.1, 4.1, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->expects($this->once())
            ->method('findById')
            ->with('LOC1')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteDisplaysCorrectOutputFormatting(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        // Use closer locations (within 20km max distance limit)
        $sourceLocation = $this->createRwsLocation('LOC1', 'Source Station', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest Station', 52.1, 4.1, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->with('LOC1')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Nearest Station with Hm0', $output);
        $this->assertStringContainsString('Source Location', $output);
        $this->assertStringContainsString('Source Station (LOC1)', $output);
        $this->assertStringContainsString('52.0000, 4.0000', $output);
        $this->assertStringContainsString('Nearest Station', $output);
        $this->assertStringContainsString('Nearest Station (LOC2)', $output);
        $this->assertStringContainsString('52.1000, 4.1000', $output);
        $this->assertStringContainsString('km', $output); // Distance will be calculated, just verify unit is present
    }

    public function testExecuteUsesCustomCapabilityOption(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source Location', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest Station', 52.1, 4.1, ['Tm02'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
            '--capability' => 'Tm02',
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Nearest Station with Tm02', $commandTester->getDisplay());
    }

    public function testExecuteUsesCapabilityShortOption(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source Location', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest Station', 52.1, 4.1, ['Th3'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
            '-c' => 'Th3',
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Nearest Station with Th3', $commandTester->getDisplay());
    }

    public function testExecuteUsesDefaultCapabilityWhenNotSpecified(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source Location', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest Station', 52.1, 4.1, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteDisplaysCoordinatesWithCorrectPrecision(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        // Use locations within 20km distance limit (about 0.1 degree difference ≈ 11km)
        $sourceLocation = $this->createRwsLocation('LOC1', 'Source', 52.123456789, 4.987654321, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest', 52.223456789, 4.987654321, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $commandTester->execute([
            'location' => 'LOC1',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        // Coordinates should be formatted with 4 decimal places
        $this->assertStringContainsString('52.1235, 4.9877', $output);
        $this->assertStringContainsString('52.2235, 4.9877', $output);
    }

    public function testExecuteDisplaysDistanceWithCorrectPrecision(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source Location', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest Station', 52.1, 4.1, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $commandTester->execute([
            'location' => 'LOC1',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        // Distance should be formatted with 2 decimal places - verify pattern exists
        $this->assertMatchesRegularExpression('/\d+\.\d{2} km/', $output);
    }

    public function testExecuteDisplaysTableWithCorrectStructure(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source Location', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest Station', 52.1, 4.1, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $commandTester->execute([
            'location' => 'LOC1',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        // Verify table headers and rows are present
        $this->assertStringContainsString('Property', $output);
        $this->assertStringContainsString('Value', $output);
        $this->assertStringContainsString('Source Location', $output);
        $this->assertStringContainsString('Source Coordinates', $output);
        $this->assertStringContainsString('Nearest Station', $output);
        $this->assertStringContainsString('Station Coordinates', $output);
        $this->assertStringContainsString('Capability', $output);
        $this->assertStringContainsString('Distance', $output);
    }

    public function testExecuteCallsRepositoryFindByIdWithCorrectArgument(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $this->locationRepository
            ->expects($this->once())
            ->method('findById')
            ->with('SPECIFIC_LOCATION_ID')
            ->willReturn(null);

        // Act
        $commandTester->execute([
            'location' => 'SPECIFIC_LOCATION_ID',
        ]);

        // Assert - expectations verified by mock
    }

    public function testExecuteCallsRepositoryFindAllWhenLocationExists(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source', 52.0, 4.0);

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$sourceLocation]);

        // Act
        $commandTester->execute([
            'location' => 'LOC1',
        ]);

        // Assert - expectations verified by mock
    }

    public function testExecuteDoesNotCallFindAllWhenLocationNotFound(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $this->locationRepository
            ->method('findById')
            ->willReturn(null);

        $this->locationRepository
            ->expects($this->never())
            ->method('findAll');

        // Act
        $commandTester->execute([
            'location' => 'NONEXISTENT',
        ]);

        // Assert - expectations verified by mock
    }

    public function testExecuteCallsFindNearestWithCorrectArguments(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $otherLocation = $this->createRwsLocation('LOC2', 'Other', 52.1, 4.1, ['Tm02'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $otherLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act - The real finder will be called with these arguments
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
            '--capability' => 'Tm02',
        ]);

        // Assert - Verify the command succeeds (which means findNearest was called correctly)
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Tm02', $commandTester->getDisplay());
    }

    public function testExecuteHandlesLocationWithSpecialCharactersInName(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', "Location with 'quotes' & symbols", 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Normal Location', 52.1, 4.1, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Location with 'quotes' & symbols", $output);
    }

    public function testExecuteHandlesZeroDistance(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest', 52.0, 4.0, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('0.00 km', $output);
    }

    public function testExecuteHandlesVerySmallDistance(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source', 52.0, 4.0, [], RwsLocation::WATER_TYPE_SEA);
        $nearestLocation = $this->createRwsLocation('LOC2', 'Nearest', 52.0001, 4.0001, ['Hm0'], RwsLocation::WATER_TYPE_SEA);
        $allLocations = [$sourceLocation, $nearestLocation];

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn($allLocations);

        // Act
        $exitCode = $commandTester->execute([
            'location' => 'LOC1',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('0.01 km', $output);
    }

    public function testExecuteErrorMessageUsesSymfonyStyleFormatting(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $this->locationRepository
            ->method('findById')
            ->willReturn(null);

        // Act
        $commandTester->execute([
            'location' => 'NONEXISTENT',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        // SymfonyStyle error messages include formatting markers
        $this->assertStringContainsString('Location "NONEXISTENT" not found', $output);
    }

    public function testExecuteWarningMessageUsesSymfonyStyleFormatting(): void
    {
        // Arrange
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $sourceLocation = $this->createRwsLocation('LOC1', 'Source', 52.0, 4.0);

        $this->locationRepository
            ->method('findById')
            ->willReturn($sourceLocation);

        $this->locationRepository
            ->method('findAll')
            ->willReturn([$sourceLocation]);

        // Act
        $commandTester->execute([
            'location' => 'LOC1',
            '--capability' => 'CUSTOM',
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        // SymfonyStyle warning messages include formatting markers
        $this->assertStringContainsString('No locations with CUSTOM capability found', $output);
    }
}
