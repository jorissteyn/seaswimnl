<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\ValueObject\RwsLocation;

/**
 * @codeCoverageIgnore
 */
interface WaterConditionsProviderInterface
{
    public function getConditions(RwsLocation $location): ?WaterConditions;

    public function getLastError(): ?string;
}
