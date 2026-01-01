<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Repository;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Infrastructure\Repository\JsonFileRwsLocationRepository;

final class JsonFileRwsLocationRepositoryTest extends TestCase
{
    private string $tempDir;
    private string $projectDir;

    protected function setUp(): void
    {
        // Create a unique temporary directory for each test
        $this->tempDir = sys_get_temp_dir().'/seaswim_test_'.uniqid();
        $this->projectDir = $this->tempDir;
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files and directories
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function getJsonFilePath(): string
    {
        return $this->projectDir.'/var/data/rws-locations.json';
    }

    private function createJsonFile(array $data): void
    {
        $dir = dirname($this->getJsonFilePath());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->getJsonFilePath(), json_encode($data, JSON_PRETTY_PRINT));
    }

    public function testFindAllReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findAll();

        // Assert
        $this->assertSame([], $result);
    }

    public function testFindAllReturnsEmptyArrayWhenFileIsEmpty(): void
    {
        // Arrange
        $this->createJsonFile([]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findAll();

        // Assert
        $this->assertSame([], $result);
    }

    public function testFindAllReturnsEmptyArrayWhenFileContainsInvalidJson(): void
    {
        // Arrange
        $dir = dirname($this->getJsonFilePath());
        mkdir($dir, 0755, true);
        file_put_contents($this->getJsonFilePath(), 'invalid json {]');
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findAll();

        // Assert
        $this->assertSame([], $result);
    }

    public function testFindAllReturnsEmptyArrayWhenJsonIsNotAnArray(): void
    {
        // Arrange
        $dir = dirname($this->getJsonFilePath());
        mkdir($dir, 0755, true);
        file_put_contents($this->getJsonFilePath(), json_encode('string value'));
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findAll();

        // Assert
        $this->assertSame([], $result);
    }

    public function testFindAllReturnsSingleLocation(): void
    {
        // Arrange
        $this->createJsonFile([
            [
                'id' => 'vlissingen',
                'name' => 'Vlissingen',
                'latitude' => 51.4424,
                'longitude' => 3.5968,
                'compartimenten' => ['OW'],
                'grootheden' => ['T', 'WATHTE'],
                'waterBodyType' => 'sea',
            ],
        ]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findAll();

        // Assert
        $this->assertCount(1, $result);
        $this->assertInstanceOf(RwsLocation::class, $result[0]);
        $this->assertSame('vlissingen', $result[0]->getId());
        $this->assertSame('Vlissingen', $result[0]->getName());
        $this->assertSame(51.4424, $result[0]->getLatitude());
        $this->assertSame(3.5968, $result[0]->getLongitude());
        $this->assertSame(['OW'], $result[0]->getCompartimenten());
        $this->assertSame(['T', 'WATHTE'], $result[0]->getGrootheden());
        $this->assertSame('sea', $result[0]->getWaterBodyType());
    }

    public function testFindAllReturnsMultipleLocations(): void
    {
        // Arrange
        $this->createJsonFile([
            [
                'id' => 'vlissingen',
                'name' => 'Vlissingen',
                'latitude' => 51.4424,
                'longitude' => 3.5968,
                'compartimenten' => ['OW'],
                'grootheden' => ['T'],
                'waterBodyType' => 'sea',
            ],
            [
                'id' => 'europlatform',
                'name' => 'Europlatform',
                'latitude' => 52.0000,
                'longitude' => 3.2761,
                'compartimenten' => ['OW', 'LW'],
                'grootheden' => ['WATHTE', 'Hm0'],
                'waterBodyType' => 'sea',
            ],
        ]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findAll();

        // Assert
        $this->assertCount(2, $result);
        $this->assertSame('vlissingen', $result[0]->getId());
        $this->assertSame('europlatform', $result[1]->getId());
    }

    public function testFindAllHandlesMissingOptionalFields(): void
    {
        // Arrange
        $this->createJsonFile([
            [
                'id' => 'test-location',
                'name' => 'Test Location',
                'latitude' => 50.0,
                'longitude' => 4.0,
            ],
        ]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findAll();

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame('test-location', $result[0]->getId());
        $this->assertSame([], $result[0]->getCompartimenten());
        $this->assertSame([], $result[0]->getGrootheden());
        $this->assertSame(RwsLocation::WATER_TYPE_UNKNOWN, $result[0]->getWaterBodyType());
    }

    public function testFindAllConvertsNumericStringsToFloats(): void
    {
        // Arrange
        $this->createJsonFile([
            [
                'id' => 'test',
                'name' => 'Test',
                'latitude' => '51.5',
                'longitude' => '3.75',
            ],
        ]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findAll();

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame(51.5, $result[0]->getLatitude());
        $this->assertSame(3.75, $result[0]->getLongitude());
    }

    public function testFindByIdReturnsNullWhenFileDoesNotExist(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findById('vlissingen');

        // Assert
        $this->assertNull($result);
    }

    public function testFindByIdReturnsNullWhenLocationNotFound(): void
    {
        // Arrange
        $this->createJsonFile([
            [
                'id' => 'vlissingen',
                'name' => 'Vlissingen',
                'latitude' => 51.4424,
                'longitude' => 3.5968,
            ],
        ]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findById('nonexistent');

        // Assert
        $this->assertNull($result);
    }

    public function testFindByIdReturnsLocationWhenFound(): void
    {
        // Arrange
        $this->createJsonFile([
            [
                'id' => 'vlissingen',
                'name' => 'Vlissingen',
                'latitude' => 51.4424,
                'longitude' => 3.5968,
                'compartimenten' => ['OW'],
                'grootheden' => ['T'],
                'waterBodyType' => 'sea',
            ],
            [
                'id' => 'europlatform',
                'name' => 'Europlatform',
                'latitude' => 52.0000,
                'longitude' => 3.2761,
            ],
        ]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findById('europlatform');

        // Assert
        $this->assertInstanceOf(RwsLocation::class, $result);
        $this->assertSame('europlatform', $result->getId());
        $this->assertSame('Europlatform', $result->getName());
        $this->assertSame(52.0000, $result->getLatitude());
        $this->assertSame(3.2761, $result->getLongitude());
    }

    public function testFindByIdFindsFirstMatchingLocation(): void
    {
        // Arrange
        $this->createJsonFile([
            [
                'id' => 'vlissingen',
                'name' => 'Vlissingen',
                'latitude' => 51.4424,
                'longitude' => 3.5968,
            ],
        ]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $result = $repository->findById('vlissingen');

        // Assert
        $this->assertInstanceOf(RwsLocation::class, $result);
        $this->assertSame('vlissingen', $result->getId());
    }

    public function testSaveAllCreatesFileWithSingleLocation(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);
        $location = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.4424,
            3.5968,
            ['OW'],
            ['T', 'WATHTE'],
            'sea'
        );

        // Act
        $repository->saveAll([$location]);

        // Assert
        $this->assertFileExists($this->getJsonFilePath());
        $content = file_get_contents($this->getJsonFilePath());
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('vlissingen', $data[0]['id']);
        $this->assertSame('Vlissingen', $data[0]['name']);
        $this->assertSame(51.4424, $data[0]['latitude']);
        $this->assertSame(3.5968, $data[0]['longitude']);
        $this->assertSame(['OW'], $data[0]['compartimenten']);
        $this->assertSame(['T', 'WATHTE'], $data[0]['grootheden']);
        $this->assertSame('sea', $data[0]['waterBodyType']);
    }

    public function testSaveAllCreatesFileWithMultipleLocations(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);
        $locations = [
            new RwsLocation('vlissingen', 'Vlissingen', 51.4424, 3.5968, ['OW'], ['T'], 'sea'),
            new RwsLocation('europlatform', 'Europlatform', 52.0000, 3.2761, ['LW'], ['Hm0'], 'sea'),
        ];

        // Act
        $repository->saveAll($locations);

        // Assert
        $this->assertFileExists($this->getJsonFilePath());
        $content = file_get_contents($this->getJsonFilePath());
        $data = json_decode($content, true);

        $this->assertCount(2, $data);
        $this->assertSame('vlissingen', $data[0]['id']);
        $this->assertSame('europlatform', $data[1]['id']);
    }

    public function testSaveAllCreatesEmptyFile(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);

        // Act
        $repository->saveAll([]);

        // Assert
        $this->assertFileExists($this->getJsonFilePath());
        $content = file_get_contents($this->getJsonFilePath());
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function testSaveAllCreatesDirectoryIfNotExists(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);
        $location = new RwsLocation('test', 'Test', 50.0, 4.0);

        // Act
        $repository->saveAll([$location]);

        // Assert
        $this->assertDirectoryExists($this->projectDir.'/var');
        $this->assertDirectoryExists($this->projectDir.'/var/data');
        $this->assertFileExists($this->getJsonFilePath());
    }

    public function testSaveAllOverwritesExistingFile(): void
    {
        // Arrange
        $this->createJsonFile([
            [
                'id' => 'old-location',
                'name' => 'Old Location',
                'latitude' => 50.0,
                'longitude' => 4.0,
            ],
        ]);
        $repository = new JsonFileRwsLocationRepository($this->projectDir);
        $newLocation = new RwsLocation('new-location', 'New Location', 51.0, 5.0);

        // Act
        $repository->saveAll([$newLocation]);

        // Assert
        $content = file_get_contents($this->getJsonFilePath());
        $data = json_decode($content, true);

        $this->assertCount(1, $data);
        $this->assertSame('new-location', $data[0]['id']);
        $this->assertSame('New Location', $data[0]['name']);
    }

    public function testSaveAllUsesJsonPrettyPrint(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);
        $location = new RwsLocation('test', 'Test', 50.0, 4.0);

        // Act
        $repository->saveAll([$location]);

        // Assert
        $content = file_get_contents($this->getJsonFilePath());
        // Pretty printed JSON should contain newlines and indentation
        $this->assertStringContainsString("\n", $content);
        $this->assertStringContainsString('    ', $content);
    }

    public function testSaveAllPreservesUnicodeCharacters(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);
        $location = new RwsLocation('test', 'Tëst Lõcation with Ümlauts', 50.0, 4.0);

        // Act
        $repository->saveAll([$location]);

        // Assert
        $content = file_get_contents($this->getJsonFilePath());
        $data = json_decode($content, true);

        $this->assertSame('Tëst Lõcation with Ümlauts', $data[0]['name']);
        // Verify that unicode is not escaped
        $this->assertStringContainsString('Tëst', $content);
        $this->assertStringContainsString('Lõcation', $content);
        $this->assertStringContainsString('Ümlauts', $content);
    }

    public function testRoundTripPersistence(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);
        $originalLocations = [
            new RwsLocation(
                'vlissingen',
                'Vlissingen',
                51.4424,
                3.5968,
                ['OW', 'LW'],
                ['T', 'WATHTE', 'Hm0'],
                'sea'
            ),
            new RwsLocation(
                'europlatform',
                'Europlatform',
                52.0000,
                3.2761,
                ['OW'],
                ['WATHTE'],
                'sea'
            ),
        ];

        // Act - Save and then load
        $repository->saveAll($originalLocations);
        $loadedLocations = $repository->findAll();

        // Assert
        $this->assertCount(2, $loadedLocations);

        $this->assertSame('vlissingen', $loadedLocations[0]->getId());
        $this->assertSame('Vlissingen', $loadedLocations[0]->getName());
        $this->assertSame(51.4424, $loadedLocations[0]->getLatitude());
        $this->assertSame(3.5968, $loadedLocations[0]->getLongitude());
        $this->assertSame(['OW', 'LW'], $loadedLocations[0]->getCompartimenten());
        $this->assertSame(['T', 'WATHTE', 'Hm0'], $loadedLocations[0]->getGrootheden());
        $this->assertSame('sea', $loadedLocations[0]->getWaterBodyType());

        $this->assertSame('europlatform', $loadedLocations[1]->getId());
        $this->assertSame('Europlatform', $loadedLocations[1]->getName());
        $this->assertSame(52.0000, $loadedLocations[1]->getLatitude());
        $this->assertSame(3.2761, $loadedLocations[1]->getLongitude());
        $this->assertSame(['OW'], $loadedLocations[1]->getCompartimenten());
        $this->assertSame(['WATHTE'], $loadedLocations[1]->getGrootheden());
        $this->assertSame('sea', $loadedLocations[1]->getWaterBodyType());
    }

    public function testFindByIdAfterSaveAll(): void
    {
        // Arrange
        $repository = new JsonFileRwsLocationRepository($this->projectDir);
        $locations = [
            new RwsLocation('loc1', 'Location 1', 50.0, 4.0),
            new RwsLocation('loc2', 'Location 2', 51.0, 5.0),
            new RwsLocation('loc3', 'Location 3', 52.0, 6.0),
        ];

        // Act
        $repository->saveAll($locations);
        $result = $repository->findById('loc2');

        // Assert
        $this->assertInstanceOf(RwsLocation::class, $result);
        $this->assertSame('loc2', $result->getId());
        $this->assertSame('Location 2', $result->getName());
    }
}
