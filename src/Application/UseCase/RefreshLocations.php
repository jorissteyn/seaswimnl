<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\KnmiStationRepositoryInterface;
use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Domain\ValueObject\KnmiStation;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Infrastructure\ExternalApi\Client\KnmiHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final readonly class RefreshLocations
{
    public function __construct(
        private LocationRepositoryInterface $locationRepository,
        private RwsHttpClientInterface $rwsClient,
        private KnmiStationRepositoryInterface $knmiStationRepository,
        private KnmiHttpClientInterface $knmiClient,
    ) {
    }

    /**
     * @return array{locations: int, stations: int}
     */
    public function execute(): array
    {
        $locationsCount = $this->refreshRwsLocations();
        $stationsCount = $this->refreshKnmiStations();

        return [
            'locations' => $locationsCount,
            'stations' => $stationsCount,
        ];
    }

    private function refreshRwsLocations(): int
    {
        $data = $this->rwsClient->fetchLocations();

        if (null === $data) {
            return -1; // Error
        }

        $locations = [];
        foreach ($data as $item) {
            $locations[] = new Location(
                $item['code'],
                $item['name'],
                $item['latitude'],
                $item['longitude'],
            );
        }

        $this->locationRepository->saveAll($locations);

        return count($locations);
    }

    private function refreshKnmiStations(): int
    {
        $data = $this->knmiClient->fetchStations();

        if (null === $data) {
            return -1; // Error
        }

        $stations = [];
        foreach ($data as $item) {
            $stations[] = new KnmiStation(
                $item['code'],
                $item['name'],
                $item['latitude'],
                $item['longitude'],
            );
        }

        $this->knmiStationRepository->saveAll($stations);

        return count($stations);
    }
}
