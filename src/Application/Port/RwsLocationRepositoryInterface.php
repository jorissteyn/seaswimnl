<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\ValueObject\RwsLocation;

interface RwsLocationRepositoryInterface
{
    /**
     * @return RwsLocation[]
     */
    public function findAll(): array;

    public function findById(string $id): ?RwsLocation;

    /**
     * @param RwsLocation[] $locations
     */
    public function saveAll(array $locations): void;
}
