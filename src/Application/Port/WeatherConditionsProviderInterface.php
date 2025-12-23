<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\ValueObject\Location;

interface WeatherConditionsProviderInterface
{
    public function getConditions(Location $location): ?WeatherConditions;

    public function getLastError(): ?string;
}
