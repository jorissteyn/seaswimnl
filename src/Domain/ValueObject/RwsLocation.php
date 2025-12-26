<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class RwsLocation
{
    /**
     * @param array<string> $compartimenten RWS compartment codes (e.g., 'OW' for surface water)
     * @param array<string> $grootheden     RWS measurement type codes (e.g., 'T', 'WATHTE', 'Hm0')
     */
    public function __construct(
        private string $id,
        private string $name,
        private float $latitude,
        private float $longitude,
        private array $compartimenten = [],
        private array $grootheden = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * @return array<string>
     */
    public function getCompartimenten(): array
    {
        return $this->compartimenten;
    }

    /**
     * @return array<string>
     */
    public function getGrootheden(): array
    {
        return $this->grootheden;
    }
}
