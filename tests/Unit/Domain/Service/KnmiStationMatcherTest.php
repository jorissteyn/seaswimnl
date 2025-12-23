<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\KnmiStationRepositoryInterface;
use Seaswim\Domain\Service\KnmiStationMatcher;
use Seaswim\Domain\ValueObject\KnmiStation;

final class KnmiStationMatcherTest extends TestCase
{
    private KnmiStationMatcher $matcher;
    private KnmiStationRepositoryInterface $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(KnmiStationRepositoryInterface::class);
        $this->matcher = new KnmiStationMatcher($this->repository);
    }

    public function testExactFirstWordMatch(): void
    {
        $vlissingen = new KnmiStation('310', 'Vlissingen', 51.44, 3.60);

        $this->repository->method('findAll')->willReturn([
            new KnmiStation('260', 'De Bilt', 52.10, 5.18),
            $vlissingen,
            new KnmiStation('235', 'De Kooy', 52.92, 4.79),
        ]);

        $result = $this->matcher->findMatchingStation('Vlissingen havenmond');

        $this->assertSame($vlissingen, $result);
    }

    public function testMatchWithDotInName(): void
    {
        $hoekVanHolland = new KnmiStation('330', 'Hoek van Holland', 51.98, 4.12);

        $this->repository->method('findAll')->willReturn([
            new KnmiStation('260', 'De Bilt', 52.10, 5.18),
            $hoekVanHolland,
        ]);

        $result = $this->matcher->findMatchingStation('Hoek.v.Holland meetpaal');

        $this->assertSame($hoekVanHolland, $result);
    }

    public function testMatchCaseInsensitive(): void
    {
        $vlissingen = new KnmiStation('310', 'Vlissingen', 51.44, 3.60);

        $this->repository->method('findAll')->willReturn([
            new KnmiStation('260', 'De Bilt', 52.10, 5.18),
            $vlissingen,
        ]);

        $result = $this->matcher->findMatchingStation('VLISSINGEN');

        $this->assertSame($vlissingen, $result);
    }

    public function testFuzzyMatchWithinThreshold(): void
    {
        $schiphol = new KnmiStation('240', 'Schiphol', 52.30, 4.77);

        $this->repository->method('findAll')->willReturn([
            new KnmiStation('260', 'De Bilt', 52.10, 5.18),
            $schiphol,
        ]);

        // "Schipol" (one letter off) should still match "Schiphol"
        $result = $this->matcher->findMatchingStation('Schipol meetstation');

        $this->assertSame($schiphol, $result);
    }

    public function testDefaultsToDeBiltWhenNoMatch(): void
    {
        $deBilt = new KnmiStation('260', 'De Bilt', 52.10, 5.18);

        $this->repository->method('findAll')->willReturn([
            $deBilt,
            new KnmiStation('310', 'Vlissingen', 51.44, 3.60),
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
        $scheveningen = new KnmiStation('210', 'Valkenburg', 52.17, 4.42);

        $this->repository->method('findAll')->willReturn([
            new KnmiStation('260', 'De Bilt', 52.10, 5.18),
            $scheveningen,
        ]);

        // "Valkenburg" first word should match
        $result = $this->matcher->findMatchingStation('Valkenburg buitenhaven zuidelijk');

        $this->assertSame($scheveningen, $result);
    }
}
