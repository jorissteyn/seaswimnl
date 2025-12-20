<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi;

use Psr\Cache\CacheItemPoolInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\UVIndex;
use Seaswim\Domain\ValueObject\WindSpeed;
use Seaswim\Infrastructure\ExternalApi\Client\KnmiHttpClient;

final readonly class KnmiAdapter implements WeatherConditionsProviderInterface
{
    public function __construct(
        private KnmiHttpClient $client,
        private CacheItemPoolInterface $cache,
        private int $cacheTtl,
    ) {
    }

    public function getConditions(Location $location): ?WeatherConditions
    {
        $cacheKey = 'knmi_weather_'.$location->getId();
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $data = $this->client->fetchWeatherData(
            $location->getLatitude(),
            $location->getLongitude(),
        );

        if (null === $data) {
            // Return stale cache if available
            $staleItem = $this->cache->getItem($cacheKey.'_stale');
            if ($staleItem->isHit()) {
                return $staleItem->get();
            }

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
     * @param array<string, mixed> $data
     */
    private function mapToEntity(Location $location, array $data): WeatherConditions
    {
        return new WeatherConditions(
            location: $location,
            airTemperature: Temperature::fromCelsius($data['temperature'] ?? null),
            windSpeed: WindSpeed::fromMetersPerSecond($data['windSpeed'] ?? null),
            windDirection: $data['windDirection'] ?? null,
            uvIndex: UVIndex::fromValue(isset($data['uvIndex']) ? (int) $data['uvIndex'] : null),
            measuredAt: new \DateTimeImmutable($data['timestamp'] ?? 'now'),
        );
    }
}
