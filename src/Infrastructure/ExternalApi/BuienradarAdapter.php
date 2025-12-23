<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi;

use Psr\Cache\CacheItemPoolInterface;
use Seaswim\Application\Port\WeatherConditionsProviderInterface;
use Seaswim\Domain\Entity\WeatherConditions;
use Seaswim\Domain\Service\BuienradarStationMatcher;
use Seaswim\Domain\ValueObject\Location;
use Seaswim\Domain\ValueObject\Temperature;
use Seaswim\Domain\ValueObject\UVIndex;
use Seaswim\Domain\ValueObject\WindSpeed;
use Seaswim\Infrastructure\ExternalApi\Client\BuienradarHttpClientInterface;

final readonly class BuienradarAdapter implements WeatherConditionsProviderInterface
{
    public function __construct(
        private BuienradarHttpClientInterface $client,
        private BuienradarStationMatcher $stationMatcher,
        private CacheItemPoolInterface $cache,
        private int $cacheTtl,
    ) {
    }

    public function getConditions(Location $location): ?WeatherConditions
    {
        // Find matching Buienradar station for this RWS location
        $station = $this->stationMatcher->findMatchingStation($location->getName());

        if (null === $station) {
            return null;
        }

        // Cache by station code to avoid duplicate fetches
        $cacheKey = 'buienradar_weather_'.$station->getCode();
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $cachedConditions = $cacheItem->get();

            // Return cached conditions but with the requested location
            return $this->cloneWithLocation($cachedConditions, $location);
        }

        $data = $this->client->fetchWeatherData($station->getCode());

        if (null === $data) {
            // Return stale cache if available
            $staleItem = $this->cache->getItem($cacheKey.'_stale');
            if ($staleItem->isHit()) {
                $staleConditions = $staleItem->get();

                return $this->cloneWithLocation($staleConditions, $location);
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
            uvIndex: UVIndex::fromValue(null), // UV not available in Buienradar feed
            measuredAt: new \DateTimeImmutable($data['timestamp'] ?? 'now'),
        );
    }

    private function cloneWithLocation(WeatherConditions $conditions, Location $location): WeatherConditions
    {
        return new WeatherConditions(
            location: $location,
            airTemperature: $conditions->getAirTemperature(),
            windSpeed: $conditions->getWindSpeed(),
            windDirection: $conditions->getWindDirection(),
            uvIndex: $conditions->getUvIndex(),
            measuredAt: $conditions->getMeasuredAt(),
        );
    }
}
