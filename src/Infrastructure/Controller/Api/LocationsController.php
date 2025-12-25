<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Controller\Api;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Infrastructure\Service\LocationBlacklist;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class LocationsController extends AbstractController
{
    public function __construct(
        private readonly RwsLocationRepositoryInterface $locationRepository,
        private readonly LocationBlacklist $blacklist,
    ) {
    }

    #[Route('/locations', name: 'api_locations', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $locations = array_values(array_filter(
            $this->locationRepository->findAll(),
            fn ($location) => !$this->blacklist->isBlacklisted($location->getId()),
        ));

        return $this->json(array_map(
            fn ($location) => [
                'id' => $location->getId(),
                'name' => $location->getName(),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
            ],
            $locations,
        ));
    }
}
