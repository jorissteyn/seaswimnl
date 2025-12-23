<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\Port\KnmiStationRepositoryInterface;
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
        private readonly KnmiStationRepositoryInterface $knmiStationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Filter locations by name or code')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Filter by source: rws, knmi (default: both)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $asJson = $input->getOption('json');
        $search = $input->getOption('search');
        $source = $input->getOption('source');

        $items = [];

        if (null === $source || 'rws' === $source) {
            foreach ($this->locationRepository->findAll() as $loc) {
                $items[] = [
                    'source' => 'rws',
                    'id' => $loc->getId(),
                    'name' => $loc->getName(),
                    'latitude' => $loc->getLatitude(),
                    'longitude' => $loc->getLongitude(),
                ];
            }
        }

        if (null === $source || 'knmi' === $source) {
            foreach ($this->knmiStationRepository->findAll() as $station) {
                $items[] = [
                    'source' => 'knmi',
                    'id' => $station->getCode(),
                    'name' => $station->getName(),
                    'latitude' => $station->getLatitude(),
                    'longitude' => $station->getLongitude(),
                ];
            }
        }

        if (null !== $search) {
            $search = strtolower($search);
            $items = array_filter(
                $items,
                fn ($item) => str_contains(strtolower($item['name']), $search)
                    || str_contains(strtolower($item['id']), $search),
            );
        }

        if ([] === $items) {
            $io->warning('No locations found. Run seaswim:locations:refresh to fetch locations.');

            return Command::SUCCESS;
        }

        if ($asJson) {
            $output->writeln(json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $io->title(sprintf('Locations (%d)', count($items)));

        $rows = array_map(
            fn ($item) => [
                $item['source'],
                $item['id'],
                $item['name'],
                sprintf('%.4f', $item['latitude']),
                sprintf('%.4f', $item['longitude']),
            ],
            array_values($items),
        );

        $io->table(['Source', 'ID', 'Name', 'Latitude', 'Longitude'], $rows);

        return Command::SUCCESS;
    }
}
