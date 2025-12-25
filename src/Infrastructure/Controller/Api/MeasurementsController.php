<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Controller\Api;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\Service\MeasurementCodes;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class MeasurementsController extends AbstractController
{
    public function __construct(
        private readonly RwsHttpClientInterface $rwsClient,
        private readonly RwsLocationRepositoryInterface $locationRepository,
    ) {
    }

    #[Route('/measurements/{locationId}', name: 'api_measurements', methods: ['GET'])]
    public function getMeasurements(string $locationId): JsonResponse
    {
        $location = $this->locationRepository->findById($locationId);
        if (null === $location) {
            return $this->json(['error' => 'Location not found'], Response::HTTP_NOT_FOUND);
        }

        $rawData = $this->rwsClient->fetchRawMeasurements($locationId);
        if (null === $rawData) {
            return $this->json(['error' => $this->rwsClient->getLastError() ?? 'Failed to fetch measurements'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $measurements = $this->formatMeasurements($rawData);

        return $this->json([
            'location' => [
                'id' => $location->getId(),
                'name' => $location->getName(),
                'compartimenten' => $location->getCompartimenten(),
                'grootheden' => $location->getGrootheden(),
            ],
            'measurements' => $measurements,
        ]);
    }

    #[Route('/measurement-codes', name: 'api_measurement_codes', methods: ['GET'])]
    public function getCodes(): JsonResponse
    {
        return $this->json([
            'compartimenten' => MeasurementCodes::getCompartimenten(),
            'grootheden' => MeasurementCodes::getGrootheden(),
            'categories' => MeasurementCodes::getCategories(),
        ]);
    }

    private function formatMeasurements(array $rawData): array
    {
        $measurements = [];

        foreach ($rawData as $item) {
            $grootheidCode = $item['grootheid'];
            $compartimentCode = $item['compartiment'];

            $grootheidInfo = MeasurementCodes::getGrootheid($grootheidCode);
            $compartimentInfo = MeasurementCodes::getCompartiment($compartimentCode);

            $measurements[] = [
                'grootheid' => [
                    'code' => $grootheidCode,
                    'dutch' => $grootheidInfo['dutch'] ?? $grootheidCode,
                    'english' => $grootheidInfo['english'] ?? $grootheidCode,
                    'unit' => $grootheidInfo['unit'] ?? null,
                    'description' => $grootheidInfo['description'] ?? null,
                    'category' => $grootheidInfo['category'] ?? 'other',
                ],
                'compartiment' => [
                    'code' => $compartimentCode,
                    'dutch' => $compartimentInfo['dutch'] ?? $compartimentCode,
                    'english' => $compartimentInfo['english'] ?? $compartimentCode,
                ],
                'value' => $item['value'],
                'timestamp' => $item['timestamp'],
            ];
        }

        // Sort by category and then by grootheid code
        usort($measurements, function ($a, $b) {
            $categoryOrder = ['water_level', 'waves', 'temperature', 'wind', 'current', 'water_quality', 'atmospheric', 'dimensions', 'other'];
            $aOrder = array_search($a['grootheid']['category'], $categoryOrder, true);
            $bOrder = array_search($b['grootheid']['category'], $categoryOrder, true);
            if ($aOrder !== $bOrder) {
                return $aOrder <=> $bOrder;
            }

            return $a['grootheid']['code'] <=> $b['grootheid']['code'];
        });

        return $measurements;
    }
}
