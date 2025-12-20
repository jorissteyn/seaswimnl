<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class KnmiHttpClient implements KnmiHttpClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
        private string $apiKey,
        private int $timeout,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchWeatherData(float $latitude, float $longitude): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/weather', [
                'query' => [
                    'lat' => $latitude,
                    'lon' => $longitude,
                ],
                'headers' => [
                    'Authorization' => $this->apiKey,
                ],
                'timeout' => $this->timeout,
            ]);

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('KNMI API request failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
