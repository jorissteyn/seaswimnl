<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Seaswim\Infrastructure\ApiPlatform\Dto\SwimmingSpotOutput;
use Seaswim\Infrastructure\ApiPlatform\State\SwimmingSpotProvider;

#[ApiResource(
    shortName: 'SwimmingSpot',
    operations: [
        new GetCollection(
            uriTemplate: '/v1/swimming-spots',
            provider: SwimmingSpotProvider::class,
        ),
        new Get(
            uriTemplate: '/v1/swimming-spots/{id}',
            provider: SwimmingSpotProvider::class,
        ),
    ],
    output: SwimmingSpotOutput::class,
    routePrefix: '/api',
)]
final class SwimmingSpotResource
{
}
