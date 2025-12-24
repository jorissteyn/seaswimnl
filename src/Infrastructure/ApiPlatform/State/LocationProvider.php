<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Infrastructure\ApiPlatform\Dto\LocationOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<LocationOutput>
 */
final readonly class LocationProvider implements ProviderInterface
{
    public function __construct(
        private RwsLocationRepositoryInterface $locationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $location = $this->locationRepository->findById($uriVariables['id']);

            if (null === $location) {
                throw new NotFoundHttpException('Location not found');
            }

            return $this->toOutput($location);
        }

        return array_map(
            fn (Location $location) => $this->toOutput($location),
            $this->locationRepository->findAll(),
        );
    }

    private function toOutput(Location $location): LocationOutput
    {
        return new LocationOutput(
            id: $location->getId(),
            name: $location->getName(),
            latitude: $location->getLatitude(),
            longitude: $location->getLongitude(),
        );
    }
}
