<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Seaswim\Application\UseCase\GetConditionsForLocation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaswim:conditions',
    description: 'Show water and weather conditions for a location',
)]
final class ConditionsCommand extends Command
{
    public function __construct(
        private readonly GetConditionsForLocation $getConditions,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('location', InputArgument::REQUIRED, 'Location ID')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locationId = $input->getArgument('location');
        $asJson = $input->getOption('json');

        $conditions = $this->getConditions->execute($locationId);

        if (null === $conditions) {
            $io->error(sprintf('Location "%s" not found', $locationId));

            return Command::FAILURE;
        }

        if ($asJson) {
            $json = json_encode($this->formatForJson($conditions), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $output->writeln($json);

            return Command::SUCCESS;
        }

        $this->displayTable($io, $conditions);

        return Command::SUCCESS;
    }

    private function displayTable(SymfonyStyle $io, array $conditions): void
    {
        $io->title('Water Conditions');

        $water = $conditions['water'];
        if (null !== $water) {
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Temperature', null !== $water->getTemperature()->getCelsius() ? $water->getTemperature()->getCelsius().'°C' : 'N/A'],
                    ['Wave Height', null !== $water->getWaveHeight()->getMeters() ? $water->getWaveHeight()->getMeters().'m' : 'N/A'],
                    ['Water Height', null !== $water->getWaterHeight()->getMeters() ? $water->getWaterHeight()->getMeters().'m' : 'N/A'],
                    ['Quality', $water->getQuality()->getLabel()],
                    ['Measured At', $water->getMeasuredAt()->format('Y-m-d H:i:s')],
                ],
            );
        } else {
            $io->warning('Water conditions unavailable');
        }

        $io->title('Weather Conditions');

        $weather = $conditions['weather'];
        if (null !== $weather) {
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Air Temperature', null !== $weather->getAirTemperature()->getCelsius() ? $weather->getAirTemperature()->getCelsius().'°C' : 'N/A'],
                    ['Wind Speed', null !== $weather->getWindSpeed()->getKilometersPerHour() ? round($weather->getWindSpeed()->getKilometersPerHour(), 1).' km/h' : 'N/A'],
                    ['Wind Direction', $weather->getWindDirection() ?? 'N/A'],
                    ['UV Index', null !== $weather->getUvIndex()->getValue() ? $weather->getUvIndex()->getValue().' ('.$weather->getUvIndex()->getLevel().')' : 'N/A'],
                    ['Measured At', $weather->getMeasuredAt()->format('Y-m-d H:i:s')],
                ],
            );
        } else {
            $io->warning('Weather conditions unavailable');
        }

        $io->title('Tides');

        $tides = $conditions['tides'];
        if (null !== $tides) {
            $rows = [];

            $prevTide = $tides->getPreviousTide();
            if (null !== $prevTide) {
                $rows[] = [
                    'Previous '.$prevTide->getType()->getLabel(),
                    $prevTide->getTime()->format('H:i'),
                    sprintf('%.0f cm', $prevTide->getHeightCm()),
                ];
            }

            $nextTide = $tides->getNextTide();
            if (null !== $nextTide) {
                $rows[] = [
                    'Next '.$nextTide->getType()->getLabel(),
                    $nextTide->getTime()->format('H:i'),
                    sprintf('%.0f cm', $nextTide->getHeightCm()),
                ];
            }

            $nextHigh = $tides->getNextHighTide();
            if (null !== $nextHigh && $nextHigh !== $nextTide) {
                $rows[] = [
                    'Next High tide',
                    $nextHigh->getTime()->format('H:i'),
                    sprintf('%.0f cm', $nextHigh->getHeightCm()),
                ];
            }

            $nextLow = $tides->getNextLowTide();
            if (null !== $nextLow && $nextLow !== $nextTide) {
                $rows[] = [
                    'Next Low tide',
                    $nextLow->getTime()->format('H:i'),
                    sprintf('%.0f cm', $nextLow->getHeightCm()),
                ];
            }

            if ([] !== $rows) {
                $io->table(['Tide', 'Time', 'Height (NAP)'], $rows);
            } else {
                $io->warning('No tide data available');
            }
        } else {
            $io->warning('Tidal information unavailable for this location');
        }

        $io->title('Swim Metrics');

        $metrics = $conditions['metrics'];
        $safetyColor = match ($metrics->getSafetyScore()->value) {
            'green' => 'green',
            'yellow' => 'yellow',
            'red' => 'red',
            default => 'white',
        };

        $io->table(
            ['Metric', 'Value'],
            [
                ['Safety Score', sprintf('<fg=%s>%s</> (%s)', $safetyColor, strtoupper($metrics->getSafetyScore()->value), $metrics->getSafetyScore()->getLabel())],
                ['Comfort Index', sprintf('%d/10 (%s)', $metrics->getComfortIndex()->getValue(), $metrics->getComfortIndex()->getLabel())],
            ],
        );
    }

    private function formatForJson(array $conditions): array
    {
        $result = [];

        $water = $conditions['water'];
        if (null !== $water) {
            $result['water'] = [
                'temperature' => $water->getTemperature()->getCelsius(),
                'waveHeight' => $water->getWaveHeight()->getMeters(),
                'waterHeight' => $water->getWaterHeight()->getMeters(),
                'quality' => $water->getQuality()->value,
                'measuredAt' => $water->getMeasuredAt()->format('c'),
            ];
        }

        $weather = $conditions['weather'];
        if (null !== $weather) {
            $result['weather'] = [
                'airTemperature' => $weather->getAirTemperature()->getCelsius(),
                'windSpeed' => $weather->getWindSpeed()->getMetersPerSecond(),
                'windDirection' => $weather->getWindDirection(),
                'uvIndex' => $weather->getUvIndex()->getValue(),
                'measuredAt' => $weather->getMeasuredAt()->format('c'),
            ];
        }

        $tides = $conditions['tides'];
        if (null !== $tides) {
            $result['tides'] = [];

            $prevTide = $tides->getPreviousTide();
            if (null !== $prevTide) {
                $result['tides']['previous'] = [
                    'type' => $prevTide->getType()->value,
                    'time' => $prevTide->getTime()->format('c'),
                    'heightCm' => $prevTide->getHeightCm(),
                ];
            }

            $nextTide = $tides->getNextTide();
            if (null !== $nextTide) {
                $result['tides']['next'] = [
                    'type' => $nextTide->getType()->value,
                    'time' => $nextTide->getTime()->format('c'),
                    'heightCm' => $nextTide->getHeightCm(),
                ];
            }

            $nextHigh = $tides->getNextHighTide();
            if (null !== $nextHigh) {
                $result['tides']['nextHigh'] = [
                    'time' => $nextHigh->getTime()->format('c'),
                    'heightCm' => $nextHigh->getHeightCm(),
                ];
            }

            $nextLow = $tides->getNextLowTide();
            if (null !== $nextLow) {
                $result['tides']['nextLow'] = [
                    'time' => $nextLow->getTime()->format('c'),
                    'heightCm' => $nextLow->getHeightCm(),
                ];
            }
        }

        $metrics = $conditions['metrics'];
        $result['metrics'] = [
            'safetyScore' => $metrics->getSafetyScore()->value,
            'safetyLabel' => $metrics->getSafetyScore()->getLabel(),
            'comfortIndex' => $metrics->getComfortIndex()->getValue(),
            'comfortLabel' => $metrics->getComfortIndex()->getLabel(),
        ];

        return $result;
    }
}
