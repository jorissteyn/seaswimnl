<?php

declare(strict_types=1);

namespace Seaswim\Domain\ValueObject;

final readonly class SwimmingSpot
{
    public function __construct(
        private string $id,
        private string $name,
        private float $latitude,
        private float $longitude,
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
     * Create a slug from the name for use as ID.
     */
    public static function slugify(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Create a SwimmingSpot from CSV row data.
     *
     * @param array{name: string, latitude: string, longitude: string} $row
     */
    public static function fromCsvRow(array $row): self
    {
        return new self(
            id: self::slugify($row['name']),
            name: $row['name'],
            latitude: (float) $row['latitude'],
            longitude: (float) $row['longitude'],
        );
    }
}
