<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\Port\BuienradarStationRepositoryInterface;
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
        private readonly BuienradarStationRepositoryInterface $buienradarStationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Filter locations by name or code')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Filter by source: rws, buienradar (default: both)')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter RWS locations by grootheid code (e.g., Hm0 for wave height, T for temperature, WATHTE for water height)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $asJson = $input->getOption('json');
        $search = $input->getOption('search');
        $source = $input->getOption('source');
        $filter = $input->getOption('filter');

        $items = [];

        if (null === $source || 'rws' === $source) {
            foreach ($this->locationRepository->findAll() as $loc) {
                $items[] = [
                    'source' => 'rws',
                    'id' => $loc->getId(),
                    'name' => $loc->getName(),
                    'latitude' => $loc->getLatitude(),
                    'longitude' => $loc->getLongitude(),
                    'compartimenten' => $loc->getCompartimenten(),
                    'grootheden' => $loc->getGrootheden(),
                ];
            }
        }

        if (null === $source || 'buienradar' === $source) {
            foreach ($this->buienradarStationRepository->findAll() as $station) {
                $items[] = [
                    'source' => 'buienradar',
                    'id' => $station->getCode(),
                    'name' => $station->getName(),
                    'latitude' => $station->getLatitude(),
                    'longitude' => $station->getLongitude(),
                    'compartimenten' => [],
                    'grootheden' => [],
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

        if (null !== $filter) {
            $items = array_filter(
                $items,
                fn ($item) => \in_array($filter, $item['grootheden'], true),
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
                implode(', ', $item['compartimenten']),
                implode(', ', $item['grootheden']),
            ],
            array_values($items),
        );

        $io->table(['Source', 'ID', 'Name', 'Latitude', 'Longitude', 'Compartimenten', 'Grootheden'], $rows);

        return Command::SUCCESS;
    }
}
