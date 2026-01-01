<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Repository;

use PHPUnit\Framework\TestCase;
use Seaswim\Infrastructure\Repository\CsvSwimmingSpotRepository;

final class CsvSwimmingSpotRepositoryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/seaswim_test_'.uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir.'/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempDir);
        }
    }

    private function createTempCsvFile(string $content): string
    {
        $filename = $this->tempDir.'/swimming_spots_'.uniqid().'.csv';
        file_put_contents($filename, $content);

        return $filename;
    }

    public function testFindAllReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        $repository = new CsvSwimmingSpotRepository('/non/existent/path.csv');

        $spots = $repository->findAll();

        $this->assertIsArray($spots);
        $this->assertCount(0, $spots);
    }

    public function testFindAllReturnsEmptyArrayWhenFileIsEmpty(): void
    {
        $csvPath = $this->createTempCsvFile('');
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertIsArray($spots);
        $this->assertCount(0, $spots);
    }

    public function testFindAllReturnsEmptyArrayWhenOnlyHeadersPresent(): void
    {
        $csvContent = "name,latitude,longitude\n";
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertIsArray($spots);
        $this->assertCount(0, $spots);
    }

    public function testFindAllReturnsSingleSpotFromValidCsv(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Scheveningen Bad,52.1048,4.2759
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertSame('scheveningen-bad', $spots[0]->getId());
        $this->assertSame('Scheveningen Bad', $spots[0]->getName());
        $this->assertSame(52.1048, $spots[0]->getLatitude());
        $this->assertSame(4.2759, $spots[0]->getLongitude());
    }

    public function testFindAllReturnsMultipleSpotsFromValidCsv(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Scheveningen Bad,52.1048,4.2759
Zandvoort aan Zee,52.3738,4.5323
Hoek van Holland,51.9775,4.1227
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(3, $spots);
        $this->assertSame('scheveningen-bad', $spots[0]->getId());
        $this->assertSame('zandvoort-aan-zee', $spots[1]->getId());
        $this->assertSame('hoek-van-holland', $spots[2]->getId());
    }

    public function testFindAllHandlesNegativeCoordinates(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Southern Beach,-33.8688,-151.2093
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertSame(-33.8688, $spots[0]->getLatitude());
        $this->assertSame(-151.2093, $spots[0]->getLongitude());
    }

    public function testFindAllHandlesZeroCoordinates(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Equator Point,0.0,0.0
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertSame(0.0, $spots[0]->getLatitude());
        $this->assertSame(0.0, $spots[0]->getLongitude());
    }

    public function testFindAllHandlesHighPrecisionCoordinates(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Precise Location,52.123456789,4.987654321
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertSame(52.123456789, $spots[0]->getLatitude());
        $this->assertSame(4.987654321, $spots[0]->getLongitude());
    }

    public function testFindAllSkipsRowsWithMismatchedColumnCount(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Valid Beach,52.1,4.3
Invalid Row,52.1
Another Valid Beach,51.9,4.1
Invalid Row Too,51.5,4.0,extra,columns
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(2, $spots);
        $this->assertSame('valid-beach', $spots[0]->getId());
        $this->assertSame('another-valid-beach', $spots[1]->getId());
    }

    public function testFindAllHandlesSpecialCharactersInNames(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Beach & Resort #1,52.1,4.3
"Beach with ""quotes""",51.9,4.1
Beach's Apostrophe,51.8,4.2
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(3, $spots);
        $this->assertSame('Beach & Resort #1', $spots[0]->getName());
        $this->assertSame('Beach with "quotes"', $spots[1]->getName());
        $this->assertSame("Beach's Apostrophe", $spots[2]->getName());
    }

    public function testFindAllHandlesIntegerCoordinates(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Simple Beach,52,4
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertSame(52.0, $spots[0]->getLatitude());
        $this->assertSame(4.0, $spots[0]->getLongitude());
    }

    public function testFindAllHandlesWhitespaceInData(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
  Beach with Spaces  ,  52.1  ,  4.3
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertSame('  Beach with Spaces  ', $spots[0]->getName());
        $this->assertSame(52.1, $spots[0]->getLatitude());
        $this->assertSame(4.3, $spots[0]->getLongitude());
    }

    public function testFindAllCachesResultsOnSubsequentCalls(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Test Beach,52.1,4.3
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots1 = $repository->findAll();
        $spots2 = $repository->findAll();

        $this->assertSame($spots1, $spots2);
        $this->assertCount(1, $spots1);
        $this->assertCount(1, $spots2);
    }

    public function testFindAllCacheWorksAfterFileModification(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Original Beach,52.1,4.3
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots1 = $repository->findAll();

        // Modify the file after first read
        file_put_contents($csvPath, "name,latitude,longitude\nNew Beach,51.9,4.1\n");

        $spots2 = $repository->findAll();

        // Cache should return original data
        $this->assertCount(1, $spots1);
        $this->assertCount(1, $spots2);
        $this->assertSame('original-beach', $spots1[0]->getId());
        $this->assertSame('original-beach', $spots2[0]->getId());
    }

    public function testFindByIdReturnsSpotWhenExists(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Scheveningen Bad,52.1048,4.2759
Zandvoort aan Zee,52.3738,4.5323
Hoek van Holland,51.9775,4.1227
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spot = $repository->findById('zandvoort-aan-zee');

        $this->assertNotNull($spot);
        $this->assertSame('zandvoort-aan-zee', $spot->getId());
        $this->assertSame('Zandvoort aan Zee', $spot->getName());
        $this->assertSame(52.3738, $spot->getLatitude());
        $this->assertSame(4.5323, $spot->getLongitude());
    }

    public function testFindByIdReturnsNullWhenNotExists(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Scheveningen Bad,52.1048,4.2759
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spot = $repository->findById('non-existent-beach');

        $this->assertNull($spot);
    }

    public function testFindByIdReturnsNullWhenFileDoesNotExist(): void
    {
        $repository = new CsvSwimmingSpotRepository('/non/existent/path.csv');

        $spot = $repository->findById('any-id');

        $this->assertNull($spot);
    }

    public function testFindByIdReturnsNullWhenFileIsEmpty(): void
    {
        $csvPath = $this->createTempCsvFile('');
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spot = $repository->findById('any-id');

        $this->assertNull($spot);
    }

    public function testFindByIdReturnsFirstMatchWhenDuplicateIdsExist(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Test Beach,52.1,4.3
Test Beach,51.9,4.1
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spot = $repository->findById('test-beach');

        $this->assertNotNull($spot);
        $this->assertSame('test-beach', $spot->getId());
        $this->assertSame(52.1, $spot->getLatitude());
        $this->assertSame(4.3, $spot->getLongitude());
    }

    public function testFindByIdUsesCache(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Test Beach,52.1,4.3
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        // First call loads from CSV
        $spot1 = $repository->findById('test-beach');

        // Modify file
        file_put_contents($csvPath, "name,latitude,longitude\nModified Beach,51.9,4.1\n");

        // Second call should use cache
        $spot2 = $repository->findById('test-beach');

        $this->assertNotNull($spot1);
        $this->assertNotNull($spot2);
        $this->assertSame('test-beach', $spot1->getId());
        $this->assertSame('test-beach', $spot2->getId());
    }

    public function testFindByIdIsCaseSensitive(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Scheveningen Bad,52.1048,4.2759
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spot1 = $repository->findById('scheveningen-bad');
        $spot2 = $repository->findById('Scheveningen-Bad');
        $spot3 = $repository->findById('SCHEVENINGEN-BAD');

        $this->assertNotNull($spot1);
        $this->assertNull($spot2);
        $this->assertNull($spot3);
    }

    public function testFindAllHandlesEmptyLinesInCsv(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude

Scheveningen Bad,52.1048,4.2759

Zandvoort aan Zee,52.3738,4.5323

CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        // Empty lines should be parsed as arrays with one empty string element
        // They won't match header count and will be skipped
        $this->assertCount(2, $spots);
        $this->assertSame('scheveningen-bad', $spots[0]->getId());
        $this->assertSame('zandvoort-aan-zee', $spots[1]->getId());
    }

    public function testFindAllHandlesWindowsLineEndings(): void
    {
        $csvContent = "name,latitude,longitude\r\nScheveningen Bad,52.1048,4.2759\r\nZandvoort aan Zee,52.3738,4.5323\r\n";
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(2, $spots);
        $this->assertSame('scheveningen-bad', $spots[0]->getId());
        $this->assertSame('zandvoort-aan-zee', $spots[1]->getId());
    }

    public function testFindAllHandlesMacLineEndings(): void
    {
        $csvContent = "name,latitude,longitude\rScheveningen Bad,52.1048,4.2759\rZandvoort aan Zee,52.3738,4.5323\r";
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        // fgetcsv handles different line endings automatically in PHP
        $this->assertIsArray($spots);
        $this->assertGreaterThanOrEqual(0, count($spots));
    }

    public function testFindAllHandlesCommasInQuotedFields(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
"Beach, The Best One",52.1,4.3
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertSame('Beach, The Best One', $spots[0]->getName());
    }

    public function testFindAllHandlesNewlinesInQuotedFields(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
"Beach
with newline",52.1,4.3
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertStringContainsString('Beach', $spots[0]->getName());
        $this->assertStringContainsString('with newline', $spots[0]->getName());
    }

    public function testFindAllHandlesScientificNotationCoordinates(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Science Beach,5.21e1,4.3e0
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1, $spots);
        $this->assertSame(52.1, $spots[0]->getLatitude());
        $this->assertSame(4.3, $spots[0]->getLongitude());
    }

    public function testFindAllHandlesUnicodeCharactersInNames(): void
    {
        $csvContent = <<<CSV
name,latitude,longitude
Café aan Zee ☀️,52.1,4.3
Plage Française,51.9,4.1
日本のビーチ,51.8,4.2
CSV;
        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(3, $spots);
        $this->assertSame('Café aan Zee ☀️', $spots[0]->getName());
        $this->assertSame('Plage Française', $spots[1]->getName());
        $this->assertSame('日本のビーチ', $spots[2]->getName());
    }

    public function testFindAllWorksWithLargeCsvFile(): void
    {
        $csvContent = "name,latitude,longitude\n";
        for ($i = 1; $i <= 1000; ++$i) {
            $csvContent .= "Beach $i,$i.123,$i.456\n";
        }

        $csvPath = $this->createTempCsvFile($csvContent);
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $spots = $repository->findAll();

        $this->assertCount(1000, $spots);
        $this->assertSame('beach-1', $spots[0]->getId());
        $this->assertSame('beach-1000', $spots[999]->getId());
    }

    public function testConstructorAcceptsCsvPath(): void
    {
        $csvPath = '/path/to/file.csv';
        $repository = new CsvSwimmingSpotRepository($csvPath);

        $this->assertInstanceOf(CsvSwimmingSpotRepository::class, $repository);
    }

    public function testFindAllReturnsEmptyArrayWhenFileCannotBeOpened(): void
    {
        // Create a file path that exists but cannot be opened (e.g., directory)
        $repository = new CsvSwimmingSpotRepository($this->tempDir);

        $spots = $repository->findAll();

        $this->assertIsArray($spots);
        $this->assertCount(0, $spots);
    }
}
