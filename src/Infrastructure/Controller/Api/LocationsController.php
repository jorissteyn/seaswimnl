<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Controller\Api;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class LocationsController extends AbstractController
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
    ) {
    }

    #[Route('/locations', name: 'api_locations', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $locations = $this->locationRepository->findAll();

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
