<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi;

use Psr\Cache\CacheItemPoolInterface;
use Seaswim\Application\Port\TidalInfoProviderInterface;
use Seaswim\Domain\Service\TideCalculator;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\TideInfo;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final readonly class RijkswaterstaatTidalAdapter implements TidalInfoProviderInterface
{
    public function __construct(
        private RwsHttpClientInterface $client,
        private TideCalculator $tideCalculator,
        private CacheItemPoolInterface $cache,
        private int $cacheTtl,
    ) {
    }

    public function getTidalInfo(Location $location): ?TideInfo
    {
        $cacheKey = 'rws_tides_'.$location->getId();
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $now = new \DateTimeImmutable();
        $start = $now->modify('-12 hours');
        $end = $now->modify('+12 hours');

        $predictions = $this->client->fetchTidalPredictions($location->getId(), $start, $end);

        if (null === $predictions || [] === $predictions) {
            return null;
        }

        $tideInfo = $this->tideCalculator->calculateTides($predictions, $now);

        $cacheItem->set($tideInfo);
        $cacheItem->expiresAfter($this->cacheTtl);
        $this->cache->save($cacheItem);

        return $tideInfo;
    }
}
