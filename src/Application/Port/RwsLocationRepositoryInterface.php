<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\ValueObject\Location;

interface RwsLocationRepositoryInterface
{
    /**
     * @return Location[]
     */
    public function findAll(): array;

    public function findById(string $id): ?Location;

    /**
     * @param Location[] $locations
     */
    public function saveAll(array $locations): void;
}
