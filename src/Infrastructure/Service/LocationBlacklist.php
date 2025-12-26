<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Service;

/**
 * Reads location IDs to blacklist from a text file.
 *
 * Some RWS locations have outdated data (months or years old). Since we reject
 * data that is not from today, these locations would always fail. Rather than
 * making API calls to check each location's data freshness (too expensive),
 * we maintain a blacklist to skip them from the location selector entirely.
 */
final class LocationBlacklist
{
    /** @var array<string, true> */
    private array $blacklisted = [];

    public function __construct(string $projectDir)
    {
        $file = $projectDir.'/data/blacklist.txt';
        if (!file_exists($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (false === $lines) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }
            $this->blacklisted[$line] = true;
        }
    }

    public function isBlacklisted(string $locationId): bool
    {
        return isset($this->blacklisted[$locationId]);
    }

    /**
     * @return string[]
     */
    public function getAll(): array
    {
        return array_keys($this->blacklisted);
    }
}
