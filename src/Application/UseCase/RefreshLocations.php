<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\BuienradarStationRepositoryInterface;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\BuienradarStation;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Infrastructure\ExternalApi\Client\BuienradarHttpClientInterface;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final readonly class RefreshLocations
{
    public function __construct(
        private RwsLocationRepositoryInterface $locationRepository,
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

    /**
     * Required grootheden for a location to be included in the dashboard.
     */
    private const REQUIRED_GROOTHEDEN = [
        'WATHTE',   // Tides (water height)
        'WINDSHD',  // Wind speed
        'WINDRTG',  // Wind direction
        'Hm0',      // Wave height
    ];

    private function refreshRwsLocations(): int
    {
        $data = $this->rwsClient->fetchLocations();

        if (null === $data) {
            return -1; // Error
        }

        $locations = [];
        foreach ($data as $item) {
            $grootheden = $item['grootheden'] ?? [];

            // Only include locations that have all required capabilities
            if (!$this->hasRequiredCapabilities($grootheden)) {
                continue;
            }

            $locations[] = new Location(
                $item['code'],
                $item['name'],
                $item['latitude'],
                $item['longitude'],
                $item['compartimenten'] ?? [],
                $grootheden,
            );
        }

        $this->locationRepository->saveAll($locations);

        return count($locations);
    }

    /**
     * Check if the location has all required grootheden.
     *
     * @param array<string> $grootheden
     */
    private function hasRequiredCapabilities(array $grootheden): bool
    {
        foreach (self::REQUIRED_GROOTHEDEN as $required) {
            if (!\in_array($required, $grootheden, true)) {
                return false;
            }
        }

        return true;
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
