<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Controller\Api;

use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class SwimmingSpotsController extends AbstractController
{
    public function __construct(
        private readonly SwimmingSpotRepositoryInterface $swimmingSpotRepository,
    ) {
    }

    #[Route('/swimming-spots', name: 'api_swimming_spots', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $spots = $this->swimmingSpotRepository->findAll();

        return $this->json(array_map(
            fn ($spot) => [
                'id' => $spot->getId(),
                'name' => $spot->getName(),
                'latitude' => $spot->getLatitude(),
                'longitude' => $spot->getLongitude(),
            ],
            $spots,
        ));
    }
}
