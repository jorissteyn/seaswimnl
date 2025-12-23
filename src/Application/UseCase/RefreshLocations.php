<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\BuienradarStationRepositoryInterface;
use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Domain\ValueObject\BuienradarStation;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Infrastructure\ExternalApi\Client\BuienradarHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final readonly class RefreshLocations
{
    public function __construct(
        private LocationRepositoryInterface $locationRepository,
        private RwsHttpClientInterface $rwsClient,
        private BuienradarStationRepositoryInterface $buienradarStationRepository,
        private BuienradarHttpClientInterface $buienradarClient,
    ) {
    }

    /**
     * @return array{locations: int, stations: int}
     */
    public function execute(): array
    {
        $locationsCount = $this->refreshRwsLocations();
        $stationsCount = $this->refreshBuienradarStations();

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

    private function refreshBuienradarStations(): int
    {
        $data = $this->buienradarClient->fetchStations();

        if (null === $data) {
            return -1; // Error
        }

        $stations = [];
        foreach ($data as $item) {
            $stations[] = new BuienradarStation(
                $item['code'],
                $item['name'],
                $item['latitude'],
                $item['longitude'],
            );
        }

        $this->buienradarStationRepository->saveAll($stations);

        return count($stations);
    }
}
