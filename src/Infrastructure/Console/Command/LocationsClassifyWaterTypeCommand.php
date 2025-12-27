<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\Port\RwsLocationRepositoryInterface;
use Seaswim\Domain\ValueObject\RwsLocation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'seaswim:locations:classify-water-type',
    description: 'Classify RWS locations by water body type using PDOK BGT API',
)]
final class LocationsClassifyWaterTypeCommand extends Command
{
    private const PDOK_BGT_URL = 'https://api.pdok.nl/lv/bgt/ogc/v1/collections/waterdeel/items';

    public function __construct(
        private readonly RwsLocationRepositoryInterface $locationRepository,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('only-unknown', null, InputOption::VALUE_NONE, 'Only classify locations with unknown water type')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not save changes')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit number of locations to process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $onlyUnknown = $input->getOption('only-unknown');
        $dryRun = $input->getOption('dry-run');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;

        $io->title('Classify RWS Locations by Water Body Type');

        $allLocations = $this->locationRepository->findAll();
        $io->text(sprintf('Found %d locations', count($allLocations)));

        // Filter to only unknown if requested
        if ($onlyUnknown) {
            $toProcess = array_filter(
                $allLocations,
                fn (RwsLocation $loc) => RwsLocation::WATER_TYPE_UNKNOWN === $loc->getWaterBodyType()
            );
            $io->text(sprintf('Filtering to %d locations with unknown water type', count($toProcess)));
        } else {
            $toProcess = $allLocations;
        }

        if ($limit) {
            $toProcess = array_slice($toProcess, 0, $limit);
            $io->text(sprintf('Limited to %d locations', count($toProcess)));
        }

        $stats = [
            RwsLocation::WATER_TYPE_SEA => 0,
            RwsLocation::WATER_TYPE_LAKE => 0,
            RwsLocation::WATER_TYPE_RIVER => 0,
            RwsLocation::WATER_TYPE_UNKNOWN => 0,
        ];

        $updatedLocations = [];
        $progressBar = new ProgressBar($output, count($toProcess));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->start();

        foreach ($toProcess as $location) {
            $progressBar->setMessage($location->getId());
            $progressBar->advance();

            $waterType = $this->classifyLocation($location);
            ++$stats[$waterType];

            $updatedLocations[$location->getId()] = new RwsLocation(
                $location->getId(),
                $location->getName(),
                $location->getLatitude(),
                $location->getLongitude(),
                $location->getCompartimenten(),
                $location->getGrootheden(),
                $waterType,
            );

            // Rate limiting - PDOK API is public but be nice
            usleep(50000); // 50ms between requests
        }

        $progressBar->finish();
        $io->newLine(2);

        // Merge updated locations back into all locations
        $finalLocations = array_map(
            fn (RwsLocation $loc) => $updatedLocations[$loc->getId()] ?? $loc,
            $allLocations
        );

        if (!$dryRun) {
            $this->locationRepository->saveAll($finalLocations);
            $io->success('Locations saved');
        } else {
            $io->warning('Dry run - no changes saved');
        }

        $io->table(
            ['Water Type', 'Count'],
            [
                ['Sea', $stats[RwsLocation::WATER_TYPE_SEA]],
                ['Lake', $stats[RwsLocation::WATER_TYPE_LAKE]],
                ['River', $stats[RwsLocation::WATER_TYPE_RIVER]],
                ['Unknown', $stats[RwsLocation::WATER_TYPE_UNKNOWN]],
            ]
        );

        return Command::SUCCESS;
    }

    private function classifyLocation(RwsLocation $location): string
    {
        $delta = 0.005; // ~500m bbox
        $bbox = sprintf(
            '%f,%f,%f,%f',
            $location->getLongitude() - $delta,
            $location->getLatitude() - $delta,
            $location->getLongitude() + $delta,
            $location->getLatitude() + $delta
        );

        try {
            $response = $this->httpClient->request('GET', self::PDOK_BGT_URL, [
                'query' => [
                    'f' => 'json',
                    'bbox' => $bbox,
                    'limit' => 50,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);
            $features = $data['features'] ?? [];

            if ([] === $features) {
                return RwsLocation::WATER_TYPE_UNKNOWN;
            }

            // Count BGT water types
            $bgtTypes = [];
            foreach ($features as $feature) {
                $type = $feature['properties']['type'] ?? null;
                if ($type) {
                    $bgtTypes[$type] = ($bgtTypes[$type] ?? 0) + 1;
                }
            }

            // Map BGT types to our water body types (priority order)
            // zee = sea/coastal
            // watervlakte = lake/harbor
            // waterloop = river/canal
            if (isset($bgtTypes['zee'])) {
                return RwsLocation::WATER_TYPE_SEA;
            }
            if (isset($bgtTypes['watervlakte'])) {
                return RwsLocation::WATER_TYPE_LAKE;
            }
            if (isset($bgtTypes['waterloop'])) {
                return RwsLocation::WATER_TYPE_RIVER;
            }

            return RwsLocation::WATER_TYPE_UNKNOWN;
        } catch (\Throwable) {
            return RwsLocation::WATER_TYPE_UNKNOWN;
        }
    }
}
