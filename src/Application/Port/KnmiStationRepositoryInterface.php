<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\ValueObject\KnmiStation;

interface KnmiStationRepositoryInterface
{
    /**
     * @return KnmiStation[]
     */
    public function findAll(): array;

    public function findByCode(string $code): ?KnmiStation;

    /**
     * @param KnmiStation[] $stations
     */
    public function saveAll(array $stations): void;
}
