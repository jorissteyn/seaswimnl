<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\Port\LocationRepositoryInterface;
use Seaswim\Domain\Service\NearestBuoyFinder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:locations:nearest-buoy',
    description: 'Find the nearest buoy (boei) for a given RWS location',
)]
final class NearestBuoyCommand extends Command
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly NearestBuoyFinder $buoyFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('location', InputArgument::REQUIRED, 'RWS location ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locationId = $input->getArgument('location');

        $location = $this->locationRepository->findById($locationId);

        if (null === $location) {
            $io->error(sprintf('Location "%s" not found', $locationId));

            return Command::FAILURE;
        }

        $allLocations = $this->locationRepository->findAll();
        $result = $this->buoyFinder->findNearestBuoy($location, $allLocations);

        if (null === $result) {
            $io->warning('No buoy locations found');

            return Command::FAILURE;
        }

        $buoy = $result['location'];
        $distance = $result['distanceKm'];

        $io->title('Nearest Buoy');

        $io->table(
            ['Property', 'Value'],
            [
                ['Source Location', sprintf('%s (%s)', $location->getName(), $location->getId())],
                ['Source Coordinates', sprintf('%.4f, %.4f', $location->getLatitude(), $location->getLongitude())],
                ['Nearest Buoy', sprintf('%s (%s)', $buoy->getName(), $buoy->getId())],
                ['Buoy Coordinates', sprintf('%.4f, %.4f', $buoy->getLatitude(), $buoy->getLongitude())],
                ['Distance', sprintf('%.2f km', $distance)],
            ],
        );

        return Command::SUCCESS;
    }
}
