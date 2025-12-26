<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\RwsLocation;

final class RwsLocationTest extends TestCase
{
    public function testConstruction(): void
    {
        $location = new RwsLocation(
            'vlissingen',
            'Vlissingen',
            51.4424,
            3.5968,
        );

        $this->assertSame('vlissingen', $location->getId());
        $this->assertSame('Vlissingen', $location->getName());
        $this->assertSame(51.4424, $location->getLatitude());
        $this->assertSame(3.5968, $location->getLongitude());
        $this->assertSame([], $location->getCompartimenten());
        $this->assertSame([], $location->getGrootheden());
    }

    public function testConstructionWithCompartimentenAndGrootheden(): void
    {
        $location = new RwsLocation(
            'europlatform',
            'Europlatform',
            52.0000,
            3.2761,
            ['OW'],
            ['T', 'WATHTE', 'Hm0'],
        );

        $this->assertSame('europlatform', $location->getId());
        $this->assertSame('Europlatform', $location->getName());
        $this->assertSame(52.0000, $location->getLatitude());
        $this->assertSame(3.2761, $location->getLongitude());
        $this->assertSame(['OW'], $location->getCompartimenten());
        $this->assertSame(['T', 'WATHTE', 'Hm0'], $location->getGrootheden());
    }
}
