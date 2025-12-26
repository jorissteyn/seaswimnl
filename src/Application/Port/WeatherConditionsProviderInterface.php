<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\ValueObject\RwsLocation;

interface WeatherConditionsProviderInterface
{
    public function getConditions(RwsLocation $location): ?WeatherConditions;

    public function getLastError(): ?string;
}
