<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClient;

final readonly class RefreshLocations
{
    public function __construct(
        private LocationRepositoryInterface $locationRepository,
        private RwsHttpClient $rwsClient,
    ) {
    }

    /**
     * @return int Number of locations refreshed
     */
    public function execute(): int
    {
        $data = $this->rwsClient->fetchLocations();

        if ($data === null) {
            return -1; // Error
        }

        $locations = [];
        foreach ($data as $item) {
            if (!isset($item['id'], $item['name'], $item['latitude'], $item['longitude'])) {
                continue;
            }

            $locations[] = new Location(
                (string) $item['id'],
                (string) $item['name'],
                (float) $item['latitude'],
                (float) $item['longitude'],
            );
        }

        $this->locationRepository->saveAll($locations);

        return count($locations);
    }
}
