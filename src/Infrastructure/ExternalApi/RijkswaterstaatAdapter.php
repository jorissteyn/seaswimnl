<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi;

use Psr\Cache\CacheItemPoolInterface;
use Seaswim\Application\Port\WaterConditionsProviderInterface;
use Seaswim\Domain\Entity\WaterConditions;
use Seaswim\Domain\ValueObject\RwsLocation;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\WaterHeight;
use Seaswim\Domain\ValueObject\WaveDirection;
use Seaswim\Domain\ValueObject\WaveHeight;
use Seaswim\Domain\ValueObject\WavePeriod;
use Seaswim\Domain\ValueObject\WindSpeed;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;

final class RijkswaterstaatAdapter implements WaterConditionsProviderInterface
{
    private ?string $lastError = null;

    public function __construct(
        private readonly RwsHttpClientInterface $client,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $cacheTtl,
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getConditions(RwsLocation $location): ?WaterConditions
    {
        $this->lastError = null;
        $cacheKey = 'rws_water_'.$location->getId();
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $data = $this->client->fetchWaterData($location->getId());

        if (null === $data) {
            $this->lastError = $this->client->getLastError();

            // Return stale cache if available
            $staleItem = $this->cache->getItem($cacheKey.'_stale');
            if ($staleItem->isHit()) {
                $this->lastError = null; // Clear error since we have stale data

                return $staleItem->get();
            }

            return null;
        }

        // Reject data that is too old (RWS can return very old data for inactive stations)
        if (!$this->isRecent($data['timestamp'] ?? null)) {
            $this->lastError = 'RWS data is outdated (older than 12 hours)';

            return null;
        }

        $conditions = $this->mapToEntity($location, $data);

        $cacheItem->set($conditions);
        $cacheItem->expiresAfter($this->cacheTtl);
        $this->cache->save($cacheItem);

        // Also save as stale backup
        $staleItem = $this->cache->getItem($cacheKey.'_stale');
        $staleItem->set($conditions);
        $staleItem->expiresAfter($this->cacheTtl * 4);
        $this->cache->save($staleItem);

        return $conditions;
    }

    /**
     * Check if the timestamp is recent (within the last 12 hours).
     *
     * Using a 12-hour window instead of "today" because:
     * - Data from 23:59 yesterday is still valid at 00:01 today
     * - Fallback wave stations may update less frequently
     * - Wave conditions don't change drastically hour by hour
     */
    private function isRecent(?string $timestamp): bool
    {
        if (null === $timestamp) {
            return false;
        }

        try {
            $date = new \DateTimeImmutable($timestamp);
            $cutoff = new \DateTimeImmutable('-12 hours');

            return $date >= $cutoff;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapToEntity(RwsLocation $location, array $data): WaterConditions
    {
        return new WaterConditions(
            location: $location,
            temperature: Temperature::fromCelsius($data['waterTemperature'] ?? null),
            waveHeight: WaveHeight::fromMeters($data['waveHeight'] ?? null),
            waterHeight: WaterHeight::fromMeters($data['waterHeight'] ?? null),
            measuredAt: new \DateTimeImmutable($data['timestamp'] ?? 'now'),
            windSpeed: isset($data['windSpeed']) ? WindSpeed::fromMetersPerSecond($data['windSpeed']) : null,
            windDirection: $data['windDirection'] ?? null,
            wavePeriod: isset($data['wavePeriod']) ? WavePeriod::fromSeconds($data['wavePeriod']) : null,
            waveDirection: isset($data['waveDirection']) ? WaveDirection::fromDegrees($data['waveDirection']) : null,
            rawMeasurements: $data['raw'] ?? null,
        );
    }
}
