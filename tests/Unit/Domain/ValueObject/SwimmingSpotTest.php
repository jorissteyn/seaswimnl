<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\ValueObject\SwimmingSpot;

final class SwimmingSpotTest extends TestCase
{
    public function testConstruction(): void
    {
        $spot = new SwimmingSpot(
            'scheveningen-bad',
            'Scheveningen Bad',
            52.1048,
            4.2759,
        );

        $this->assertSame('scheveningen-bad', $spot->getId());
        $this->assertSame('Scheveningen Bad', $spot->getName());
        $this->assertSame(52.1048, $spot->getLatitude());
        $this->assertSame(4.2759, $spot->getLongitude());
    }

    public function testConstructionWithNegativeCoordinates(): void
    {
        $spot = new SwimmingSpot(
            'south-west-location',
            'South West Location',
            -33.8688,
            -151.2093,
        );

        $this->assertSame('south-west-location', $spot->getId());
        $this->assertSame('South West Location', $spot->getName());
        $this->assertSame(-33.8688, $spot->getLatitude());
        $this->assertSame(-151.2093, $spot->getLongitude());
    }

    public function testConstructionWithZeroCoordinates(): void
    {
        $spot = new SwimmingSpot(
            'equator-prime-meridian',
            'Equator & Prime Meridian',
            0.0,
            0.0,
        );

        $this->assertSame('equator-prime-meridian', $spot->getId());
        $this->assertSame('Equator & Prime Meridian', $spot->getName());
        $this->assertSame(0.0, $spot->getLatitude());
        $this->assertSame(0.0, $spot->getLongitude());
    }

    public function testSlugifyWithSimpleName(): void
    {
        $slug = SwimmingSpot::slugify('Scheveningen');

        $this->assertSame('scheveningen', $slug);
    }

    public function testSlugifyWithSpaces(): void
    {
        $slug = SwimmingSpot::slugify('Hoek van Holland');

        $this->assertSame('hoek-van-holland', $slug);
    }

    public function testSlugifyWithSpecialCharacters(): void
    {
        $slug = SwimmingSpot::slugify('Strand & Zwembad #1');

        $this->assertSame('strand-zwembad-1', $slug);
    }

    public function testSlugifyWithMultipleSpaces(): void
    {
        $slug = SwimmingSpot::slugify('Beach   with    spaces');

        $this->assertSame('beach-with-spaces', $slug);
    }

    public function testSlugifyWithLeadingAndTrailingSpaces(): void
    {
        $slug = SwimmingSpot::slugify('  Trimmed Beach  ');

        $this->assertSame('trimmed-beach', $slug);
    }

    public function testSlugifyWithLeadingAndTrailingSpecialChars(): void
    {
        $slug = SwimmingSpot::slugify('---Beach!!!');

        $this->assertSame('beach', $slug);
    }

    public function testSlugifyWithUppercaseLetters(): void
    {
        $slug = SwimmingSpot::slugify('LOUDLY NAMED BEACH');

        $this->assertSame('loudly-named-beach', $slug);
    }

    public function testSlugifyWithMixedCase(): void
    {
        $slug = SwimmingSpot::slugify('CamelCase BeachName');

        $this->assertSame('camelcase-beachname', $slug);
    }

    public function testSlugifyWithDiacritics(): void
    {
        $slug = SwimmingSpot::slugify('Café aan Zee');

        $this->assertSame('caf-aan-zee', $slug);
    }

    public function testSlugifyWithNumbers(): void
    {
        $slug = SwimmingSpot::slugify('Beach 123');

        $this->assertSame('beach-123', $slug);
    }

    public function testSlugifyWithUnderscores(): void
    {
        $slug = SwimmingSpot::slugify('beach_with_underscores');

        $this->assertSame('beach-with-underscores', $slug);
    }

    public function testSlugifyWithDots(): void
    {
        $slug = SwimmingSpot::slugify('St. Nicholas Beach');

        $this->assertSame('st-nicholas-beach', $slug);
    }

    public function testSlugifyWithEmptyString(): void
    {
        $slug = SwimmingSpot::slugify('');

        $this->assertSame('', $slug);
    }

    public function testSlugifyWithOnlySpecialCharacters(): void
    {
        $slug = SwimmingSpot::slugify('!@#$%^&*()');

        $this->assertSame('', $slug);
    }

    public function testFromCsvRow(): void
    {
        $row = [
            'name' => 'Zandvoort aan Zee',
            'latitude' => '52.3738',
            'longitude' => '4.5323',
        ];

        $spot = SwimmingSpot::fromCsvRow($row);

        $this->assertSame('zandvoort-aan-zee', $spot->getId());
        $this->assertSame('Zandvoort aan Zee', $spot->getName());
        $this->assertSame(52.3738, $spot->getLatitude());
        $this->assertSame(4.5323, $spot->getLongitude());
    }

    public function testFromCsvRowWithNegativeCoordinates(): void
    {
        $row = [
            'name' => 'Southern Beach',
            'latitude' => '-34.0',
            'longitude' => '-118.5',
        ];

        $spot = SwimmingSpot::fromCsvRow($row);

        $this->assertSame('southern-beach', $spot->getId());
        $this->assertSame('Southern Beach', $spot->getName());
        $this->assertSame(-34.0, $spot->getLatitude());
        $this->assertSame(-118.5, $spot->getLongitude());
    }

    public function testFromCsvRowWithIntegerCoordinates(): void
    {
        $row = [
            'name' => 'Simple Beach',
            'latitude' => '52',
            'longitude' => '4',
        ];

        $spot = SwimmingSpot::fromCsvRow($row);

        $this->assertSame('simple-beach', $spot->getId());
        $this->assertSame('Simple Beach', $spot->getName());
        $this->assertSame(52.0, $spot->getLatitude());
        $this->assertSame(4.0, $spot->getLongitude());
    }

    public function testFromCsvRowWithZeroCoordinates(): void
    {
        $row = [
            'name' => 'Zero Point',
            'latitude' => '0.0',
            'longitude' => '0.0',
        ];

        $spot = SwimmingSpot::fromCsvRow($row);

        $this->assertSame('zero-point', $spot->getId());
        $this->assertSame('Zero Point', $spot->getName());
        $this->assertSame(0.0, $spot->getLatitude());
        $this->assertSame(0.0, $spot->getLongitude());
    }

    public function testFromCsvRowGeneratesSlugAsId(): void
    {
        $row = [
            'name' => 'Beach With SPECIAL @#$ Characters!',
            'latitude' => '51.5',
            'longitude' => '3.5',
        ];

        $spot = SwimmingSpot::fromCsvRow($row);

        $this->assertSame('beach-with-special-characters', $spot->getId());
        $this->assertSame('Beach With SPECIAL @#$ Characters!', $spot->getName());
    }

    public function testFromCsvRowWithHighPrecisionCoordinates(): void
    {
        $row = [
            'name' => 'Precise Location',
            'latitude' => '52.123456789',
            'longitude' => '4.987654321',
        ];

        $spot = SwimmingSpot::fromCsvRow($row);

        $this->assertSame('precise-location', $spot->getId());
        $this->assertSame('Precise Location', $spot->getName());
        $this->assertSame(52.123456789, $spot->getLatitude());
        $this->assertSame(4.987654321, $spot->getLongitude());
    }

    public function testGetIdReturnsCorrectValue(): void
    {
        $spot = new SwimmingSpot(
            'test-id',
            'Test Name',
            50.0,
            5.0,
        );

        $this->assertSame('test-id', $spot->getId());
    }

    public function testGetNameReturnsCorrectValue(): void
    {
        $spot = new SwimmingSpot(
            'test-id',
            'Test Beach Name',
            50.0,
            5.0,
        );

        $this->assertSame('Test Beach Name', $spot->getName());
    }

    public function testGetLatitudeReturnsCorrectValue(): void
    {
        $spot = new SwimmingSpot(
            'test-id',
            'Test Name',
            52.1234,
            5.0,
        );

        $this->assertSame(52.1234, $spot->getLatitude());
    }

    public function testGetLongitudeReturnsCorrectValue(): void
    {
        $spot = new SwimmingSpot(
            'test-id',
            'Test Name',
            50.0,
            4.5678,
        );

        $this->assertSame(4.5678, $spot->getLongitude());
    }
}
