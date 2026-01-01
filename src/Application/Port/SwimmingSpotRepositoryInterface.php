<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\ValueObject\SwimmingSpot;

/**
 * @codeCoverageIgnore
 */
interface SwimmingSpotRepositoryInterface
{
    /**
     * @return SwimmingSpot[]
     */
    public function findAll(): array;

    public function findById(string $id): ?SwimmingSpot;
}
