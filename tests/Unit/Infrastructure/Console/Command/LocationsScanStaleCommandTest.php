<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Infrastructure\Console\Command\LocationsScanStaleCommand;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class LocationsScanStaleCommandTest extends TestCase
{
    private string $tempDir;
    private string $blacklistFile;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a temporary directory for test blacklist files
        $this->tempDir = sys_get_temp_dir().'/seaswim_test_'.uniqid();
        mkdir($this->tempDir.'/data', 0777, true);
        $this->blacklistFile = $this->tempDir.'/data/blacklist.txt';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up temporary files
        if (file_exists($this->blacklistFile)) {
            unlink($this->blacklistFile);
        }
        if (is_dir($this->tempDir.'/data')) {
            rmdir($this->tempDir.'/data');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    private function createLocation(string $id, string $name = 'Test Location'): RwsLocation
    {
        return new RwsLocation(
            id: $id,
            name: $name,
            latitude: 52.0,
            longitude: 4.0,
            compartimenten: ['OW'],
            grootheden: ['T'],
            waterBodyType: RwsLocation::WATER_TYPE_SEA
        );
    }

    private function createCommand(
        array $locations = [],
        ?RwsHttpClientInterface $rwsClient = null
    ): LocationsScanStaleCommand {
        $locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $locationRepository->method('findAll')->willReturn($locations);

        if (null === $rwsClient) {
            $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        }

        return new LocationsScanStaleCommand(
            $locationRepository,
            $rwsClient,
            $this->tempDir
        );
    }

    public function testCommandIsProperlyConfigured(): void
    {
        // Arrange
        $command = $this->createCommand();

        // Act
        $name = $command->getName();
        $description = $command->getDescription();

        // Assert
        $this->assertSame('seaswim:locations:scan-stale', $name);
        $this->assertSame('Scan all RWS locations for stale data and update the blacklist', $description);
    }

    public function testCommandHasDryRunOption(): void
    {
        // Arrange
        $command = $this->createCommand();

        // Act
        $definition = $command->getDefinition();

        // Assert
        $this->assertTrue($definition->hasOption('dry-run'));
        $dryRunOption = $definition->getOption('dry-run');
        $this->assertFalse($dryRunOption->acceptValue());
        $this->assertStringContainsString('Show what would be blacklisted', $dryRunOption->getDescription());
    }

    public function testCommandHasDelayOption(): void
    {
        // Arrange
        $command = $this->createCommand();

        // Act
        $definition = $command->getDefinition();

        // Assert
        $this->assertTrue($definition->hasOption('delay'));
        $delayOption = $definition->getOption('delay');
        $this->assertTrue($delayOption->acceptValue());
        $this->assertSame('100', $delayOption->getDefault());
        $this->assertStringContainsString('Delay between API calls', $delayOption->getDescription());
        // Check shortcut
        $this->assertSame('d', $delayOption->getShortcut());
    }

    public function testExecuteWithNoLocationsReturnsSuccessAndWarning(): void
    {
        // Arrange
        $command = $this->createCommand(locations: []);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No locations found', $output);
        $this->assertStringContainsString('Run seaswim:locations:refresh first', $output);
    }

    public function testExecuteScansLocationsAndDetectsFreshData(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $rwsClient->method('fetchWaterData')
            ->willReturnCallback(function (string $locationId) use ($today) {
                return [
                    'timestamp' => $today.' 12:00:00',
                    'value' => 20.5,
                ];
            });

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fresh data (today): 2 locations', $output);
        $this->assertStringContainsString('Stale data: 0 locations', $output);
        $this->assertStringContainsString('No new locations to blacklist', $output);
    }

    public function testExecuteScansLocationsAndDetectsStaleData(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');

        $rwsClient->method('fetchWaterData')
            ->willReturnCallback(function (string $locationId) use ($yesterday) {
                return [
                    'timestamp' => $yesterday.' 12:00:00',
                    'value' => 20.5,
                ];
            });

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fresh data (today): 0 locations', $output);
        $this->assertStringContainsString('Stale data: 2 locations', $output);
        $this->assertStringContainsString('LOC001', $output);
        $this->assertStringContainsString('LOC002', $output);
        $this->assertStringContainsString($yesterday, $output);
        $this->assertStringContainsString('Blacklist updated with 2 total locations', $output);
    }

    public function testExecuteDetectsLocationsWithNoData(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn(null);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No data: 2 locations', $output);
        $this->assertStringContainsString('Blacklist updated with 2 total locations', $output);
    }

    public function testExecuteDetectsLocationsWithMissingTimestamp(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn([
            'value' => 20.5,
            // timestamp is missing
        ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No data: 1 locations', $output);
    }

    public function testExecuteHandlesInvalidTimestampFormat(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn([
            'timestamp' => 'invalid-date-format',
            'value' => 20.5,
        ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No data: 1 locations', $output);
    }

    public function testExecuteWithDryRunDoesNotWriteBlacklist(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn([
            'timestamp' => $yesterday.' 12:00:00',
            'value' => 20.5,
        ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--dry-run' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Dry run - blacklist not updated', $output);
        $this->assertStringContainsString('LOC001', $output);
        $this->assertFileDoesNotExist($this->blacklistFile);
    }

    public function testExecuteWritesBlacklistFile(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn([
            'timestamp' => $yesterday.' 12:00:00',
            'value' => 20.5,
        ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($this->blacklistFile);

        $content = file_get_contents($this->blacklistFile);
        $this->assertStringContainsString('LOC001', $content);
        $this->assertStringContainsString('LOC002', $content);
        $this->assertStringContainsString('# Blacklisted RWS locations', $content);
        $this->assertStringContainsString('# Generated by seaswim:locations:scan-stale', $content);
    }

    public function testExecuteSkipsAlreadyBlacklistedLocations(): void
    {
        // Arrange - create existing blacklist
        $existingBlacklist = "# Existing blacklist\nLOC001\nLOC002\n";
        file_put_contents($this->blacklistFile, $existingBlacklist);

        $locations = [
            $this->createLocation('LOC001'), // already blacklisted
            $this->createLocation('LOC002'), // already blacklisted
            $this->createLocation('LOC003'), // new location
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->expects($this->once()) // Only called once for LOC003
            ->method('fetchWaterData')
            ->with('LOC003')
            ->willReturn(['timestamp' => (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s')]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Total locations: 3', $output);
        $this->assertStringContainsString('already blacklisted: 2', $output);
        $this->assertStringContainsString('to scan: 1', $output);
    }

    public function testExecuteWhenAllLocationsAlreadyBlacklisted(): void
    {
        // Arrange
        $existingBlacklist = "# Existing blacklist\nLOC001\nLOC002\n";
        file_put_contents($this->blacklistFile, $existingBlacklist);

        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->expects($this->never())->method('fetchWaterData');

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('All locations are already blacklisted', $output);
    }

    public function testExecuteMergesWithExistingBlacklist(): void
    {
        // Arrange - create existing blacklist
        $existingBlacklist = "# Existing blacklist\n# Comment line\nLOC001\n\n# Another comment\nLOC002\n";
        file_put_contents($this->blacklistFile, $existingBlacklist);

        $locations = [
            $this->createLocation('LOC003'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn([
            'timestamp' => $yesterday.' 12:00:00',
        ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $content = file_get_contents($this->blacklistFile);
        // Check all locations are present
        $this->assertStringContainsString('LOC001', $content);
        $this->assertStringContainsString('LOC002', $content);
        $this->assertStringContainsString('LOC003', $content);
        $this->assertStringContainsString('Blacklist updated with 3 total locations', $commandTester->getDisplay());
    }

    public function testExecuteIgnoresCommentsAndEmptyLinesInExistingBlacklist(): void
    {
        // Arrange
        $existingBlacklist = <<<'TXT'
# Header comment
# Another comment

LOC001

# Mid comment
LOC002

# Trailing comment
TXT;
        file_put_contents($this->blacklistFile, $existingBlacklist);

        $locations = [
            $this->createLocation('LOC003'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s')]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $content = file_get_contents($this->blacklistFile);
        $this->assertStringContainsString('LOC001', $content);
        $this->assertStringContainsString('LOC002', $content);
    }

    public function testExecuteSortsBlacklistAlphabetically(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC003'),
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn([
            'timestamp' => $yesterday.' 12:00:00',
        ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $content = file_get_contents($this->blacklistFile);
        $lines = explode("\n", $content);
        $locationLines = array_filter($lines, fn ($line) => !str_starts_with(trim($line), '#') && '' !== trim($line));
        $locationLines = array_values($locationLines);

        $this->assertSame('LOC001', trim($locationLines[0]));
        $this->assertSame('LOC002', trim($locationLines[1]));
        $this->assertSame('LOC003', trim($locationLines[2]));
    }

    public function testExecuteDisplaysStaleLocationsTableSortedByDate(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
            $this->createLocation('LOC003'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $threeDaysAgo = (new \DateTimeImmutable('-3 days'))->format('Y-m-d');
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $lastWeek = (new \DateTimeImmutable('-7 days'))->format('Y-m-d');

        $rwsClient->method('fetchWaterData')
            ->willReturnMap([
                ['LOC001', ['timestamp' => $yesterday.' 12:00:00']],
                ['LOC002', ['timestamp' => $lastWeek.' 12:00:00']],
                ['LOC003', ['timestamp' => $threeDaysAgo.' 12:00:00']],
            ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Stale locations (most recent data)', $output);
        $this->assertStringContainsString('Location ID', $output);
        $this->assertStringContainsString('Last Data', $output);

        // Verify dates appear in output
        $this->assertStringContainsString($lastWeek, $output);
        $this->assertStringContainsString($threeDaysAgo, $output);
        $this->assertStringContainsString($yesterday, $output);
    }

    public function testExecuteWithCustomDelayOption(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s')]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--delay' => '500']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        // Note: We cannot easily test the actual delay, but we verify the option is accepted
    }

    public function testExecuteWithDelayShortcutOption(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s')]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['-d' => '250']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteDisplaysProgressBar(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s')]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        // Progress bar output contains location IDs and progress indicators
        $this->assertStringContainsString('2/2', $output);
    }

    public function testExecuteMixedFreshStaleAndNoDataLocations(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('FRESH001'),
            $this->createLocation('STALE001'),
            $this->createLocation('NODATA001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');

        $rwsClient->method('fetchWaterData')
            ->willReturnMap([
                ['FRESH001', ['timestamp' => $today.' 12:00:00']],
                ['STALE001', ['timestamp' => $yesterday.' 12:00:00']],
                ['NODATA001', null],
            ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fresh data (today): 1 locations', $output);
        $this->assertStringContainsString('Stale data: 1 locations', $output);
        $this->assertStringContainsString('No data: 1 locations', $output);

        $content = file_get_contents($this->blacklistFile);
        $this->assertStringContainsString('STALE001', $content);
        $this->assertStringContainsString('NODATA001', $content);
        $this->assertStringNotContainsString('FRESH001', $content);
    }

    public function testExecuteRemovesDuplicatesFromBlacklist(): void
    {
        // Arrange - existing blacklist has LOC001
        $existingBlacklist = "# Existing\nLOC001\n";
        file_put_contents($this->blacklistFile, $existingBlacklist);

        $locations = [
            $this->createLocation('LOC001'), // Will be skipped in scan
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => $yesterday.' 12:00:00']);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $content = file_get_contents($this->blacklistFile);
        $lines = explode("\n", $content);
        $locationLines = array_filter($lines, fn ($line) => !str_starts_with(trim($line), '#') && '' !== trim($line));

        // Count occurrences of LOC001 - should only appear once
        $loc001Count = count(array_filter($locationLines, fn ($line) => 'LOC001' === trim($line)));
        $this->assertSame(1, $loc001Count, 'LOC001 should appear exactly once');
    }

    public function testExecuteHandlesEmptyExistingBlacklist(): void
    {
        // Arrange - create empty blacklist file
        file_put_contents($this->blacklistFile, '');

        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => $yesterday.' 12:00:00']);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $content = file_get_contents($this->blacklistFile);
        $this->assertStringContainsString('LOC001', $content);
    }

    public function testExecuteHandlesNonExistentBlacklistFile(): void
    {
        // Arrange - ensure file doesn't exist
        $this->assertFileDoesNotExist($this->blacklistFile);

        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => $yesterday.' 12:00:00']);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($this->blacklistFile);
        $content = file_get_contents($this->blacklistFile);
        $this->assertStringContainsString('LOC001', $content);
    }

    public function testExecuteAddsNewLocationsMessageOutput(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => $yesterday.' 12:00:00']);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Adding 2 new locations to blacklist:', $output);
        $this->assertStringContainsString('- LOC001', $output);
        $this->assertStringContainsString('- LOC002', $output);
    }

    public function testExecuteBlacklistFileContainsTimestamp(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => $yesterday.' 12:00:00']);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $content = file_get_contents($this->blacklistFile);
        $this->assertStringContainsString('Generated by seaswim:locations:scan-stale on', $content);
        // Verify timestamp format
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $content);
    }

    public function testExecuteBlacklistFileContainsHeaderComments(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn(null);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $content = file_get_contents($this->blacklistFile);
        $this->assertStringContainsString('# Blacklisted RWS locations (stale or no data)', $content);
        $this->assertStringContainsString('# These locations return outdated data from the RWS API', $content);
        $this->assertStringContainsString('# They are excluded from the location selector', $content);
    }

    public function testExecuteDisplaysTitleAndSummarySection(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s')]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Scanning RWS locations for stale data', $output);
        $this->assertStringContainsString('Results', $output);
    }

    public function testExecuteApiCallsRespectLocationOrder(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
            $this->createLocation('LOC002'),
            $this->createLocation('LOC003'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $callOrder = [];

        $rwsClient->method('fetchWaterData')
            ->willReturnCallback(function (string $locationId) use (&$callOrder) {
                $callOrder[] = $locationId;

                return ['timestamp' => (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s')];
            });

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertSame(['LOC001', 'LOC002', 'LOC003'], $callOrder);
    }

    public function testExecuteWithZeroDelayOption(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $rwsClient->method('fetchWaterData')->willReturn(['timestamp' => (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s')]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--delay' => '0']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteHandlesDateAtMidnight(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn([
            'timestamp' => $today.' 00:00:00',
        ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fresh data (today): 1 locations', $output);
    }

    public function testExecuteHandlesDateJustBeforeMidnight(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('LOC001'),
        ];

        $rwsClient = $this->createMock(RwsHttpClientInterface::class);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $rwsClient->method('fetchWaterData')->willReturn([
            'timestamp' => $today.' 23:59:59',
        ]);

        $command = $this->createCommand($locations, $rwsClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Fresh data (today): 1 locations', $output);
    }
}
