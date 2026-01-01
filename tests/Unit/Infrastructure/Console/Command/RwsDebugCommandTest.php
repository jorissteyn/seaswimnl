<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Seaswim\Infrastructure\Console\Command\RwsDebugCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RwsDebugCommandTest extends TestCase
{
    private const API_URL = 'https://waterwebservices.rijkswaterstaat.nl';

    private function createMockResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($data);
        $response->method('getStatusCode')->willReturn($statusCode);

        return $response;
    }

    public function testCommandIsProperlyConfigured(): void
    {
        // Arrange
        $httpClient = $this->createMock(HttpClientInterface::class);
        $command = new RwsDebugCommand($httpClient, self::API_URL);

        // Act
        $name = $command->getName();
        $description = $command->getDescription();

        // Assert
        $this->assertSame('seaswim:rws:debug', $name);
        $this->assertSame('Display the full RWS API response for a given location ID', $description);
    }

    public function testCommandRequiresLocationArgument(): void
    {
        // Arrange
        $httpClient = $this->createMock(HttpClientInterface::class);
        $command = new RwsDebugCommand($httpClient, self::API_URL);

        // Act
        $definition = $command->getDefinition();

        // Assert
        $this->assertTrue($definition->hasArgument('location'));
        $argument = $definition->getArgument('location');
        $this->assertTrue($argument->isRequired());
        $this->assertSame('The RWS location ID (e.g., vlissingen, zeelandbrug.noord)', $argument->getDescription());
    }

    public function testExecuteDisplaysSuccessfulResponseWithObservations(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.5],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'WATHTE'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 200],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                self::API_URL.'/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen',
                $this->callback(function ($options) {
                    $this->assertArrayHasKey('json', $options);
                    $this->assertArrayHasKey('timeout', $options);
                    $this->assertSame(30, $options['timeout']);
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertSame('application/json', $options['headers']['Content-Type']);
                    $this->assertArrayHasKey('LocatieLijst', $options['json']);
                    $this->assertSame([['Code' => 'vlissingen']], $options['json']['LocatieLijst']);

                    return true;
                })
            )
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'vlissingen']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('RWS API Debug: vlissingen', $output);
        $this->assertStringContainsString('Request', $output);
        $this->assertStringContainsString('Response', $output);
        $this->assertStringContainsString('Grootheid', $output);
        $this->assertStringContainsString('Compartiment', $output);
        $this->assertStringContainsString('T', $output);
        $this->assertStringContainsString('WATHTE', $output);
        $this->assertStringContainsString('OW', $output);
        $this->assertStringContainsString('15.5', $output);
        $this->assertStringContainsString('200', $output);
    }

    public function testExecuteDisplaysErrorWhenApiReturnsSuccesvolFalse(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => false,
            'Foutmelding' => 'Location not found',
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'invalid-location']);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Location not found', $output);
    }

    public function testExecuteDisplaysUnknownErrorWhenFoutmeldingIsNotProvided(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => false,
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Unknown error', $output);
    }

    public function testExecuteDisplaysWarningWhenNoObservationsAreReturned(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'empty-location']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No observations returned', $output);
    }

    public function testExecuteDisplaysWarningWhenWaarnemingenLijstIsMissing(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No observations returned', $output);
    }

    public function testExecuteHandlesObservationsWithNoData(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'Hm0'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Hm0', $output);
        $this->assertStringContainsString('No data', $output);
    }

    public function testExecuteHandlesMissingMetadata(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 10.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('?', $output);
    }

    public function testExecuteHandlesMissingMeetwaarde(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => [],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('N/A', $output);
    }

    public function testExecuteHandlesMissingTijdstip(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Meetwaarde' => ['Waarde_Numeriek' => 12.5],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('N/A', $output);
    }

    public function testExecuteFormatsTimestampCorrectly(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T14:30:45+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 18.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('2025-01-01 14:30:45', $output);
    }

    public function testExecuteKeepsOriginalTimestampWhenParsingFails(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => 'invalid-date-format',
                            'Meetwaarde' => ['Waarde_Numeriek' => 20.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('invalid-date-format', $output);
    }

    public function testExecuteDisplaysFullJsonResponseInVerboseMode(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Full Response (JSON)', $output);
        $this->assertStringContainsString('"Succesvol": true', $output);
    }

    public function testExecuteDoesNotDisplayFullJsonResponseInNormalMode(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringNotContainsString('Full Response (JSON)', $output);
        $this->assertStringContainsString('Use -v to see full JSON response', $output);
    }

    public function testExecuteDisplaysRequestPayload(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'vlissingen']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Request', $output);
        $this->assertStringContainsString('URL:', $output);
        $this->assertStringContainsString('Payload:', $output);
        $this->assertStringContainsString('LocatieLijst', $output);
        $this->assertStringContainsString('vlissingen', $output);
        $this->assertStringContainsString('AquoPlusWaarnemingMetadataLijst', $output);
    }

    public function testExecuteHandlesHttpClientException(): void
    {
        // Arrange
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willThrowException(new \RuntimeException('Network error'));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('API request failed', $output);
        $this->assertStringContainsString('Network error', $output);
    }

    public function testExecuteHandlesGenericException(): void
    {
        // Arrange
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willThrowException(new \Exception('Unexpected error'));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('API request failed', $output);
        $this->assertStringContainsString('Unexpected error', $output);
    }

    public function testExecuteSendsCorrectPayloadForAllMeasurementTypes(): void
    {
        // Arrange
        $responseData = ['Succesvol' => true, 'WaarnemingenLijst' => []];
        $capturedPayload = null;

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function ($options) use (&$capturedPayload) {
                    $capturedPayload = $options['json'];

                    return true;
                })
            )
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertNotNull($capturedPayload);
        $this->assertArrayHasKey('AquoPlusWaarnemingMetadataLijst', $capturedPayload);
        $metadata = $capturedPayload['AquoPlusWaarnemingMetadataLijst'];
        $this->assertCount(7, $metadata);

        // Verify all expected measurement types
        $expectedTypes = [
            ['Compartiment' => 'OW', 'Grootheid' => 'T'],
            ['Compartiment' => 'OW', 'Grootheid' => 'WATHTE'],
            ['Compartiment' => 'OW', 'Grootheid' => 'Hm0'],
            ['Compartiment' => 'OW', 'Grootheid' => 'Tm02'],
            ['Compartiment' => 'OW', 'Grootheid' => 'Th3'],
            ['Compartiment' => 'LT', 'Grootheid' => 'WINDSHD'],
            ['Compartiment' => 'LT', 'Grootheid' => 'WINDRTG'],
        ];

        foreach ($expectedTypes as $index => $expected) {
            $this->assertSame(
                $expected['Compartiment'],
                $metadata[$index]['AquoMetadata']['Compartiment']['Code']
            );
            $this->assertSame(
                $expected['Grootheid'],
                $metadata[$index]['AquoMetadata']['Grootheid']['Code']
            );
        }
    }

    public function testExecuteDisplaysDataAgeInMinutes(): void
    {
        // Arrange
        $fiveMinutesAgo = (new \DateTimeImmutable())->modify('-5 minutes')->format('c');
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => $fiveMinutesAgo,
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('minutes ago', $output);
    }

    public function testExecuteDisplaysDataAgeInHours(): void
    {
        // Arrange
        $twoHoursAgo = (new \DateTimeImmutable())->modify('-2 hours')->format('c');
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => $twoHoursAgo,
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('hours ago', $output);
    }

    public function testExecuteDisplaysDataAgeInDays(): void
    {
        // Arrange
        $threeDaysAgo = (new \DateTimeImmutable())->modify('-3 days')->format('c');
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => $threeDaysAgo,
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('days ago', $output);
    }

    public function testExecuteDisplaysDataAgeInMonths(): void
    {
        // Arrange
        $twoMonthsAgo = (new \DateTimeImmutable())->modify('-2 months')->format('c');
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => $twoMonthsAgo,
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('months ago', $output);
    }

    public function testExecuteDisplaysDataAgeInYears(): void
    {
        // Arrange
        $oneYearAgo = (new \DateTimeImmutable())->modify('-1 year')->format('c');
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => $oneYearAgo,
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('years ago', $output);
    }

    public function testExecuteDisplaysDashForDataAgeWhenTimestampIsNull(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        // The Age column should contain '-'
        $this->assertMatchesRegularExpression('/Age.*-/s', $output);
    }

    public function testExecuteDisplaysDashForDataAgeWhenTimestampIsInvalid(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => 'not-a-valid-date',
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        // The Age column should contain '-' for invalid dates
        $this->assertMatchesRegularExpression('/Age.*-/s', $output);
    }

    public function testExecuteHandlesMultipleObservations(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'WATHTE'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 250],
                        ],
                    ],
                ],
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'WINDSHD'],
                        'Compartiment' => ['Code' => 'LT'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 5.5],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('T', $output);
        $this->assertStringContainsString('WATHTE', $output);
        $this->assertStringContainsString('WINDSHD', $output);
        $this->assertStringContainsString('15', $output);
        $this->assertStringContainsString('250', $output);
        $this->assertStringContainsString('5.5', $output);
        $this->assertStringContainsString('LT', $output);
    }

    public function testExecuteDisplaysTableWithCorrectHeaders(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [
                [
                    'AquoMetadata' => [
                        'Grootheid' => ['Code' => 'T'],
                        'Compartiment' => ['Code' => 'OW'],
                    ],
                    'MetingenLijst' => [
                        [
                            'Tijdstip' => '2025-01-01T12:00:00+01:00',
                            'Meetwaarde' => ['Waarde_Numeriek' => 15.0],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Grootheid', $output);
        $this->assertStringContainsString('Compartiment', $output);
        $this->assertStringContainsString('Value', $output);
        $this->assertStringContainsString('Timestamp', $output);
        $this->assertStringContainsString('Age', $output);
    }

    public function testExecuteUsesCorrectHttpTimeout(): void
    {
        // Arrange
        $responseData = ['Succesvol' => true, 'WaarnemingenLijst' => []];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function ($options) {
                    $this->assertSame(30, $options['timeout']);

                    return true;
                })
            )
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'test']);
    }

    public function testExecuteConstructsCorrectApiUrl(): void
    {
        // Arrange
        $responseData = ['Succesvol' => true, 'WaarnemingenLijst' => []];
        $customApiUrl = 'https://custom.api.url';

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $customApiUrl.'/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen',
                $this->anything()
            )
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, $customApiUrl);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'test']);
    }

    public function testExecuteDisplaysLocationInTitle(): void
    {
        // Arrange
        $responseData = ['Succesvol' => true, 'WaarnemingenLijst' => []];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute(['location' => 'zeelandbrug.noord']);

        // Assert
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('RWS API Debug: zeelandbrug.noord', $output);
    }

    public function testExecuteDisplaysFullJsonResponseWhenWarningIsShown(): void
    {
        // Arrange
        $responseData = [
            'Succesvol' => true,
            'WaarnemingenLijst' => [],
            'SomeOtherField' => 'additional data',
        ];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($this->createMockResponse($responseData));

        $command = new RwsDebugCommand($httpClient, self::API_URL);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['location' => 'test']);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No observations returned', $output);
        $this->assertStringContainsString('"Succesvol": true', $output);
        $this->assertStringContainsString('SomeOtherField', $output);
    }
}
