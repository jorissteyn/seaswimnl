<?php

declare(strict_types=1);

namespace Seaswim\Application\UseCase;

use Psr\Cache\CacheItemPoolInterface;

final readonly class ClearCache
{
    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function execute(): bool
    {
        return $this->cache->clear();
    }
}
