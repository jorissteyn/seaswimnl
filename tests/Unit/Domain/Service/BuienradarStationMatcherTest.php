<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\BuienradarStationRepositoryInterface;
use Seaswim\Domain\Service\BuienradarStationMatcher;
use Seaswim\Domain\ValueObject\BuienradarStation;

final class BuienradarStationMatcherTest extends TestCase
{
    private BuienradarStationMatcher $matcher;
    private BuienradarStationRepositoryInterface $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(BuienradarStationRepositoryInterface::class);
        $this->matcher = new BuienradarStationMatcher($this->repository);
    }

    public function testExactFirstWordMatch(): void
    {
        $vlissingen = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);

        $this->repository->method('findAll')->willReturn([
            new BuienradarStation('6260', 'De Bilt', 52.10, 5.18),
            $vlissingen,
            new BuienradarStation('6235', 'De Kooy', 52.92, 4.79),
        ]);

        $result = $this->matcher->findMatchingStation('Vlissingen havenmond');

        $this->assertSame($vlissingen, $result);
    }

    public function testMatchWithDotInName(): void
    {
        $hoekVanHolland = new BuienradarStation('6330', 'Hoek van Holland', 51.98, 4.12);

        $this->repository->method('findAll')->willReturn([
            new BuienradarStation('6260', 'De Bilt', 52.10, 5.18),
            $hoekVanHolland,
        ]);

        $result = $this->matcher->findMatchingStation('Hoek.v.Holland meetpaal');

        $this->assertSame($hoekVanHolland, $result);
    }

    public function testMatchCaseInsensitive(): void
    {
        $vlissingen = new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60);

        $this->repository->method('findAll')->willReturn([
            new BuienradarStation('6260', 'De Bilt', 52.10, 5.18),
            $vlissingen,
        ]);

        $result = $this->matcher->findMatchingStation('VLISSINGEN');

        $this->assertSame($vlissingen, $result);
    }

    public function testFuzzyMatchWithinThreshold(): void
    {
        $schiphol = new BuienradarStation('6240', 'Schiphol', 52.30, 4.77);

        $this->repository->method('findAll')->willReturn([
            new BuienradarStation('6260', 'De Bilt', 52.10, 5.18),
            $schiphol,
        ]);

        // "Schipol" (one letter off) should still match "Schiphol"
        $result = $this->matcher->findMatchingStation('Schipol meetstation');

        $this->assertSame($schiphol, $result);
    }

    public function testDefaultsToDeBiltWhenNoMatch(): void
    {
        $deBilt = new BuienradarStation('6260', 'De Bilt', 52.10, 5.18);

        $this->repository->method('findAll')->willReturn([
            $deBilt,
            new BuienradarStation('6310', 'Vlissingen', 51.44, 3.60),
        ]);

        $result = $this->matcher->findMatchingStation('Offshore platform XYZ');

        $this->assertSame($deBilt, $result);
    }

    public function testNoMatchWhenNoStations(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $result = $this->matcher->findMatchingStation('Vlissingen');

        $this->assertNull($result);
    }

    public function testMatchWithDirectionalSuffix(): void
    {
        $valkenburg = new BuienradarStation('6210', 'Valkenburg', 52.17, 4.42);

        $this->repository->method('findAll')->willReturn([
            new BuienradarStation('6260', 'De Bilt', 52.10, 5.18),
            $valkenburg,
        ]);

        // "Valkenburg" first word should match
        $result = $this->matcher->findMatchingStation('Valkenburg buitenhaven zuidelijk');

        $this->assertSame($valkenburg, $result);
    }
}
