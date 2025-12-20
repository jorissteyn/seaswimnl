<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Application\UseCase;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Seaswim\Application\UseCase\ClearCache;

final class ClearCacheTest extends TestCase
{
    public function testExecuteClearsCache(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $useCase = new ClearCache($cache);
        $result = $useCase->execute();

        $this->assertTrue($result);
    }

    public function testExecuteReturnsFalseWhenCacheEmpty(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())
            ->method('clear')
            ->willReturn(false);

        $useCase = new ClearCache($cache);
        $result = $useCase->execute();

        $this->assertFalse($result);
    }
}
