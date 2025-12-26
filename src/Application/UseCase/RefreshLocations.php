<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\WeatherStationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\WeatherStation;
use Seaswim\Infrastructure\ExternalApi\Client\BuienradarHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final readonly class RefreshLocations
{
    public function __construct(
        private RwsLocationRepositoryInterface $locationRepository,
        private RwsHttpClientInterface $rwsClient,
        private WeatherStationRepositoryInterface $weatherStationRepository,
        private BuienradarHttpClientInterface $buienradarClient,
    ) {
    }

    /**
     * @return array{locations: int, stations: int}
     */
    public function execute(): array
    {
        return [
            'locations' => $this->refreshRwsLocations(),
            'stations' => $this->refreshWeatherStations(),
        ];
    }

    /**
     * @return int Number of locations imported, or -1 on error
     */
    private function refreshRwsLocations(): int
    {
        $data = $this->rwsClient->fetchLocations();

        if (null === $data) {
            return -1;
        }

        $locations = [];
        foreach ($data as $item) {
            $locations[] = new RwsLocation(
                $item['code'],
                $item['name'],
                $item['latitude'],
                $item['longitude'],
                $item['compartimenten'] ?? [],
                $item['grootheden'] ?? [],
            );
        }

        $this->locationRepository->saveAll($locations);

        return count($locations);
    }

    /**
     * @return int Number of stations imported, or -1 on error
     */
    private function refreshWeatherStations(): int
    {
        $data = $this->buienradarClient->fetchStations();

        if (null === $data) {
            return -1;
        }

        $stations = [];
        foreach ($data as $item) {
            $stations[] = new WeatherStation(
                $item['code'],
                $item['name'],
                $item['latitude'],
                $item['longitude'],
            );
        }

        $this->weatherStationRepository->saveAll($stations);

        return count($stations);
    }
}
