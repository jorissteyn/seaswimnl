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

        if ($conditions === null) {
            $io->error(sprintf('Location "%s" not found', $locationId));

            return Command::FAILURE;
        }

        if ($asJson) {
            $output->writeln(json_encode($this->formatForJson($conditions), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->displayTable($io, $conditions);

        return Command::SUCCESS;
    }

    private function displayTable(SymfonyStyle $io, array $conditions): void
    {
        $io->title('Water Conditions');

        $water = $conditions['water'];
        if ($water !== null) {
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Temperature', $water->getTemperature()->getCelsius() !== null ? $water->getTemperature()->getCelsius().'°C' : 'N/A'],
                    ['Wave Height', $water->getWaveHeight()->getMeters() !== null ? $water->getWaveHeight()->getMeters().'m' : 'N/A'],
                    ['Water Height', $water->getWaterHeight()->getMeters() !== null ? $water->getWaterHeight()->getMeters().'m' : 'N/A'],
                    ['Quality', $water->getQuality()->getLabel()],
                    ['Measured At', $water->getMeasuredAt()->format('Y-m-d H:i:s')],
                ],
            );
        } else {
            $io->warning('Water conditions unavailable');
        }

        $io->title('Weather Conditions');

        $weather = $conditions['weather'];
        if ($weather !== null) {
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Air Temperature', $weather->getAirTemperature()->getCelsius() !== null ? $weather->getAirTemperature()->getCelsius().'°C' : 'N/A'],
                    ['Wind Speed', $weather->getWindSpeed()->getKilometersPerHour() !== null ? round($weather->getWindSpeed()->getKilometersPerHour(), 1).' km/h' : 'N/A'],
                    ['Wind Direction', $weather->getWindDirection() ?? 'N/A'],
                    ['UV Index', $weather->getUvIndex()->getValue() !== null ? $weather->getUvIndex()->getValue().' ('.$weather->getUvIndex()->getLevel().')' : 'N/A'],
                    ['Measured At', $weather->getMeasuredAt()->format('Y-m-d H:i:s')],
                ],
            );
        } else {
            $io->warning('Weather conditions unavailable');
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
                ['Recommendation', sprintf('%s - %s', $metrics->getRecommendation()->getLabel(), $metrics->getRecommendation()->getExplanation())],
            ],
        );
    }

    private function formatForJson(array $conditions): array
    {
        $result = [];

        $water = $conditions['water'];
        if ($water !== null) {
            $result['water'] = [
                'temperature' => $water->getTemperature()->getCelsius(),
                'waveHeight' => $water->getWaveHeight()->getMeters(),
                'waterHeight' => $water->getWaterHeight()->getMeters(),
                'quality' => $water->getQuality()->value,
                'measuredAt' => $water->getMeasuredAt()->format('c'),
            ];
        }

        $weather = $conditions['weather'];
        if ($weather !== null) {
            $result['weather'] = [
                'airTemperature' => $weather->getAirTemperature()->getCelsius(),
                'windSpeed' => $weather->getWindSpeed()->getMetersPerSecond(),
                'windDirection' => $weather->getWindDirection(),
                'uvIndex' => $weather->getUvIndex()->getValue(),
                'measuredAt' => $weather->getMeasuredAt()->format('c'),
            ];
        }

        $metrics = $conditions['metrics'];
        $result['metrics'] = [
            'safetyScore' => $metrics->getSafetyScore()->value,
            'safetyLabel' => $metrics->getSafetyScore()->getLabel(),
            'comfortIndex' => $metrics->getComfortIndex()->getValue(),
            'comfortLabel' => $metrics->getComfortIndex()->getLabel(),
            'recommendation' => $metrics->getRecommendation()->getTypeValue(),
            'recommendationLabel' => $metrics->getRecommendation()->getLabel(),
            'recommendationExplanation' => $metrics->getRecommendation()->getExplanation(),
        ];

        return $result;
    }
}
