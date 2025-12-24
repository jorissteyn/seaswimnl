<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Domain\Service\NearestStationFinder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:locations:nearest-station',
    description: 'Find the nearest location with a specific capability (Hm0, Tm02, Th3)',
    aliases: ['seaswim:locations:nearest-wave-station'],
)]
final class NearestBuoyCommand extends Command
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly NearestStationFinder $stationFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('location', InputArgument::REQUIRED, 'RWS location ID')
            ->addOption('capability', 'c', InputOption::VALUE_REQUIRED, 'Capability to search for (Hm0, Tm02, Th3)', 'Hm0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locationId = $input->getArgument('location');
        $capability = $input->getOption('capability');

        $location = $this->locationRepository->findById($locationId);

        if (null === $location) {
            $io->error(sprintf('Location "%s" not found', $locationId));

            return Command::FAILURE;
        }

        $allLocations = $this->locationRepository->findAll();
        $result = $this->stationFinder->findNearest($location, $allLocations, $capability);

        if (null === $result) {
            $io->warning(sprintf('No locations with %s capability found', $capability));

            return Command::FAILURE;
        }

        $station = $result['location'];
        $distance = $result['distanceKm'];

        $io->title(sprintf('Nearest Station with %s', $capability));

        $io->table(
            ['Property', 'Value'],
            [
                ['Source Location', sprintf('%s (%s)', $location->getName(), $location->getId())],
                ['Source Coordinates', sprintf('%.4f, %.4f', $location->getLatitude(), $location->getLongitude())],
                ['Nearest Station', sprintf('%s (%s)', $station->getName(), $station->getId())],
                ['Station Coordinates', sprintf('%.4f, %.4f', $station->getLatitude(), $station->getLongitude())],
                ['Capability', $capability],
                ['Distance', sprintf('%.2f km', $distance)],
            ],
        );

        return Command::SUCCESS;
    }
}
