<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\ValueObject\Location;

interface WaterConditionsProviderInterface
{
    public function getConditions(Location $location): ?WaterConditions;

    public function getLastError(): ?string;
}
