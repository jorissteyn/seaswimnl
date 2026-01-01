<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Repository;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\WeatherStation;
use Seaswim\Infrastructure\Repository\JsonFileWeatherStationRepository;

final class JsonFileWeatherStationRepositoryTest extends TestCase
{
    private string $tempDir;
    private string $projectDir;
    private JsonFileWeatherStationRepository $repository;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/seaswim-test-'.uniqid();
        $this->projectDir = $this->tempDir;
        mkdir($this->tempDir, 0755, true);
        $this->repository = new JsonFileWeatherStationRepository($this->projectDir);
    }

    protected function tearDown(): void
    {
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

    private function createJsonFile(string $content): void
    {
        $dataDir = $this->projectDir.'/var/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($dataDir.'/weather-stations.json', $content);
    }

    public function testFindAllReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        // Arrange - file does not exist

        // Act
        $stations = $this->repository->findAll();

        // Assert
        $this->assertIsArray($stations);
        $this->assertEmpty($stations);
    }

    public function testFindAllReturnsWeatherStationsFromJsonFile(): void
    {
        // Arrange
        $jsonData = [
            [
                'code' => '6310',
                'name' => 'Vlissingen',
                'latitude' => 51.44,
                'longitude' => 3.60,
            ],
            [
                'code' => '6260',
                'name' => 'De Bilt',
                'latitude' => 52.10,
                'longitude' => 5.18,
            ],
        ];
        $this->createJsonFile(json_encode($jsonData));

        // Act
        $stations = $this->repository->findAll();

        // Assert
        $this->assertCount(2, $stations);
        $this->assertContainsOnlyInstancesOf(WeatherStation::class, $stations);

        $this->assertSame('6310', $stations[0]->getCode());
        $this->assertSame('Vlissingen', $stations[0]->getName());
        $this->assertSame(51.44, $stations[0]->getLatitude());
        $this->assertSame(3.60, $stations[0]->getLongitude());

        $this->assertSame('6260', $stations[1]->getCode());
        $this->assertSame('De Bilt', $stations[1]->getName());
        $this->assertSame(52.10, $stations[1]->getLatitude());
        $this->assertSame(5.18, $stations[1]->getLongitude());
    }

    public function testFindAllReturnsEmptyArrayWhenFileContentIsEmpty(): void
    {
        // Arrange
        $this->createJsonFile('');

        // Act
        $stations = $this->repository->findAll();

        // Assert
        $this->assertIsArray($stations);
        $this->assertEmpty($stations);
    }

    public function testFindAllReturnsEmptyArrayWhenJsonIsInvalid(): void
    {
        // Arrange
        $this->createJsonFile('{"invalid json');

        // Act
        $stations = $this->repository->findAll();

        // Assert
        $this->assertIsArray($stations);
        $this->assertEmpty($stations);
    }

    public function testFindAllReturnsEmptyArrayWhenJsonIsNull(): void
    {
        // Arrange
        $this->createJsonFile('null');

        // Act
        $stations = $this->repository->findAll();

        // Assert
        $this->assertIsArray($stations);
        $this->assertEmpty($stations);
    }

    public function testFindAllHandlesEmptyJsonArray(): void
    {
        // Arrange
        $this->createJsonFile('[]');

        // Act
        $stations = $this->repository->findAll();

        // Assert
        $this->assertIsArray($stations);
        $this->assertEmpty($stations);
    }

    public function testFindAllConvertsNumericStringsToFloats(): void
    {
        // Arrange
        $jsonData = [
            [
                'code' => '6310',
                'name' => 'Vlissingen',
                'latitude' => '51.44',
                'longitude' => '3.60',
            ],
        ];
        $this->createJsonFile(json_encode($jsonData));

        // Act
        $stations = $this->repository->findAll();

        // Assert
        $this->assertCount(1, $stations);
        $this->assertSame(51.44, $stations[0]->getLatitude());
        $this->assertSame(3.60, $stations[0]->getLongitude());
    }

    public function testFindByCodeReturnsStationWhenExists(): void
    {
        // Arrange
        $jsonData = [
            [
                'code' => '6310',
                'name' => 'Vlissingen',
                'latitude' => 51.44,
                'longitude' => 3.60,
            ],
            [
                'code' => '6260',
                'name' => 'De Bilt',
                'latitude' => 52.10,
                'longitude' => 5.18,
            ],
        ];
        $this->createJsonFile(json_encode($jsonData));

        // Act
        $station = $this->repository->findByCode('6260');

        // Assert
        $this->assertInstanceOf(WeatherStation::class, $station);
        $this->assertSame('6260', $station->getCode());
        $this->assertSame('De Bilt', $station->getName());
        $this->assertSame(52.10, $station->getLatitude());
        $this->assertSame(5.18, $station->getLongitude());
    }

    public function testFindByCodeReturnsNullWhenStationDoesNotExist(): void
    {
        // Arrange
        $jsonData = [
            [
                'code' => '6310',
                'name' => 'Vlissingen',
                'latitude' => 51.44,
                'longitude' => 3.60,
            ],
        ];
        $this->createJsonFile(json_encode($jsonData));

        // Act
        $station = $this->repository->findByCode('9999');

        // Assert
        $this->assertNull($station);
    }

    public function testFindByCodeReturnsNullWhenFileDoesNotExist(): void
    {
        // Arrange - file does not exist

        // Act
        $station = $this->repository->findByCode('6310');

        // Assert
        $this->assertNull($station);
    }

    public function testFindByCodeReturnsFirstMatchingStation(): void
    {
        // Arrange - duplicate codes (edge case)
        $jsonData = [
            [
                'code' => '6310',
                'name' => 'Vlissingen',
                'latitude' => 51.44,
                'longitude' => 3.60,
            ],
            [
                'code' => '6310',
                'name' => 'Duplicate Station',
                'latitude' => 50.00,
                'longitude' => 4.00,
            ],
        ];
        $this->createJsonFile(json_encode($jsonData));

        // Act
        $station = $this->repository->findByCode('6310');

        // Assert
        $this->assertInstanceOf(WeatherStation::class, $station);
        $this->assertSame('6310', $station->getCode());
        $this->assertSame('Vlissingen', $station->getName());
    }

    public function testSaveAllWritesStationsToJsonFile(): void
    {
        // Arrange
        $stations = [
            new WeatherStation('6310', 'Vlissingen', 51.44, 3.60),
            new WeatherStation('6260', 'De Bilt', 52.10, 5.18),
        ];

        // Act
        $this->repository->saveAll($stations);

        // Assert
        $filePath = $this->projectDir.'/var/data/weather-stations.json';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        $this->assertSame('6310', $data[0]['code']);
        $this->assertSame('Vlissingen', $data[0]['name']);
        $this->assertSame(51.44, $data[0]['latitude']);
        $this->assertSame(3.60, $data[0]['longitude']);

        $this->assertSame('6260', $data[1]['code']);
        $this->assertSame('De Bilt', $data[1]['name']);
        $this->assertSame(52.10, $data[1]['latitude']);
        $this->assertSame(5.18, $data[1]['longitude']);
    }

    public function testSaveAllCreatesDirectoryIfNotExists(): void
    {
        // Arrange
        $stations = [
            new WeatherStation('6310', 'Vlissingen', 51.44, 3.60),
        ];

        // Act
        $this->repository->saveAll($stations);

        // Assert
        $dataDir = $this->projectDir.'/var/data';
        $this->assertDirectoryExists($dataDir);
        $this->assertFileExists($dataDir.'/weather-stations.json');
    }

    public function testSaveAllOverwritesExistingFile(): void
    {
        // Arrange
        $initialStations = [
            new WeatherStation('6310', 'Vlissingen', 51.44, 3.60),
        ];
        $this->repository->saveAll($initialStations);

        $newStations = [
            new WeatherStation('6260', 'De Bilt', 52.10, 5.18),
            new WeatherStation('6270', 'Leeuwarden', 53.22, 5.76),
        ];

        // Act
        $this->repository->saveAll($newStations);

        // Assert
        $filePath = $this->projectDir.'/var/data/weather-stations.json';
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        $this->assertCount(2, $data);
        $this->assertSame('6260', $data[0]['code']);
        $this->assertSame('6270', $data[1]['code']);
    }

    public function testSaveAllWritesEmptyArrayWhenNoStations(): void
    {
        // Arrange
        $stations = [];

        // Act
        $this->repository->saveAll($stations);

        // Assert
        $filePath = $this->projectDir.'/var/data/weather-stations.json';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testSaveAllFormatsJsonWithPrettyPrint(): void
    {
        // Arrange
        $stations = [
            new WeatherStation('6310', 'Vlissingen', 51.44, 3.60),
        ];

        // Act
        $this->repository->saveAll($stations);

        // Assert
        $filePath = $this->projectDir.'/var/data/weather-stations.json';
        $content = file_get_contents($filePath);

        // Check that JSON is formatted (contains newlines and indentation)
        $this->assertStringContainsString("\n", $content);
        $this->assertStringContainsString('    ', $content);
    }

    public function testSaveAllHandlesUnicodeCharacters(): void
    {
        // Arrange
        $stations = [
            new WeatherStation('TEST', 'Ámsterdam', 52.37, 4.89),
        ];

        // Act
        $this->repository->saveAll($stations);

        // Assert
        $filePath = $this->projectDir.'/var/data/weather-stations.json';
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        $this->assertSame('Ámsterdam', $data[0]['name']);
        // Verify unicode is not escaped
        $this->assertStringContainsString('Ámsterdam', $content);
    }

    public function testRoundTripPersistence(): void
    {
        // Arrange
        $originalStations = [
            new WeatherStation('6310', 'Vlissingen', 51.44, 3.60),
            new WeatherStation('6260', 'De Bilt', 52.10, 5.18),
        ];

        // Act - save and then load
        $this->repository->saveAll($originalStations);
        $loadedStations = $this->repository->findAll();

        // Assert
        $this->assertCount(2, $loadedStations);
        $this->assertSame('6310', $loadedStations[0]->getCode());
        $this->assertSame('Vlissingen', $loadedStations[0]->getName());
        $this->assertSame(51.44, $loadedStations[0]->getLatitude());
        $this->assertSame(3.60, $loadedStations[0]->getLongitude());

        $this->assertSame('6260', $loadedStations[1]->getCode());
        $this->assertSame('De Bilt', $loadedStations[1]->getName());
        $this->assertSame(52.10, $loadedStations[1]->getLatitude());
        $this->assertSame(5.18, $loadedStations[1]->getLongitude());
    }

    public function testFindAllHandlesIntegerCoordinates(): void
    {
        // Arrange
        $jsonData = [
            [
                'code' => '6310',
                'name' => 'Vlissingen',
                'latitude' => 51,
                'longitude' => 3,
            ],
        ];
        $this->createJsonFile(json_encode($jsonData));

        // Act
        $stations = $this->repository->findAll();

        // Assert
        $this->assertCount(1, $stations);
        $this->assertSame(51.0, $stations[0]->getLatitude());
        $this->assertSame(3.0, $stations[0]->getLongitude());
    }

    public function testFindByCodeIsCaseSensitive(): void
    {
        // Arrange
        $jsonData = [
            [
                'code' => 'ABC123',
                'name' => 'Test Station',
                'latitude' => 51.44,
                'longitude' => 3.60,
            ],
        ];
        $this->createJsonFile(json_encode($jsonData));

        // Act
        $stationUpperCase = $this->repository->findByCode('ABC123');
        $stationLowerCase = $this->repository->findByCode('abc123');

        // Assert
        $this->assertInstanceOf(WeatherStation::class, $stationUpperCase);
        $this->assertNull($stationLowerCase);
    }

    public function testSaveAllHandlesNegativeCoordinates(): void
    {
        // Arrange
        $stations = [
            new WeatherStation('SOUTH', 'Southern Station', -45.87, -170.50),
        ];

        // Act
        $this->repository->saveAll($stations);

        // Assert
        $loadedStations = $this->repository->findAll();
        $this->assertCount(1, $loadedStations);
        $this->assertSame(-45.87, $loadedStations[0]->getLatitude());
        $this->assertSame(-170.50, $loadedStations[0]->getLongitude());
    }

    public function testSaveAllHandlesStationWithZeroCoordinates(): void
    {
        // Arrange
        $stations = [
            new WeatherStation('ZERO', 'Null Island', 0.0, 0.0),
        ];

        // Act
        $this->repository->saveAll($stations);

        // Assert
        $loadedStations = $this->repository->findAll();
        $this->assertCount(1, $loadedStations);
        $this->assertSame(0.0, $loadedStations[0]->getLatitude());
        $this->assertSame(0.0, $loadedStations[0]->getLongitude());
    }

    public function testSaveAllHandlesStationWithVeryPreciseCoordinates(): void
    {
        // Arrange
        $stations = [
            new WeatherStation('PRECISE', 'Precise Station', 51.123456789, 3.987654321),
        ];

        // Act
        $this->repository->saveAll($stations);

        // Assert
        $loadedStations = $this->repository->findAll();
        $this->assertCount(1, $loadedStations);
        $this->assertSame(51.123456789, $loadedStations[0]->getLatitude());
        $this->assertSame(3.987654321, $loadedStations[0]->getLongitude());
    }
}
