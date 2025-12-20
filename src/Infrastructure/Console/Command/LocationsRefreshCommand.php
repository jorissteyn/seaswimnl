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
    description: 'Refresh swim locations from Rijkswaterstaat',
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

        $io->info('Refreshing locations from Rijkswaterstaat...');

        $count = $this->refreshLocations->execute();

        if ($count < 0) {
            $io->error('Failed to refresh locations. API may be unavailable.');

            return Command::FAILURE;
        }

        $io->success(sprintf('Refreshed %d locations', $count));

        return Command::SUCCESS;
    }
}
