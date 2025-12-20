<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\UseCase\ClearCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:cache:clear',
    description: 'Clear cached API responses',
)]
final class CacheClearCommand extends Command
{
    public function __construct(
        private readonly ClearCache $clearCache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->clearCache->execute();

        if ($result) {
            $io->success('Cache cleared successfully');
        } else {
            $io->info('Cache was already empty');
        }

        return Command::SUCCESS;
    }
}
