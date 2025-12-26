<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Controller\Api;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Seaswim\Domain\Service\MeasurementCodes;
use Seaswim\Domain\Service\NearestRwsLocationFinder;
use Seaswim\Domain\Service\NearestRwsLocationMatcher;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class MeasurementsController extends AbstractController
{
    private const WAVE_CAPABILITIES = ['Hm0', 'Tm02', 'Th3'];

    public function __construct(
        private readonly RwsHttpClientInterface $rwsClient,
        private readonly RwsLocationRepositoryInterface $locationRepository,
        private readonly SwimmingSpotRepositoryInterface $swimmingSpotRepository,
        private readonly NearestRwsLocationMatcher $rwsLocationMatcher,
        private readonly NearestRwsLocationFinder $rwsLocationFinder,
    ) {
    }

    #[Route('/measurements/{swimmingSpotId}', name: 'api_measurements', methods: ['GET'])]
    public function getMeasurements(string $swimmingSpotId): JsonResponse
    {
        $swimmingSpot = $this->swimmingSpotRepository->findById($swimmingSpotId);
        if (null === $swimmingSpot) {
            return $this->json(['error' => 'Swimming spot not found'], Response::HTTP_NOT_FOUND);
        }

        // Find primary RWS location
        $rwsResult = $this->rwsLocationMatcher->findNearestLocation($swimmingSpot);
        if (null === $rwsResult) {
            return $this->json(['error' => 'No RWS location found near this swimming spot'], Response::HTTP_NOT_FOUND);
        }

        $primaryLocation = $rwsResult['location'];
        $allLocations = $this->locationRepository->findAll();
        $measurements = [];

        // Fetch measurements from primary location
        $primaryData = $this->rwsClient->fetchRawMeasurements($primaryLocation->getId());
        if (null !== $primaryData) {
            $locationInfo = [
                'id' => $primaryLocation->getId(),
                'name' => $primaryLocation->getName(),
                'distanceKm' => $rwsResult['distanceKm'],
            ];
            $measurements = array_merge($measurements, $this->formatMeasurements($primaryData, $locationInfo));
        }

        // Check which wave capabilities the primary location is missing
        $primaryGrootheden = $primaryLocation->getGrootheden();
        $missingWaveCapabilities = array_filter(
            self::WAVE_CAPABILITIES,
            fn (string $cap) => !\in_array($cap, $primaryGrootheden, true)
        );

        // Fetch wave measurements from fallback stations
        foreach ($missingWaveCapabilities as $capability) {
            $fallbackResult = $this->rwsLocationFinder->findNearest($primaryLocation, $allLocations, $capability);
            if (null === $fallbackResult) {
                continue;
            }

            $fallbackLocation = $fallbackResult['location'];
            $fallbackData = $this->rwsClient->fetchRawMeasurements($fallbackLocation->getId());
            if (null === $fallbackData) {
                continue;
            }

            // Only include the specific wave measurement we're looking for
            $filteredData = array_filter(
                $fallbackData,
                fn (array $item) => $item['grootheid'] === $capability
            );

            if ([] !== $filteredData) {
                $locationInfo = [
                    'id' => $fallbackLocation->getId(),
                    'name' => $fallbackLocation->getName(),
                    'distanceKm' => $fallbackResult['distanceKm'],
                ];
                $measurements = array_merge($measurements, $this->formatMeasurements($filteredData, $locationInfo));
            }
        }

        // Sort all measurements
        $measurements = $this->sortMeasurements($measurements);

        return $this->json([
            'swimmingSpot' => [
                'id' => $swimmingSpot->getId(),
                'name' => $swimmingSpot->getName(),
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

    private function formatMeasurements(array $rawData, array $locationInfo): array
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
                'location' => $locationInfo,
            ];
        }

        return $measurements;
    }

    private function sortMeasurements(array $measurements): array
    {
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
