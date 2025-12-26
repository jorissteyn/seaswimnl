<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Repository;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;

final class JsonFileRwsLocationRepository implements RwsLocationRepositoryInterface
{
    private string $filePath;

    public function __construct(string $projectDir)
    {
        $this->filePath = $projectDir.'/var/data/rws-locations.json';
    }

    public function findAll(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        if (false === $content) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        return array_map(
            fn (array $item) => new RwsLocation(
                $item['id'],
                $item['name'],
                (float) $item['latitude'],
                (float) $item['longitude'],
                $item['compartimenten'] ?? [],
                $item['grootheden'] ?? [],
            ),
            $data,
        );
    }

    public function findById(string $id): ?RwsLocation
    {
        foreach ($this->findAll() as $location) {
            if ($location->getId() === $id) {
                return $location;
            }
        }

        return null;
    }

    public function saveAll(array $locations): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = array_map(
            fn (RwsLocation $location) => [
                'id' => $location->getId(),
                'name' => $location->getName(),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'compartimenten' => $location->getCompartimenten(),
                'grootheden' => $location->getGrootheden(),
            ],
            $locations,
        );

        file_put_contents(
            $this->filePath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
