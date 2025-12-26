<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\ValueObject\WeatherStation;

interface WeatherStationRepositoryInterface
{
    /**
     * @return WeatherStation[]
     */
    public function findAll(): array;

    public function findByCode(string $code): ?WeatherStation;

    /**
     * @param WeatherStation[] $stations
     */
    public function saveAll(array $stations): void;
}
