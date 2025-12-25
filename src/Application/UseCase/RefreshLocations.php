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
     * @return array{locations: array{imported: int, filtered: int, total: int}|int, stations: int, requiredGrootheden: array<string>}
     */
    public function execute(): array
    {
        $locationsResult = $this->refreshRwsLocations();
        $stationsCount = $this->refreshBuienradarStations();

        return [
            'locations' => $locationsResult,
            'stations' => $stationsCount,
            'requiredGrootheden' => self::REQUIRED_GROOTHEDEN,
        ];
    }

    /**
     * Required grootheden for a location to be included in the dashboard.
     */
    private const REQUIRED_GROOTHEDEN = [
        'WINDSHD',  // Wind speed
        'WINDRTG',  // Wind direction
    ];

    /**
     * @return array{imported: int, total: int, filterSteps: array<string, int>}|int Returns -1 on error
     */
    private function refreshRwsLocations(): array|int
    {
        $data = $this->rwsClient->fetchLocations();

        if (null === $data) {
            return -1; // Error
        }

        $total = count($data);

        // Track remaining locations after each filter step
        $filterSteps = [];
        $remaining = $data;

        foreach (self::REQUIRED_GROOTHEDEN as $required) {
            $remaining = array_filter($remaining, function (array $item) use ($required): bool {
                $grootheden = $item['grootheden'] ?? [];

                return \in_array($required, $grootheden, true);
            });
            $filterSteps[$required] = count($remaining);
        }

        // Build final location list from the filtered data
        $locations = [];
        foreach ($remaining as $item) {
            $locations[] = new Location(
                $item['code'],
                $item['name'],
                $item['latitude'],
                $item['longitude'],
                $item['compartimenten'] ?? [],
                $item['grootheden'] ?? [],
            );
        }

        $this->locationRepository->saveAll($locations);

        return [
            'imported' => count($locations),
            'total' => $total,
            'filterSteps' => $filterSteps,
        ];
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
