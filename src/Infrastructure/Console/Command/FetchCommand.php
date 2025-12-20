<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\UseCase\FetchAllData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:fetch',
    description: 'Fetch data from external APIs for all locations',
)]
final class FetchCommand extends Command
{
    public function __construct(
        private readonly FetchAllData $fetchAllData,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force fetch ignoring cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Fetching data from external APIs...');

        $result = $this->fetchAllData->execute();

        $io->success(sprintf(
            'Fetched data for %d locations: %d water, %d weather',
            $result['locations'],
            $result['water'],
            $result['weather'],
        ));

        return Command::SUCCESS;
    }
}
