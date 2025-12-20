<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:locations:list',
    description: 'List all swim locations',
)]
final class LocationsListCommand extends Command
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Filter locations by name or code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $asJson = $input->getOption('json');
        $search = $input->getOption('search');

        $locations = $this->locationRepository->findAll();

        if (null !== $search) {
            $search = strtolower($search);
            $locations = array_filter(
                $locations,
                fn ($loc) => str_contains(strtolower($loc->getName()), $search)
                    || str_contains(strtolower($loc->getId()), $search),
            );
        }

        if ([] === $locations) {
            $io->warning('No locations found. Run seaswim:locations:refresh to fetch locations.');

            return Command::SUCCESS;
        }

        if ($asJson) {
            $data = array_map(
                fn ($loc) => [
                    'id' => $loc->getId(),
                    'name' => $loc->getName(),
                    'latitude' => $loc->getLatitude(),
                    'longitude' => $loc->getLongitude(),
                ],
                array_values($locations),
            );
            $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title(sprintf('Locations (%d)', count($locations)));

        $rows = array_map(
            fn ($loc) => [
                $loc->getId(),
                $loc->getName(),
                sprintf('%.4f', $loc->getLatitude()),
                sprintf('%.4f', $loc->getLongitude()),
            ],
            array_values($locations),
        );

        $io->table(['ID', 'Name', 'Latitude', 'Longitude'], $rows);

        return Command::SUCCESS;
    }
}
