<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Infrastructure\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Infrastructure\Service\LocationBlacklist;

final class LocationBlacklistTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/seaswim_test_'.uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir.'/data', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
    }

    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeTempDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createBlacklistFile(string $content): void
    {
        file_put_contents($this->tempDir.'/data/blacklist.txt', $content);
    }

    public function testConstructorWithNonExistentFile(): void
    {
        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertFalse($blacklist->isBlacklisted('any.location'));
        $this->assertEmpty($blacklist->getAll());
    }

    public function testConstructorWithEmptyFile(): void
    {
        $this->createBlacklistFile('');

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertFalse($blacklist->isBlacklisted('any.location'));
        $this->assertEmpty($blacklist->getAll());
    }

    public function testConstructorLoadsSingleLocation(): void
    {
        $this->createBlacklistFile('location.test');

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.test'));
        $this->assertSame(['location.test'], $blacklist->getAll());
    }

    public function testConstructorLoadsMultipleLocations(): void
    {
        $this->createBlacklistFile(
            "location.one\nlocation.two\nlocation.three"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.one'));
        $this->assertTrue($blacklist->isBlacklisted('location.two'));
        $this->assertTrue($blacklist->isBlacklisted('location.three'));
        $this->assertSame(['location.one', 'location.two', 'location.three'], $blacklist->getAll());
    }

    public function testConstructorSkipsCommentLines(): void
    {
        $this->createBlacklistFile(
            "# This is a comment\nlocation.valid\n# Another comment"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.valid'));
        $this->assertFalse($blacklist->isBlacklisted('# This is a comment'));
        $this->assertFalse($blacklist->isBlacklisted('# Another comment'));
        $this->assertSame(['location.valid'], $blacklist->getAll());
    }

    public function testConstructorSkipsEmptyLines(): void
    {
        $this->createBlacklistFile(
            "location.one\n\n\nlocation.two\n\n"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.one'));
        $this->assertTrue($blacklist->isBlacklisted('location.two'));
        $this->assertSame(['location.one', 'location.two'], $blacklist->getAll());
    }

    public function testConstructorTrimsWhitespace(): void
    {
        $this->createBlacklistFile(
            "  location.with.leading.spaces\nlocation.with.trailing.spaces  \n  location.with.both  "
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.with.leading.spaces'));
        $this->assertTrue($blacklist->isBlacklisted('location.with.trailing.spaces'));
        $this->assertTrue($blacklist->isBlacklisted('location.with.both'));
        $this->assertSame([
            'location.with.leading.spaces',
            'location.with.trailing.spaces',
            'location.with.both',
        ], $blacklist->getAll());
    }

    public function testConstructorSkipsWhitespaceOnlyLines(): void
    {
        $this->createBlacklistFile(
            "location.one\n   \n\t\t\nlocation.two"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.one'));
        $this->assertTrue($blacklist->isBlacklisted('location.two'));
        $this->assertSame(['location.one', 'location.two'], $blacklist->getAll());
    }

    public function testConstructorHandlesCommentAfterWhitespace(): void
    {
        $this->createBlacklistFile(
            "location.one\n   # Indented comment\nlocation.two"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.one'));
        $this->assertTrue($blacklist->isBlacklisted('location.two'));
        $this->assertFalse($blacklist->isBlacklisted('# Indented comment'));
        $this->assertSame(['location.one', 'location.two'], $blacklist->getAll());
    }

    public function testConstructorHandlesRealWorldBlacklistFormat(): void
    {
        $this->createBlacklistFile(
            "# Blacklisted RWS locations (stale or no data)\n".
            "# Generated by seaswim:locations:scan-stale on 2025-12-26 16:56:49\n".
            "#\n".
            "# These locations return outdated data from the RWS API.\n".
            "# They are excluded from the location selector.\n".
            "\n".
            "4epetroleumhaven\n".
            "7epetroleumhaven\n".
            'aa.helmond'
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('4epetroleumhaven'));
        $this->assertTrue($blacklist->isBlacklisted('7epetroleumhaven'));
        $this->assertTrue($blacklist->isBlacklisted('aa.helmond'));
        $this->assertFalse($blacklist->isBlacklisted('# Blacklisted RWS locations (stale or no data)'));
        $this->assertSame(['4epetroleumhaven', '7epetroleumhaven', 'aa.helmond'], $blacklist->getAll());
    }

    public function testIsBlacklistedReturnsTrueForBlacklistedLocation(): void
    {
        $this->createBlacklistFile('blacklisted.location');

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('blacklisted.location'));
    }

    public function testIsBlacklistedReturnsFalseForNonBlacklistedLocation(): void
    {
        $this->createBlacklistFile('blacklisted.location');

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertFalse($blacklist->isBlacklisted('other.location'));
        $this->assertFalse($blacklist->isBlacklisted('completely.different'));
    }

    public function testIsBlacklistedReturnsFalseWhenFileDoesNotExist(): void
    {
        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertFalse($blacklist->isBlacklisted('any.location'));
    }

    public function testIsBlacklistedIsCaseSensitive(): void
    {
        $this->createBlacklistFile('lowercase.location');

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('lowercase.location'));
        $this->assertFalse($blacklist->isBlacklisted('LOWERCASE.LOCATION'));
        $this->assertFalse($blacklist->isBlacklisted('Lowercase.Location'));
    }

    public function testGetAllReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertSame([], $blacklist->getAll());
    }

    public function testGetAllReturnsEmptyArrayWhenFileIsEmpty(): void
    {
        $this->createBlacklistFile('');

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertSame([], $blacklist->getAll());
    }

    public function testGetAllReturnsAllBlacklistedLocations(): void
    {
        $this->createBlacklistFile(
            "location.alpha\nlocation.beta\nlocation.gamma"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertSame([
            'location.alpha',
            'location.beta',
            'location.gamma',
        ], $blacklist->getAll());
    }

    public function testGetAllReturnsLocationsInOrderFromFile(): void
    {
        $this->createBlacklistFile(
            "zebra.location\nalpha.location\nmiddle.location"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        // Should maintain the order from the file
        $this->assertSame([
            'zebra.location',
            'alpha.location',
            'middle.location',
        ], $blacklist->getAll());
    }

    public function testGetAllExcludesCommentsAndEmptyLines(): void
    {
        $this->createBlacklistFile(
            "# Header comment\n".
            "location.one\n".
            "\n".
            "# Middle comment\n".
            "location.two\n".
            "   \n".
            'location.three'
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertSame([
            'location.one',
            'location.two',
            'location.three',
        ], $blacklist->getAll());
    }

    public function testHandlesDuplicateLocations(): void
    {
        $this->createBlacklistFile(
            "duplicate.location\nduplicate.location\nother.location"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        // Should only include each unique location once (keys are unique)
        $this->assertTrue($blacklist->isBlacklisted('duplicate.location'));
        $this->assertTrue($blacklist->isBlacklisted('other.location'));

        $all = $blacklist->getAll();
        $this->assertContains('duplicate.location', $all);
        $this->assertContains('other.location', $all);
    }

    public function testHandlesSpecialCharactersInLocationIds(): void
    {
        $this->createBlacklistFile(
            "location-with-dashes\nlocation.with.dots\nlocation_with_underscores\nlocation123"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location-with-dashes'));
        $this->assertTrue($blacklist->isBlacklisted('location.with.dots'));
        $this->assertTrue($blacklist->isBlacklisted('location_with_underscores'));
        $this->assertTrue($blacklist->isBlacklisted('location123'));
    }

    public function testHandlesLargeBlacklistFile(): void
    {
        // Create a file with 100 locations
        $locations = [];
        for ($i = 0; $i < 100; ++$i) {
            $locations[] = "location.{$i}";
        }
        $this->createBlacklistFile(implode("\n", $locations));

        $blacklist = new LocationBlacklist($this->tempDir);

        // Check first, middle, and last
        $this->assertTrue($blacklist->isBlacklisted('location.0'));
        $this->assertTrue($blacklist->isBlacklisted('location.50'));
        $this->assertTrue($blacklist->isBlacklisted('location.99'));
        $this->assertFalse($blacklist->isBlacklisted('location.100'));
        $this->assertCount(100, $blacklist->getAll());
    }

    public function testConstructorHandlesUnreadableFile(): void
    {
        $blacklistPath = $this->tempDir.'/data/blacklist.txt';

        // Create the file
        file_put_contents($blacklistPath, 'location.test');

        // Make the file unreadable by changing permissions
        chmod($blacklistPath, 0000);

        // This should not throw an exception, just return empty results
        // because file() returns false when it cannot read the file
        // Using @ to suppress the expected warning from file()
        $blacklist = @new LocationBlacklist($this->tempDir);

        $this->assertFalse($blacklist->isBlacklisted('location.test'));
        $this->assertEmpty($blacklist->getAll());

        // Restore permissions for cleanup
        chmod($blacklistPath, 0644);
    }

    public function testHandlesMixedLineEndings(): void
    {
        // Test with different line ending styles
        // Note: file() with FILE_IGNORE_NEW_LINES handles \n, \r\n automatically
        $this->createBlacklistFile(
            "location.unix\n".
            "location.windows\r\n".
            'location.mixed'
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.unix'));
        $this->assertTrue($blacklist->isBlacklisted('location.windows'));
        $this->assertTrue($blacklist->isBlacklisted('location.mixed'));
    }

    public function testPerformanceOfIsBlacklistedLookup(): void
    {
        // Create a large blacklist to verify O(1) lookup time
        $locations = [];
        for ($i = 0; $i < 1000; ++$i) {
            $locations[] = "location.{$i}";
        }
        $this->createBlacklistFile(implode("\n", $locations));

        $blacklist = new LocationBlacklist($this->tempDir);

        // Multiple lookups should be fast (using isset() internally)
        $startTime = microtime(true);
        for ($i = 0; $i < 1000; ++$i) {
            $blacklist->isBlacklisted("location.{$i}");
        }
        $elapsed = microtime(true) - $startTime;

        // Should complete in under 0.1 seconds even with 1000 locations and 1000 lookups
        $this->assertLessThan(0.1, $elapsed, 'Lookup performance should be O(1)');
    }

    public function testHandlesUtf8CharactersInLocationIds(): void
    {
        $this->createBlacklistFile(
            "location.caf\u{00E9}\nlocation.\u{20AC}\nlocation.\u{1F600}"
        );

        $blacklist = new LocationBlacklist($this->tempDir);

        $this->assertTrue($blacklist->isBlacklisted('location.café'));
        $this->assertTrue($blacklist->isBlacklisted('location.€'));
        $this->assertTrue($blacklist->isBlacklisted('location.😀'));
    }
}
