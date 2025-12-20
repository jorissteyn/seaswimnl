<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Seaswim\Infrastructure\ApiPlatform\Dto\LocationOutput;
use Seaswim\Infrastructure\ApiPlatform\State\LocationProvider;

#[ApiResource(
    shortName: 'Location',
    operations: [
        new GetCollection(
            uriTemplate: '/v1/locations',
            provider: LocationProvider::class,
        ),
        new Get(
            uriTemplate: '/v1/locations/{id}',
            provider: LocationProvider::class,
        ),
    ],
    output: LocationOutput::class,
    routePrefix: '/api',
)]
final class LocationResource
{
}
