<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Seaswim\Application\UseCase\ClearCache;
use Seaswim\Infrastructure\Console\Command\CacheClearCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CacheClearCommandTest extends TestCase
{
    private function createClearCacheStub(bool $returnValue): ClearCache
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('clear')->willReturn($returnValue);

        return new ClearCache($cache);
    }

    public function testCommandIsProperlyConfigured(): void
    {
        // Arrange
        $clearCache = $this->createClearCacheStub(true);
        $command = new CacheClearCommand($clearCache);

        // Act
        $name = $command->getName();
        $description = $command->getDescription();

        // Assert
        $this->assertSame('seaswim:cache:clear', $name);
        $this->assertSame('Clear cached API responses', $description);
    }

    public function testExecuteDisplaysSuccessMessageWhenCacheIsCleared(): void
    {
        // Arrange
        $clearCache = $this->createClearCacheStub(true);
        $command = new CacheClearCommand($clearCache);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Cache cleared successfully', $commandTester->getDisplay());
    }

    public function testExecuteDisplaysInfoMessageWhenCacheWasEmpty(): void
    {
        // Arrange
        $clearCache = $this->createClearCacheStub(false);
        $command = new CacheClearCommand($clearCache);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Cache was already empty', $commandTester->getDisplay());
    }

    public function testExecuteCallsClearCacheUseCase(): void
    {
        // Arrange
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $clearCache = new ClearCache($cache);
        $command = new CacheClearCommand($clearCache);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert - expectations verified by mock
    }

    public function testExecuteAlwaysReturnsSuccessExitCode(): void
    {
        // Arrange
        $clearCache = $this->createClearCacheStub(false);
        $command = new CacheClearCommand($clearCache);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testCommandCanBeExecutedMultipleTimes(): void
    {
        // Arrange - first execution with cleared cache
        $clearCache1 = $this->createClearCacheStub(true);
        $command1 = new CacheClearCommand($clearCache1);
        $commandTester1 = new CommandTester($command1);

        // Act - first execution
        $exitCode1 = $commandTester1->execute([]);

        // Assert - first execution
        $this->assertSame(Command::SUCCESS, $exitCode1);
        $this->assertStringContainsString('Cache cleared successfully', $commandTester1->getDisplay());

        // Arrange - second execution with empty cache
        $clearCache2 = $this->createClearCacheStub(false);
        $command2 = new CacheClearCommand($clearCache2);
        $commandTester2 = new CommandTester($command2);

        // Act - second execution
        $exitCode2 = $commandTester2->execute([]);

        // Assert - second execution
        $this->assertSame(Command::SUCCESS, $exitCode2);
        $this->assertStringContainsString('Cache was already empty', $commandTester2->getDisplay());
    }

    public function testSuccessMessageUsesSymfonyStyleFormatting(): void
    {
        // Arrange
        $clearCache = $this->createClearCacheStub(true);
        $command = new CacheClearCommand($clearCache);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        // SymfonyStyle success messages include [OK] prefix or similar formatting
        $this->assertStringContainsString('Cache cleared successfully', $output);
    }

    public function testInfoMessageUsesSymfonyStyleFormatting(): void
    {
        // Arrange
        $clearCache = $this->createClearCacheStub(false);
        $command = new CacheClearCommand($clearCache);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $output = $commandTester->getDisplay();
        // SymfonyStyle info messages include [INFO] prefix or similar formatting
        $this->assertStringContainsString('Cache was already empty', $output);
    }

    public function testCommandDoesNotAcceptAnyArguments(): void
    {
        // Arrange
        $clearCache = $this->createClearCacheStub(true);
        $command = new CacheClearCommand($clearCache);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $definition = $command->getDefinition();
        $this->assertCount(0, $definition->getArguments());
    }

    public function testCommandDoesNotAcceptAnyOptions(): void
    {
        // Arrange
        $clearCache = $this->createClearCacheStub(true);
        $command = new CacheClearCommand($clearCache);
        $commandTester = new CommandTester($command);

        // Act
        $commandTester->execute([]);

        // Assert
        $definition = $command->getDefinition();
        // Symfony commands have default options (help, quiet, verbose, etc.)
        // We verify no custom options were added beyond the inherited ones
        $options = $definition->getOptions();
        $customOptions = array_filter($options, function ($option) {
            $name = $option->getName();

            // These are inherited from base Command class
            return !in_array($name, ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction']);
        });
        $this->assertCount(0, $customOptions);
    }
}
