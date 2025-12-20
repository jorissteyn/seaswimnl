<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Seaswim\Infrastructure\ApiPlatform\Dto\ConditionsOutput;
use Seaswim\Infrastructure\ApiPlatform\State\ConditionsProvider;

#[ApiResource(
    shortName: 'Conditions',
    operations: [
        new Get(
            uriTemplate: '/v1/conditions/{location}',
            provider: ConditionsProvider::class,
        ),
    ],
    output: ConditionsOutput::class,
    routePrefix: '/api',
)]
final class ConditionsResource
{
}
