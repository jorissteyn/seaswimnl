<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Repository;

use Seaswim\Application\Port\KnmiStationRepositoryInterface;
use Seaswim\Domain\ValueObject\KnmiStation;

final class JsonFileKnmiStationRepository implements KnmiStationRepositoryInterface
{
    private string $filePath;

    public function __construct(string $projectDir)
    {
        $this->filePath = $projectDir.'/var/data/knmi-stations.json';
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
            fn (array $item) => new KnmiStation(
                $item['code'],
                $item['name'],
                (float) $item['latitude'],
                (float) $item['longitude'],
            ),
            $data,
        );
    }

    public function findByCode(string $code): ?KnmiStation
    {
        foreach ($this->findAll() as $station) {
            if ($station->getCode() === $code) {
                return $station;
            }
        }

        return null;
    }

    public function saveAll(array $stations): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = array_map(
            fn (KnmiStation $station) => [
                'code' => $station->getCode(),
                'name' => $station->getName(),
                'latitude' => $station->getLatitude(),
                'longitude' => $station->getLongitude(),
            ],
            $stations,
        );

        file_put_contents(
            $this->filePath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
