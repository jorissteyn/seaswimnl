<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\Location;

final class LocationTest extends TestCase
{
    public function testConstruction(): void
    {
        $location = new Location(
            'vlissingen',
            'Vlissingen',
            51.4424,
            3.5968,
        );

        $this->assertSame('vlissingen', $location->getId());
        $this->assertSame('Vlissingen', $location->getName());
        $this->assertSame(51.4424, $location->getLatitude());
        $this->assertSame(3.5968, $location->getLongitude());
    }
}
