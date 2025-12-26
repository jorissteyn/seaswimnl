<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Seaswim\Domain\ValueObject\SwimmingSpot;
use Seaswim\Infrastructure\ApiPlatform\Dto\SwimmingSpotOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<SwimmingSpotOutput>
 */
final readonly class SwimmingSpotProvider implements ProviderInterface
{
    public function __construct(
        private SwimmingSpotRepositoryInterface $swimmingSpotRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $spot = $this->swimmingSpotRepository->findById($uriVariables['id']);

            if (null === $spot) {
                throw new NotFoundHttpException('Swimming spot not found');
            }

            return $this->toOutput($spot);
        }

        return array_map(
            fn (SwimmingSpot $spot) => $this->toOutput($spot),
            $this->swimmingSpotRepository->findAll(),
        );
    }

    private function toOutput(SwimmingSpot $spot): SwimmingSpotOutput
    {
        return new SwimmingSpotOutput(
            id: $spot->getId(),
            name: $spot->getName(),
            latitude: $spot->getLatitude(),
            longitude: $spot->getLongitude(),
        );
    }
}
