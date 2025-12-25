<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Infrastructure\ExternalApi\Client\RwsHttpClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:locations:scan-stale',
    description: 'Scan all RWS locations for stale data and update the blacklist',
)]
final class LocationsScanStaleCommand extends Command
{
    public function __construct(
        private readonly RwsLocationRepositoryInterface $locationRepository,
        private readonly RwsHttpClientInterface $rwsClient,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be blacklisted without writing to file')
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Delay between API calls in milliseconds', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $delay = (int) $input->getOption('delay');

        $locations = $this->locationRepository->findAll();
        $total = count($locations);

        if (0 === $total) {
            $io->warning('No locations found. Run seaswim:locations:refresh first.');

            return Command::SUCCESS;
        }

        $blacklistFile = $this->projectDir.'/blacklist.txt';
        $existingBlacklist = $this->loadExistingBlacklist($blacklistFile);
        $existingBlacklistSet = array_flip($existingBlacklist);

        // Filter out already blacklisted locations
        $locationsToScan = array_filter(
            $locations,
            fn ($loc) => !isset($existingBlacklistSet[$loc->getId()])
        );
        $toScan = count($locationsToScan);
        $skipped = $total - $toScan;

        $io->title('Scanning RWS locations for stale data');
        $io->text(sprintf('Total locations: %d, already blacklisted: %d, to scan: %d', $total, $skipped, $toScan));

        if (0 === $toScan) {
            $io->success('All locations are already blacklisted. Nothing to scan.');

            return Command::SUCCESS;
        }

        $staleLocations = [];
        $freshLocations = [];
        $noDataLocations = [];
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $progressBar = new ProgressBar($output, $toScan);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        foreach ($locationsToScan as $location) {
            $progressBar->setMessage($location->getId());
            $progressBar->advance();

            $data = $this->rwsClient->fetchWaterData($location->getId());

            if (null === $data) {
                $noDataLocations[] = $location->getId();
                usleep($delay * 1000);
                continue;
            }

            $timestamp = $data['timestamp'] ?? null;

            if (null === $timestamp) {
                $noDataLocations[] = $location->getId();
                usleep($delay * 1000);
                continue;
            }

            try {
                $date = new \DateTimeImmutable($timestamp);
                $dateStr = $date->format('Y-m-d');

                if ($dateStr !== $today) {
                    $staleLocations[$location->getId()] = $dateStr;
                } else {
                    $freshLocations[] = $location->getId();
                }
            } catch (\Exception) {
                $noDataLocations[] = $location->getId();
            }

            usleep($delay * 1000);
        }

        $progressBar->setMessage('Done');
        $progressBar->finish();
        $io->newLine(2);

        // Summary
        $io->section('Results');
        $io->text(sprintf('<info>Fresh data (today):</info> %d locations', count($freshLocations)));
        $io->text(sprintf('<comment>Stale data:</comment> %d locations', count($staleLocations)));
        $io->text(sprintf('<comment>No data:</comment> %d locations', count($noDataLocations)));

        if ([] !== $staleLocations) {
            $io->newLine();
            $io->section('Stale locations (most recent data)');

            // Sort by date ascending (oldest first)
            asort($staleLocations);

            $rows = [];
            foreach ($staleLocations as $id => $date) {
                $rows[] = [$id, $date];
            }
            $io->table(['Location ID', 'Last Data'], $rows);
        }

        // Merge with existing blacklist and write
        $newBlacklist = array_unique(array_merge(
            $existingBlacklist,
            array_keys($staleLocations),
            $noDataLocations
        ));
        sort($newBlacklist);

        $added = array_diff($newBlacklist, $existingBlacklist);

        if ([] === $added) {
            $io->success('No new locations to blacklist.');

            return Command::SUCCESS;
        }

        $io->newLine();
        $io->text(sprintf('Adding %d new locations to blacklist:', count($added)));
        foreach ($added as $id) {
            $io->text(sprintf('  - %s', $id));
        }

        if ($dryRun) {
            $io->warning('Dry run - blacklist not updated.');

            return Command::SUCCESS;
        }

        $this->writeBlacklist($blacklistFile, $newBlacklist);
        $io->success(sprintf('Blacklist updated with %d total locations.', count($newBlacklist)));

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function loadExistingBlacklist(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (false === $lines) {
            return [];
        }

        $blacklist = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' !== $line && !str_starts_with($line, '#')) {
                $blacklist[] = $line;
            }
        }

        return $blacklist;
    }

    /**
     * @param string[] $locations
     */
    private function writeBlacklist(string $file, array $locations): void
    {
        $content = "# Blacklisted RWS locations (stale or no data)\n";
        $content .= '# Generated by seaswim:locations:scan-stale on '.(new \DateTimeImmutable())->format('Y-m-d H:i:s')."\n";
        $content .= "#\n";
        $content .= "# These locations return outdated data from the RWS API.\n";
        $content .= "# They are excluded from the location selector.\n";
        $content .= "\n";
        $content .= implode("\n", $locations)."\n";

        file_put_contents($file, $content);
    }
}
