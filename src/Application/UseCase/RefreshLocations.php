<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final readonly class RefreshLocations
{
    public function __construct(
        private LocationRepositoryInterface $locationRepository,
        private RwsHttpClientInterface $rwsClient,
    ) {
    }

    /**
     * @return int Number of locations refreshed
     */
    public function execute(): int
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
}
