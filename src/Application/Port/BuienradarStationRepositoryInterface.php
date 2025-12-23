<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\ValueObject\BuienradarStation;

interface BuienradarStationRepositoryInterface
{
    /**
     * @return BuienradarStation[]
     */
    public function findAll(): array;

    public function findByCode(string $code): ?BuienradarStation;

    /**
     * @param BuienradarStation[] $stations
     */
    public function saveAll(array $stations): void;
}
