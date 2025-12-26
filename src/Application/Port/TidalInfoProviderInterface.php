<?php

declare(strict_types=1);

namespace Seaswim\Application\Port;

use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\TideInfo;

interface TidalInfoProviderInterface
{
    public function getTidalInfo(RwsLocation $location): ?TideInfo;

    public function getLastError(): ?string;
}
