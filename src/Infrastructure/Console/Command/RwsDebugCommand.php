<?php

declare(strict_types=1);

namespace Seaswim\Infrastructure\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'seaswim:rws:debug',
    description: 'Display the full RWS API response for a given location ID',
)]
final class RwsDebugCommand extends Command
{
    private const LATEST_OBSERVATIONS_PATH = '/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $rwsApiUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('location', InputArgument::REQUIRED, 'The RWS location ID (e.g., vlissingen, zeelandbrug.noord)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locationId = $input->getArgument('location');

        $io->title(sprintf('RWS API Debug: %s', $locationId));

        $payload = [
            'LocatieLijst' => [
                ['Code' => $locationId],
            ],
            'AquoPlusWaarnemingMetadataLijst' => [
                ['AquoMetadata' => ['Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'T']]],
                ['AquoMetadata' => ['Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'WATHTE']]],
                ['AquoMetadata' => ['Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'Hm0']]],
                ['AquoMetadata' => ['Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'Tm02']]],
                ['AquoMetadata' => ['Compartiment' => ['Code' => 'OW'], 'Grootheid' => ['Code' => 'Th3']]],
                ['AquoMetadata' => ['Compartiment' => ['Code' => 'LT'], 'Grootheid' => ['Code' => 'WINDSHD']]],
                ['AquoMetadata' => ['Compartiment' => ['Code' => 'LT'], 'Grootheid' => ['Code' => 'WINDRTG']]],
            ],
        ];

        $url = $this->rwsApiUrl.self::LATEST_OBSERVATIONS_PATH;

        $io->section('Request');
        $io->text(sprintf('URL: %s', $url));
        $io->text('Payload:');
        $io->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray(false);

            $io->section('Response');

            if (isset($data['Succesvol']) && false === $data['Succesvol']) {
                $io->error($data['Foutmelding'] ?? 'Unknown error');

                return Command::FAILURE;
            }

            if (!isset($data['WaarnemingenLijst']) || [] === $data['WaarnemingenLijst']) {
                $io->warning('No observations returned');
                $io->writeln((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return Command::SUCCESS;
            }

            // Display summary table
            $rows = [];
            foreach ($data['WaarnemingenLijst'] as $observation) {
                $grootheid = $observation['AquoMetadata']['Grootheid']['Code'] ?? '?';
                $compartiment = $observation['AquoMetadata']['Compartiment']['Code'] ?? '?';
                $metingen = $observation['MetingenLijst'] ?? [];

                if ([] === $metingen) {
                    $rows[] = [$grootheid, $compartiment, 'No data', '-', '-'];
                    continue;
                }

                $latest = $metingen[0];
                $value = $latest['Meetwaarde']['Waarde_Numeriek'] ?? 'N/A';
                $timestamp = $latest['Tijdstip'] ?? 'N/A';

                // Format timestamp for readability
                if ('N/A' !== $timestamp) {
                    try {
                        $dt = new \DateTimeImmutable($timestamp);
                        $timestamp = $dt->format('Y-m-d H:i:s');
                    } catch (\Exception) {
                        // Keep original
                    }
                }

                $rows[] = [$grootheid, $compartiment, $value, $timestamp, $this->getDataAge($latest['Tijdstip'] ?? null)];
            }

            $io->table(['Grootheid', 'Compartiment', 'Value', 'Timestamp', 'Age'], $rows);

            // Full JSON output with -v
            if ($output->isVerbose()) {
                $io->section('Full Response (JSON)');
                $io->writeln((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } else {
                $io->text('Use -v to see full JSON response');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('API request failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function getDataAge(?string $timestamp): string
    {
        if (null === $timestamp) {
            return '-';
        }

        try {
            $dt = new \DateTimeImmutable($timestamp);
            $now = new \DateTimeImmutable();
            $diff = $now->diff($dt);

            if ($diff->y > 0) {
                return sprintf('%d years ago', $diff->y);
            }
            if ($diff->m > 0) {
                return sprintf('%d months ago', $diff->m);
            }
            if ($diff->d > 0) {
                return sprintf('%d days ago', $diff->d);
            }
            if ($diff->h > 0) {
                return sprintf('%d hours ago', $diff->h);
            }

            return sprintf('%d minutes ago', $diff->i);
        } catch (\Exception) {
            return '-';
        }
    }
}
