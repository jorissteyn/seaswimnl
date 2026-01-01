<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Repository;

use Seaswim\Application\Port\SwimmingSpotRepositoryInterface;
use Seaswim\Domain\ValueObject\SwimmingSpot;

final class CsvSwimmingSpotRepository implements SwimmingSpotRepositoryInterface
{
    /** @var SwimmingSpot[]|null */
    private ?array $cache = null;

    public function __construct(
        private readonly string $csvPath,
    ) {
    }

    public function findAll(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $this->cache = $this->loadFromCsv();

        return $this->cache;
    }

    public function findById(string $id): ?SwimmingSpot
    {
        foreach ($this->findAll() as $spot) {
            if ($spot->getId() === $id) {
                return $spot;
            }
        }

        return null;
    }

    /**
     * @return SwimmingSpot[]
     */
    private function loadFromCsv(): array
    {
        if (!file_exists($this->csvPath)) {
            return [];
        }

        $handle = fopen($this->csvPath, 'r');
        if (false === $handle) {
            return [];
        }

        $spots = [];
        $headers = null;

        while (false !== ($row = fgetcsv($handle))) {
            if (null === $row) {
                continue;
            }

            if (null === $headers) {
                $headers = $row;
                continue;
            }

            if (count($row) !== count($headers)) {
                continue;
            }

            /** @var array{name: string, latitude: string, longitude: string} $data */
            $data = array_combine($headers, $row);
            $spots[] = SwimmingSpot::fromCsvRow($data);
        }

        fclose($handle);

        return $spots;
    }
}
