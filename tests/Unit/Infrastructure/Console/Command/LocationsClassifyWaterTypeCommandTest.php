<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Infrastructure\Console\Command\LocationsClassifyWaterTypeCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class LocationsClassifyWaterTypeCommandTest extends TestCase
{
    private RwsLocationRepositoryInterface $locationRepository;
    private HttpClientInterface $httpClient;

    protected function setUp(): void
    {
        $this->locationRepository = $this->createMock(RwsLocationRepositoryInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
    }

    /**
     * Creates a test RwsLocation with specified water type.
     */
    private function createLocation(
        string $id,
        string $name,
        float $lat,
        float $lon,
        string $waterType = RwsLocation::WATER_TYPE_UNKNOWN
    ): RwsLocation {
        return new RwsLocation(
            $id,
            $name,
            $lat,
            $lon,
            ['OW'],
            ['T'],
            $waterType
        );
    }

    /**
     * Creates a mock HTTP response with PDOK API data.
     *
     * @param array<array{type: string}> $features
     */
    private function createPdokResponse(array $features): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'features' => array_map(fn ($f) => ['properties' => $f], $features),
        ]);

        return $response;
    }

    public function testCommandIsProperlyConfigured(): void
    {
        // Arrange
        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);

        // Act
        $name = $command->getName();
        $description = $command->getDescription();
        $definition = $command->getDefinition();

        // Assert
        $this->assertSame('seaswim:locations:classify-water-type', $name);
        $this->assertSame('Classify RWS locations by water body type using PDOK BGT API', $description);
        $this->assertTrue($definition->hasOption('only-unknown'));
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('limit'));
    }

    public function testOnlyUnknownOptionIsConfiguredCorrectly(): void
    {
        // Arrange
        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);

        // Act
        $definition = $command->getDefinition();
        $option = $definition->getOption('only-unknown');

        // Assert
        $this->assertFalse($option->acceptValue());
        $this->assertSame('Only classify locations with unknown water type', $option->getDescription());
    }

    public function testDryRunOptionIsConfiguredCorrectly(): void
    {
        // Arrange
        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);

        // Act
        $definition = $command->getDefinition();
        $option = $definition->getOption('dry-run');

        // Assert
        $this->assertFalse($option->acceptValue());
        $this->assertSame('Do not save changes', $option->getDescription());
    }

    public function testLimitOptionIsConfiguredCorrectly(): void
    {
        // Arrange
        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);

        // Act
        $definition = $command->getDefinition();
        $option = $definition->getOption('limit');

        // Assert
        $this->assertTrue($option->acceptValue());
        $this->assertTrue($option->isValueRequired());
        $this->assertSame('Limit number of locations to process', $option->getDescription());
    }

    public function testExecuteClassifiesLocationsAsSeaWhenPdokReturnsSeeType(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return 1 === count($locations)
                    && RwsLocation::WATER_TYPE_SEA === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Sea', $output);
        $this->assertStringContainsString('1', $output);
    }

    public function testExecuteClassifiesLocationsAsLakeWhenPdokReturnsWatervlakteType(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'watervlakte']]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_LAKE === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Lake', $output);
    }

    public function testExecuteClassifiesLocationsAsRiverWhenPdokReturnsWaterloopType(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'waterloop']]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_RIVER === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('River', $output);
    }

    public function testExecuteClassifiesLocationsAsUnknownWhenPdokReturnsNoFeatures(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_UNKNOWN === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Unknown', $output);
    }

    public function testExecuteClassifiesLocationsAsUnknownWhenPdokReturnsUnrecognizedType(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'greppel_droge_sloot']]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_UNKNOWN === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteClassifiesLocationsAsUnknownWhenHttpRequestThrowsException(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $this->httpClient->method('request')->willThrowException(new \Exception('Network error'));

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_UNKNOWN === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecutePrioritizesSeaOverOtherTypesWhenMultipleTypesPresent(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([
            ['type' => 'waterloop'],
            ['type' => 'watervlakte'],
            ['type' => 'zee'],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_SEA === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);
    }

    public function testExecutePrioritizesLakeOverRiverWhenBothPresent(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([
            ['type' => 'waterloop'],
            ['type' => 'watervlakte'],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_LAKE === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);
    }

    public function testExecuteWithDryRunDoesNotSaveLocations(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test Location', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->never())->method('saveAll');

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--dry-run' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Dry run - no changes saved', $output);
    }

    public function testExecuteWithOnlyUnknownOptionFiltersLocations(): void
    {
        // Arrange
        $unknownLocation = $this->createLocation('loc1', 'Unknown', 52.0, 4.0, RwsLocation::WATER_TYPE_UNKNOWN);
        $seaLocation = $this->createLocation('loc2', 'Sea', 52.1, 4.1, RwsLocation::WATER_TYPE_SEA);
        $this->locationRepository->method('findAll')->willReturn([$unknownLocation, $seaLocation]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->expects($this->once())->method('request')->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--only-unknown' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Filtering to 1 locations with unknown water type', $output);
    }

    public function testExecuteWithLimitOptionLimitsNumberOfLocationsProcessed(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('loc1', 'Location 1', 52.0, 4.0),
            $this->createLocation('loc2', 'Location 2', 52.1, 4.1),
            $this->createLocation('loc3', 'Location 3', 52.2, 4.2),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->expects($this->exactly(2))->method('request')->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--limit' => '2']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Limited to 2 locations', $output);
    }

    public function testExecuteWithOnlyUnknownAndLimitOptionsCombined(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('loc1', 'Unknown 1', 52.0, 4.0, RwsLocation::WATER_TYPE_UNKNOWN),
            $this->createLocation('loc2', 'Sea', 52.1, 4.1, RwsLocation::WATER_TYPE_SEA),
            $this->createLocation('loc3', 'Unknown 2', 52.2, 4.2, RwsLocation::WATER_TYPE_UNKNOWN),
            $this->createLocation('loc4', 'Unknown 3', 52.3, 4.3, RwsLocation::WATER_TYPE_UNKNOWN),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->expects($this->exactly(2))->method('request')->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([
            '--only-unknown' => true,
            '--limit' => '2',
        ]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Filtering to 3 locations with unknown water type', $output);
        $this->assertStringContainsString('Limited to 2 locations', $output);
    }

    public function testExecuteDisplaysProgressBarDuringProcessing(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('loc1', 'Location 1', 52.0, 4.0),
            $this->createLocation('loc2', 'Location 2', 52.1, 4.1),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->method('request')->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('2/2', $output);
    }

    public function testExecuteDisplaysStatisticsTableAfterProcessing(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('loc1', 'Sea Location', 52.0, 4.0),
            $this->createLocation('loc2', 'Lake Location', 52.1, 4.1),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $seaResponse = $this->createPdokResponse([['type' => 'zee']]);
        $lakeResponse = $this->createPdokResponse([['type' => 'watervlakte']]);
        $this->httpClient->method('request')->willReturnOnConsecutiveCalls($seaResponse, $lakeResponse);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Water Type', $output);
        $this->assertStringContainsString('Count', $output);
        $this->assertStringContainsString('Sea', $output);
        $this->assertStringContainsString('Lake', $output);
        $this->assertStringContainsString('River', $output);
        $this->assertStringContainsString('Unknown', $output);
    }

    public function testExecuteRequestsCorrectPdokApiUrl(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.pdok.nl/lv/bgt/ogc/v1/collections/waterdeel/items',
                $this->anything()
            )
            ->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);
    }

    public function testExecuteSendsCorrectQueryParametersToPdokApi(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function ($options) {
                    return isset($options['query']['f'])
                        && 'json' === $options['query']['f']
                        && isset($options['query']['bbox'])
                        && isset($options['query']['limit'])
                        && 50 === $options['query']['limit']
                        && isset($options['timeout'])
                        && 10 === $options['timeout'];
                })
            )
            ->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);
    }

    public function testExecuteCalculatesCorrectBoundingBox(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) {
                    $bbox = $options['query']['bbox'];
                    $parts = explode(',', $bbox);

                    // Check that bbox contains 4 values and is centered around location
                    return 4 === count($parts)
                        && (float) $parts[0] < 4.0 // min lon
                        && (float) $parts[2] > 4.0 // max lon
                        && (float) $parts[1] < 52.0 // min lat
                        && (float) $parts[3] > 52.0; // max lat
                })
            )
            ->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);
    }

    public function testExecuteHandlesEmptyLocationsList(): void
    {
        // Arrange
        $this->locationRepository->method('findAll')->willReturn([]);
        $this->httpClient->expects($this->never())->method('request');

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 locations', $output);
    }

    public function testExecutePreservesOriginalLocationDataWhenUpdating(): void
    {
        // Arrange
        $originalLocation = $this->createLocation('loc1', 'Original Name', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$originalLocation]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                $loc = $locations[0];

                return 'loc1' === $loc->getId()
                    && 'Original Name' === $loc->getName()
                    && 52.0 === $loc->getLatitude()
                    && 4.0 === $loc->getLongitude()
                    && $loc->getCompartimenten() === ['OW']
                    && $loc->getGrootheden() === ['T']
                    && RwsLocation::WATER_TYPE_SEA === $loc->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);
    }

    public function testExecuteMergesUpdatedLocationsBackIntoAllLocations(): void
    {
        // Arrange
        $locationToUpdate = $this->createLocation('loc1', 'Update Me', 52.0, 4.0);
        $locationNotProcessed = $this->createLocation('loc2', 'Leave Me', 52.1, 4.1, RwsLocation::WATER_TYPE_LAKE);

        $this->locationRepository->method('findAll')->willReturn([$locationToUpdate, $locationNotProcessed]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->expects($this->once())->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return 2 === count($locations)
                    && 'loc1' === $locations[0]->getId()
                    && RwsLocation::WATER_TYPE_SEA === $locations[0]->getWaterBodyType()
                    && 'loc2' === $locations[1]->getId()
                    && RwsLocation::WATER_TYPE_LAKE === $locations[1]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['--only-unknown' => true]);
    }

    public function testExecuteDisplaysTitleAtStart(): void
    {
        // Arrange
        $this->locationRepository->method('findAll')->willReturn([]);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Classify RWS Locations by Water Body Type', $output);
    }

    public function testExecuteDisplaysFoundLocationsCount(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('loc1', 'Location 1', 52.0, 4.0),
            $this->createLocation('loc2', 'Location 2', 52.1, 4.1),
            $this->createLocation('loc3', 'Location 3', 52.2, 4.2),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->method('request')->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Found 3 locations', $output);
    }

    public function testExecuteDisplaysSuccessMessageWhenSavingCompletes(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([['type' => 'zee']]);
        $this->httpClient->method('request')->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Locations saved', $output);
    }

    public function testExecuteWithMultipleLocationsCalculatesCorrectStatistics(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('loc1', 'Sea 1', 52.0, 4.0),
            $this->createLocation('loc2', 'Sea 2', 52.1, 4.1),
            $this->createLocation('loc3', 'Lake 1', 52.2, 4.2),
            $this->createLocation('loc4', 'River 1', 52.3, 4.3),
            $this->createLocation('loc5', 'Unknown 1', 52.4, 4.4),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $responses = [
            $this->createPdokResponse([['type' => 'zee']]),
            $this->createPdokResponse([['type' => 'zee']]),
            $this->createPdokResponse([['type' => 'watervlakte']]),
            $this->createPdokResponse([['type' => 'waterloop']]),
            $this->createPdokResponse([]),
        ];
        $this->httpClient->method('request')->willReturnOnConsecutiveCalls(...$responses);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        // Verify the statistics in the output contain correct counts
        $this->assertMatchesRegularExpression('/Sea.*2/s', $output);
        $this->assertMatchesRegularExpression('/Lake.*1/s', $output);
        $this->assertMatchesRegularExpression('/River.*1/s', $output);
        $this->assertMatchesRegularExpression('/Unknown.*1/s', $output);
    }

    public function testExecuteHandlesResponseWithMissingFeaturesKey(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_UNKNOWN === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteHandlesResponseWithMissingPropertiesKey(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'features' => [
                [], // No properties key
            ],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_UNKNOWN === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteHandlesResponseWithMissingTypeKey(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'features' => [
                ['properties' => []], // No type key
            ],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_UNKNOWN === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteCountsMultipleOccurrencesOfSameType(): void
    {
        // Arrange
        $location = $this->createLocation('loc1', 'Test', 52.0, 4.0);
        $this->locationRepository->method('findAll')->willReturn([$location]);

        $response = $this->createPdokResponse([
            ['type' => 'zee'],
            ['type' => 'zee'],
            ['type' => 'zee'],
        ]);
        $this->httpClient->method('request')->willReturn($response);

        $this->locationRepository->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function ($locations) {
                return RwsLocation::WATER_TYPE_SEA === $locations[0]->getWaterBodyType();
            }));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);
    }

    public function testExecuteReturnsSuccessEvenWithAllApiFailures(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('loc1', 'Test 1', 52.0, 4.0),
            $this->createLocation('loc2', 'Test 2', 52.1, 4.1),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $this->httpClient->method('request')->willThrowException(new \Exception('Network error'));

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertMatchesRegularExpression('/Unknown.*2/s', $output);
    }

    public function testExecuteWithZeroLimitProcessesAllLocations(): void
    {
        // Arrange - when limit is 0, PHP evaluates it as false, so limit is not applied
        $locations = [
            $this->createLocation('loc1', 'Location 1', 52.0, 4.0),
            $this->createLocation('loc2', 'Location 2', 52.1, 4.1),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $response = $this->createPdokResponse([]);
        $this->httpClient->expects($this->exactly(2))->method('request')->willReturn($response);

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--limit' => '0']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        // When limit is 0, the if ($limit) condition is false, so no limiting occurs
        $this->assertStringNotContainsString('Limited to', $output);
        $this->assertMatchesRegularExpression('/Unknown.*2/s', $output);
    }

    public function testExecuteWithOnlyUnknownWhenAllLocationsAreClassified(): void
    {
        // Arrange
        $locations = [
            $this->createLocation('loc1', 'Sea', 52.0, 4.0, RwsLocation::WATER_TYPE_SEA),
            $this->createLocation('loc2', 'Lake', 52.1, 4.1, RwsLocation::WATER_TYPE_LAKE),
        ];
        $this->locationRepository->method('findAll')->willReturn($locations);

        $this->httpClient->expects($this->never())->method('request');

        $command = new LocationsClassifyWaterTypeCommand($this->locationRepository, $this->httpClient);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--only-unknown' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Filtering to 0 locations with unknown water type', $output);
    }
}
