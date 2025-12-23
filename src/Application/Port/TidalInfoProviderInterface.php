<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\TideInfo;

interface TidalInfoProviderInterface
{
    public function getTidalInfo(Location $location): ?TideInfo;

    public function getLastError(): ?string;
}
