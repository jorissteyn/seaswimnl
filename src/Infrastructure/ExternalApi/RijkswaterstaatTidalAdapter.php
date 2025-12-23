<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi;

use Psr\Cache\CacheItemPoolInterface;
use Seaswim\Application\Port\TidalInfoProviderInterface;
use Seaswim\Domain\Service\TideCalculator;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\TideInfo;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final class RijkswaterstaatTidalAdapter implements TidalInfoProviderInterface
{
    private ?string $lastError = null;

    public function __construct(
        private readonly RwsHttpClientInterface $client,
        private readonly TideCalculator $tideCalculator,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $cacheTtl,
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getTidalInfo(Location $location): ?TideInfo
    {
        $this->lastError = null;
        $cacheKey = 'rws_tides_'.$location->getId();
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $now = new \DateTimeImmutable();
        $start = $now->modify('-12 hours');
        $end = $now->modify('+12 hours');

        $predictions = $this->client->fetchTidalPredictions($location->getId(), $start, $end);

        if (null === $predictions) {
            $this->lastError = $this->client->getLastError();

            return null;
        }

        if ([] === $predictions) {
            $this->lastError = 'No tidal data available for this location';

            return null;
        }

        $tideInfo = $this->tideCalculator->calculateTides($predictions, $now);

        $cacheItem->set($tideInfo);
        $cacheItem->expiresAfter($this->cacheTtl);
        $this->cache->save($cacheItem);

        return $tideInfo;
    }
}
