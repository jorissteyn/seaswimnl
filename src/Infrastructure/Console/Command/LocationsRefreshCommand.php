<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\UseCase\RefreshLocations;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:locations:refresh',
    description: 'Refresh swim locations from Rijkswaterstaat and weather stations from Buienradar',
)]
final class LocationsRefreshCommand extends Command
{
    public function __construct(
        private readonly RefreshLocations $refreshLocations,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Refreshing locations from Rijkswaterstaat and Buienradar...');

        $result = $this->refreshLocations->execute();

        $hasError = false;

        if (-1 === $result['locations']) {
            $io->error('Failed to refresh RWS locations. API may be unavailable.');
            $hasError = true;
        } else {
            /** @var array{imported: int, total: int, filterSteps: array<string, int>} $locations */
            $locations = $result['locations'];

            $io->text(sprintf('Total locations from RWS API: %d', $locations['total']));
            $io->newLine();

            $io->text('Filtering by required grootheden:');
            $previous = $locations['total'];
            foreach ($locations['filterSteps'] as $code => $remaining) {
                $filtered = $previous - $remaining;
                $io->text(sprintf('  %s: %d remaining (-%d)', $code, $remaining, $filtered));
                $previous = $remaining;
            }
            $io->newLine();

            $io->success(sprintf('Imported %d RWS locations', $locations['imported']));
        }

        if ($result['stations'] < 0) {
            $io->error('Failed to refresh Buienradar stations.');
            $hasError = true;
        } else {
            $io->success(sprintf('Refreshed %d Buienradar weather stations', $result['stations']));
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }
}
