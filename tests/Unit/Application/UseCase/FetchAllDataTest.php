<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Application\UseCase;

use PHPUnit\Framework\TestCase;
use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Application\UseCase\FetchAllData;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\UVIndex;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaterQuality;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WindSpeed;

final class FetchAllDataTest extends TestCase
{
    public function testExecuteFetchesDataForAllLocations(): void
    {
        $location1 = new Location('loc1', 'Location 1', 51.0, 3.0);
        $location2 = new Location('loc2', 'Location 2', 52.0, 4.0);

        $repository = $this->createMock(LocationRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$location1, $location2]);

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')
            ->willReturnCallback(fn ($loc) => $this->createWaterConditions($loc));

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')
            ->willReturnCallback(fn ($loc) => $this->createWeatherConditions($loc));

        $useCase = new FetchAllData($repository, $waterProvider, $weatherProvider);
        $result = $useCase->execute();

        $this->assertSame(2, $result['locations']);
        $this->assertSame(2, $result['water']);
        $this->assertSame(2, $result['weather']);
    }

    public function testExecuteCountsPartialFetches(): void
    {
        $location = new Location('loc1', 'Location 1', 51.0, 3.0);

        $repository = $this->createMock(LocationRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$location]);

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $waterProvider->method('getConditions')->willReturn(null);

        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);
        $weatherProvider->method('getConditions')
            ->willReturn($this->createWeatherConditions($location));

        $useCase = new FetchAllData($repository, $waterProvider, $weatherProvider);
        $result = $useCase->execute();

        $this->assertSame(1, $result['locations']);
        $this->assertSame(0, $result['water']);
        $this->assertSame(1, $result['weather']);
    }

    public function testExecuteHandlesEmptyLocationList(): void
    {
        $repository = $this->createMock(LocationRepositoryInterface::class);
        $repository->method('findAll')->willReturn([]);

        $waterProvider = $this->createMock(WaterConditionsProviderInterface::class);
        $weatherProvider = $this->createMock(WeatherConditionsProviderInterface::class);

        $useCase = new FetchAllData($repository, $waterProvider, $weatherProvider);
        $result = $useCase->execute();

        $this->assertSame(0, $result['locations']);
        $this->assertSame(0, $result['water']);
        $this->assertSame(0, $result['weather']);
    }

    private function createWaterConditions(Location $location): WaterConditions
    {
        return new WaterConditions(
            $location,
            Temperature::fromCelsius(18.0),
            WaveHeight::fromMeters(0.5),
            WaterHeight::fromMeters(0.0),
            WaterQuality::Good,
            new \DateTimeImmutable(),
        );
    }

    private function createWeatherConditions(Location $location): WeatherConditions
    {
        return new WeatherConditions(
            $location,
            Temperature::fromCelsius(22.0),
            WindSpeed::fromMetersPerSecond(5.0),
            'N',
            UVIndex::fromValue(5),
            new \DateTimeImmutable(),
        );
    }
}
