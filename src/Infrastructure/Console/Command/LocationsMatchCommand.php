<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\Service\BuienradarStationMatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:locations:match',
    description: 'Show the matching Buienradar station for an RWS location',
)]
final class LocationsMatchCommand extends Command
{
    public function __construct(
        private readonly RwsLocationRepositoryInterface $locationRepository,
        private readonly BuienradarStationMatcher $stationMatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('location', InputArgument::REQUIRED, 'RWS location code or name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locationQuery = $input->getArgument('location');

        $location = $this->locationRepository->findById($locationQuery);

        if (null === $location) {
            // Try searching by name
            $searchLower = strtolower($locationQuery);
            foreach ($this->locationRepository->findAll() as $loc) {
                if (str_contains(strtolower($loc->getName()), $searchLower)
                    || str_contains(strtolower($loc->getId()), $searchLower)) {
                    $location = $loc;
                    break;
                }
            }
        }

        if (null === $location) {
            $io->error(sprintf('Location "%s" not found.', $locationQuery));

            return Command::FAILURE;
        }

        $io->section('RWS Location');
        $io->table(
            ['ID', 'Name', 'Latitude', 'Longitude'],
            [[$location->getId(), $location->getName(), $location->getLatitude(), $location->getLongitude()]],
        );

        $station = $this->stationMatcher->findMatchingStation($location->getName());

        if (null === $station) {
            $io->warning('No matching Buienradar station found.');

            return Command::SUCCESS;
        }

        $io->section('Matching Buienradar Station');
        $io->table(
            ['Code', 'Name', 'Latitude', 'Longitude'],
            [[$station->getCode(), $station->getName(), $station->getLatitude(), $station->getLongitude()]],
        );

        return Command::SUCCESS;
    }
}
