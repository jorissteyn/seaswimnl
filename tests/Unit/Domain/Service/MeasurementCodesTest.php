<?php

declare(strict_types=1);

namespace Seaswim\Tests\Unit\Domain\Service;

use PHPUnit\Framework\TestCase;
use Seaswim\Domain\Service\MeasurementCodes;

final class MeasurementCodesTest extends TestCase
{
    // ==================== getCompartimenten() Tests ====================

    public function testGetCompartimentenReturnsArray(): void
    {
        $compartimenten = MeasurementCodes::getCompartimenten();

        $this->assertIsArray($compartimenten);
        $this->assertNotEmpty($compartimenten);
    }

    public function testGetCompartimentenHasExpectedKeys(): void
    {
        $compartimenten = MeasurementCodes::getCompartimenten();

        // Verify expected compartiment codes exist
        $this->assertArrayHasKey('OW', $compartimenten);
        $this->assertArrayHasKey('LT', $compartimenten);
        $this->assertArrayHasKey('BS', $compartimenten);
        $this->assertArrayHasKey('ZS', $compartimenten);
        $this->assertArrayHasKey('OE', $compartimenten);
        $this->assertArrayHasKey('OR', $compartimenten);
        $this->assertArrayHasKey('NVT', $compartimenten);
        $this->assertArrayHasKey('NT', $compartimenten);
        $this->assertArrayHasKey('PM', $compartimenten);
    }

    public function testGetCompartimentenItemsHaveCorrectStructure(): void
    {
        $compartimenten = MeasurementCodes::getCompartimenten();

        foreach ($compartimenten as $code => $data) {
            $this->assertIsString($code, 'Code should be a string');
            $this->assertIsArray($data, 'Data should be an array');
            $this->assertArrayHasKey('dutch', $data, "Missing 'dutch' key for code: {$code}");
            $this->assertArrayHasKey('english', $data, "Missing 'english' key for code: {$code}");
            $this->assertArrayHasKey('description', $data, "Missing 'description' key for code: {$code}");
            $this->assertIsString($data['dutch'], "Dutch translation should be a string for code: {$code}");
            $this->assertIsString($data['english'], "English translation should be a string for code: {$code}");
            $this->assertIsString($data['description'], "Description should be a string for code: {$code}");
            $this->assertNotEmpty($data['dutch'], "Dutch translation should not be empty for code: {$code}");
            $this->assertNotEmpty($data['english'], "English translation should not be empty for code: {$code}");
            $this->assertNotEmpty($data['description'], "Description should not be empty for code: {$code}");
        }
    }

    public function testGetCompartimentenOppervlaktewaterData(): void
    {
        $compartimenten = MeasurementCodes::getCompartimenten();
        $ow = $compartimenten['OW'];

        $this->assertSame('Oppervlaktewater', $ow['dutch']);
        $this->assertSame('Surface water', $ow['english']);
        $this->assertSame('Water in rivers, lakes, seas, canals', $ow['description']);
    }

    // ==================== getGrootheden() Tests ====================

    public function testGetGrootthedenReturnsArray(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();

        $this->assertIsArray($grootheden);
        $this->assertNotEmpty($grootheden);
    }

    public function testGetGrootthedenHasExpectedKeys(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();

        // Verify expected grootheid codes exist (sample from different categories)
        $this->assertArrayHasKey('WATHTE', $grootheden);
        $this->assertArrayHasKey('Hm0', $grootheden);
        $this->assertArrayHasKey('T', $grootheden);
        $this->assertArrayHasKey('WINDSHD', $grootheden);
        $this->assertArrayHasKey('STROOMSHD', $grootheden);
        $this->assertArrayHasKey('SALNTT', $grootheden);
        $this->assertArrayHasKey('LUCHTDK', $grootheden);
        $this->assertArrayHasKey('NVT', $grootheden);
    }

    public function testGetGrootthedenItemsHaveCorrectStructure(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();

        foreach ($grootheden as $code => $data) {
            $this->assertIsString($code, 'Code should be a string');
            $this->assertIsArray($data, 'Data should be an array');
            $this->assertArrayHasKey('dutch', $data, "Missing 'dutch' key for code: {$code}");
            $this->assertArrayHasKey('english', $data, "Missing 'english' key for code: {$code}");
            $this->assertArrayHasKey('unit', $data, "Missing 'unit' key for code: {$code}");
            $this->assertArrayHasKey('description', $data, "Missing 'description' key for code: {$code}");
            $this->assertArrayHasKey('category', $data, "Missing 'category' key for code: {$code}");

            $this->assertIsString($data['dutch'], "Dutch translation should be a string for code: {$code}");
            $this->assertIsString($data['english'], "English translation should be a string for code: {$code}");
            $this->assertTrue(
                is_string($data['unit']) || null === $data['unit'],
                "Unit should be string or null for code: {$code}"
            );
            $this->assertIsString($data['description'], "Description should be a string for code: {$code}");
            $this->assertIsString($data['category'], "Category should be a string for code: {$code}");

            $this->assertNotEmpty($data['dutch'], "Dutch translation should not be empty for code: {$code}");
            $this->assertNotEmpty($data['english'], "English translation should not be empty for code: {$code}");
            $this->assertNotEmpty($data['description'], "Description should not be empty for code: {$code}");
            $this->assertNotEmpty($data['category'], "Category should not be empty for code: {$code}");
        }
    }

    public function testGetGrootthedenWaterHeightData(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();
        $wathte = $grootheden['WATHTE'];

        $this->assertSame('Waterhoogte', $wathte['dutch']);
        $this->assertSame('Water height', $wathte['english']);
        $this->assertSame('cm', $wathte['unit']);
        $this->assertSame('Water level relative to NAP', $wathte['description']);
        $this->assertSame('water_level', $wathte['category']);
    }

    public function testGetGrootthedenHandlesNullUnits(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();

        // pH has null unit
        $this->assertArrayHasKey('pH', $grootheden);
        $this->assertNull($grootheden['pH']['unit']);

        // NVT has null unit
        $this->assertArrayHasKey('NVT', $grootheden);
        $this->assertNull($grootheden['NVT']['unit']);

        // HHT has null unit
        $this->assertArrayHasKey('HHT', $grootheden);
        $this->assertNull($grootheden['HHT']['unit']);
    }

    public function testGetGrootthedenCategories(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();
        $categories = array_unique(array_column($grootheden, 'category'));

        // Verify all expected categories exist
        $expectedCategories = [
            'water_level',
            'waves',
            'temperature',
            'wind',
            'current',
            'water_quality',
            'atmospheric',
            'dimensions',
            'other',
        ];

        foreach ($expectedCategories as $category) {
            $this->assertContains($category, $categories, "Expected category '{$category}' not found");
        }
    }

    public function testGetGrootthedenWavesCategory(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();
        $waveMeasurements = array_filter($grootheden, fn ($data) => 'waves' === $data['category']);

        $this->assertNotEmpty($waveMeasurements);
        $this->assertArrayHasKey('Hm0', $waveMeasurements);
        $this->assertArrayHasKey('Hmax', $waveMeasurements);
        $this->assertArrayHasKey('Tm02', $waveMeasurements);
    }

    public function testGetGrootthedenWindCategory(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();
        $windMeasurements = array_filter($grootheden, fn ($data) => 'wind' === $data['category']);

        $this->assertNotEmpty($windMeasurements);
        $this->assertArrayHasKey('WINDSHD', $windMeasurements);
        $this->assertArrayHasKey('WINDRTG', $windMeasurements);
        $this->assertArrayHasKey('WINDST', $windMeasurements);
    }

    // ==================== getCompartiment() Tests ====================

    public function testGetCompartimentReturnsDataForValidCode(): void
    {
        $result = MeasurementCodes::getCompartiment('OW');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dutch', $result);
        $this->assertArrayHasKey('english', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertSame('Oppervlaktewater', $result['dutch']);
        $this->assertSame('Surface water', $result['english']);
    }

    public function testGetCompartimentReturnsNullForInvalidCode(): void
    {
        $result = MeasurementCodes::getCompartiment('INVALID');

        $this->assertNull($result);
    }

    public function testGetCompartimentReturnsNullForEmptyString(): void
    {
        $result = MeasurementCodes::getCompartiment('');

        $this->assertNull($result);
    }

    public function testGetCompartimentHandlesAllValidCodes(): void
    {
        $compartimenten = MeasurementCodes::getCompartimenten();

        foreach (array_keys($compartimenten) as $code) {
            $result = MeasurementCodes::getCompartiment($code);

            $this->assertIsArray($result, "Failed to get compartiment for code: {$code}");
            $this->assertSame($compartimenten[$code], $result, "Data mismatch for code: {$code}");
        }
    }

    public function testGetCompartimentIsCaseSensitive(): void
    {
        $resultUppercase = MeasurementCodes::getCompartiment('OW');
        $resultLowercase = MeasurementCodes::getCompartiment('ow');

        $this->assertIsArray($resultUppercase);
        $this->assertNull($resultLowercase);
    }

    // ==================== getGrootheid() Tests ====================

    public function testGetGrootheidReturnsDataForValidCode(): void
    {
        $result = MeasurementCodes::getGrootheid('WATHTE');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dutch', $result);
        $this->assertArrayHasKey('english', $result);
        $this->assertArrayHasKey('unit', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('category', $result);
        $this->assertSame('Waterhoogte', $result['dutch']);
        $this->assertSame('Water height', $result['english']);
        $this->assertSame('cm', $result['unit']);
        $this->assertSame('water_level', $result['category']);
    }

    public function testGetGrootheidReturnsNullForInvalidCode(): void
    {
        $result = MeasurementCodes::getGrootheid('INVALID');

        $this->assertNull($result);
    }

    public function testGetGrootheidReturnsNullForEmptyString(): void
    {
        $result = MeasurementCodes::getGrootheid('');

        $this->assertNull($result);
    }

    public function testGetGrootheidHandlesAllValidCodes(): void
    {
        $grootheden = MeasurementCodes::getGrootheden();

        foreach (array_keys($grootheden) as $code) {
            $result = MeasurementCodes::getGrootheid($code);

            $this->assertIsArray($result, "Failed to get grootheid for code: {$code}");
            $this->assertSame($grootheden[$code], $result, "Data mismatch for code: {$code}");
        }
    }

    public function testGetGrootheidIsCaseSensitive(): void
    {
        $resultUppercase = MeasurementCodes::getGrootheid('WATHTE');
        $resultLowercase = MeasurementCodes::getGrootheid('wathte');

        $this->assertIsArray($resultUppercase);
        $this->assertNull($resultLowercase);
    }

    public function testGetGrootheidHandlesCodesWithSpecialCharacters(): void
    {
        // Test codes with slashes
        $result1 = MeasurementCodes::getGrootheid('H1/3');
        $this->assertIsArray($result1);
        $this->assertSame('Significant wave height', $result1['english']);

        $result2 = MeasurementCodes::getGrootheid('H1/10');
        $this->assertIsArray($result2);
        $this->assertSame('Wave height 1/10', $result2['english']);

        // Test codes with underscores
        $result3 = MeasurementCodes::getGrootheid('T_Hmax');
        $this->assertIsArray($result3);
        $this->assertSame('Period at Hmax', $result3['english']);
    }

    public function testGetGrootheidReturnsNullUnit(): void
    {
        $result = MeasurementCodes::getGrootheid('pH');

        $this->assertIsArray($result);
        $this->assertNull($result['unit']);
        $this->assertSame('pH value', $result['english']);
    }

    // ==================== getCategories() Tests ====================

    public function testGetCategoriesReturnsArray(): void
    {
        $categories = MeasurementCodes::getCategories();

        $this->assertIsArray($categories);
        $this->assertNotEmpty($categories);
    }

    public function testGetCategoriesHasExpectedKeys(): void
    {
        $categories = MeasurementCodes::getCategories();

        $expectedKeys = [
            'water_level',
            'waves',
            'temperature',
            'wind',
            'current',
            'water_quality',
            'atmospheric',
            'dimensions',
            'other',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $categories, "Missing category key: {$key}");
        }
    }

    public function testGetCategoriesHasCorrectValues(): void
    {
        $categories = MeasurementCodes::getCategories();

        $this->assertSame('Water Level & Tides', $categories['water_level']);
        $this->assertSame('Waves', $categories['waves']);
        $this->assertSame('Temperature', $categories['temperature']);
        $this->assertSame('Wind', $categories['wind']);
        $this->assertSame('Current & Flow', $categories['current']);
        $this->assertSame('Water Quality', $categories['water_quality']);
        $this->assertSame('Atmospheric', $categories['atmospheric']);
        $this->assertSame('Physical Dimensions', $categories['dimensions']);
        $this->assertSame('Other', $categories['other']);
    }

    public function testGetCategoriesValuesAreStrings(): void
    {
        $categories = MeasurementCodes::getCategories();

        foreach ($categories as $key => $value) {
            $this->assertIsString($key, 'Category key should be string');
            $this->assertIsString($value, 'Category value should be string');
            $this->assertNotEmpty($value, "Category value should not be empty for key: {$key}");
        }
    }

    public function testGetCategoriesMatchesGrootthedenCategories(): void
    {
        $categories = MeasurementCodes::getCategories();
        $grootheden = MeasurementCodes::getGrootheden();

        // Get all unique categories used in grootheden
        $usedCategories = array_unique(array_column($grootheden, 'category'));

        // All used categories should exist in the categories list
        foreach ($usedCategories as $usedCategory) {
            $this->assertArrayHasKey(
                $usedCategory,
                $categories,
                "Category '{$usedCategory}' used in grootheden but not defined in getCategories()"
            );
        }
    }

    // ==================== Integration Tests ====================

    public function testCompartimentenAndGrootthedenAreSeparateDataSets(): void
    {
        $compartimenten = MeasurementCodes::getCompartimenten();
        $grootheden = MeasurementCodes::getGrootheden();

        // Both should have 'NVT' code but with different structures
        $this->assertArrayHasKey('NVT', $compartimenten);
        $this->assertArrayHasKey('NVT', $grootheden);

        // Compartiment NVT has 3 keys
        $this->assertCount(3, $compartimenten['NVT']);
        $this->assertArrayNotHasKey('unit', $compartimenten['NVT']);
        $this->assertArrayNotHasKey('category', $compartimenten['NVT']);

        // Grootheid NVT has 5 keys
        $this->assertCount(5, $grootheden['NVT']);
        $this->assertArrayHasKey('unit', $grootheden['NVT']);
        $this->assertArrayHasKey('category', $grootheden['NVT']);
    }

    public function testDataConsistencyAcrossMethods(): void
    {
        // getCompartiment should return same data as getCompartimenten
        $allCompartimenten = MeasurementCodes::getCompartimenten();

        foreach (array_keys($allCompartimenten) as $code) {
            $single = MeasurementCodes::getCompartiment($code);
            $fromList = $allCompartimenten[$code];

            $this->assertSame($fromList, $single, "Data mismatch for compartiment code: {$code}");
        }

        // getGrootheid should return same data as getGrootheden
        $allGrootheden = MeasurementCodes::getGrootheden();

        foreach (array_keys($allGrootheden) as $code) {
            $single = MeasurementCodes::getGrootheid($code);
            $fromList = $allGrootheden[$code];

            $this->assertSame($fromList, $single, "Data mismatch for grootheid code: {$code}");
        }
    }

    public function testStaticMethodsReturnConsistentData(): void
    {
        // Multiple calls should return identical data
        $compartimenten1 = MeasurementCodes::getCompartimenten();
        $compartimenten2 = MeasurementCodes::getCompartimenten();

        $this->assertSame($compartimenten1, $compartimenten2);

        $grootheden1 = MeasurementCodes::getGrootheden();
        $grootheden2 = MeasurementCodes::getGrootheden();

        $this->assertSame($grootheden1, $grootheden2);

        $categories1 = MeasurementCodes::getCategories();
        $categories2 = MeasurementCodes::getCategories();

        $this->assertSame($categories1, $categories2);
    }

    // ==================== Edge Cases and Boundary Tests ====================

    public function testGetCompartimentWithWhitespace(): void
    {
        $result = MeasurementCodes::getCompartiment(' OW ');

        $this->assertNull($result, 'Should not match codes with leading/trailing whitespace');
    }

    public function testGetGrootheidWithWhitespace(): void
    {
        $result = MeasurementCodes::getGrootheid(' WATHTE ');

        $this->assertNull($result, 'Should not match codes with leading/trailing whitespace');
    }

    public function testGetCompartimentWithNumericString(): void
    {
        $result = MeasurementCodes::getCompartiment('123');

        $this->assertNull($result);
    }

    public function testGetGrootheidWithNumericString(): void
    {
        $result = MeasurementCodes::getGrootheid('123');

        $this->assertNull($result);
    }

    public function testDataIntegrityNoEmptyStrings(): void
    {
        $compartimenten = MeasurementCodes::getCompartimenten();
        $grootheden = MeasurementCodes::getGrootheden();

        // Check compartimenten have no empty strings
        foreach ($compartimenten as $code => $data) {
            $this->assertNotEmpty($code, 'Found empty compartiment code');
            $this->assertNotEmpty($data['dutch'], "Found empty dutch for {$code}");
            $this->assertNotEmpty($data['english'], "Found empty english for {$code}");
            $this->assertNotEmpty($data['description'], "Found empty description for {$code}");
        }

        // Check grootheden have no empty strings (except null units are allowed)
        foreach ($grootheden as $code => $data) {
            $this->assertNotEmpty($code, 'Found empty grootheid code');
            $this->assertNotEmpty($data['dutch'], "Found empty dutch for {$code}");
            $this->assertNotEmpty($data['english'], "Found empty english for {$code}");
            $this->assertNotEmpty($data['description'], "Found empty description for {$code}");
            $this->assertNotEmpty($data['category'], "Found empty category for {$code}");
        }
    }
}
