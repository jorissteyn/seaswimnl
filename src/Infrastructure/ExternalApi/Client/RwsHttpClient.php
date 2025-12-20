<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ExternalApi\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RwsHttpClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
        private int $timeout,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchWaterData(string $locationCode): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/waterdata', [
                'query' => ['location' => $locationCode],
                'timeout' => $this->timeout,
            ]);

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('RWS API request failed', [
                'location' => $locationCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchLocations(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/locations', [
                'timeout' => $this->timeout,
            ]);

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('RWS API locations request failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
