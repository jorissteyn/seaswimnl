<?php

declare(strict_types=1);

namespace Seaswim\Domain\Service;

/**
 * Reference data for RWS/Aquo measurement codes.
 *
 * Provides descriptions for compartimenten (where) and grootheden (what)
 * codes used in the Rijkswaterstaat water data API.
 *
 * @see https://www.aquo.nl/
 */
final class MeasurementCodes
{
    /**
     * Compartimenten - where the measurement is taken.
     *
     * @return array<string, array{dutch: string, english: string, description: string}>
     */
    public static function getCompartimenten(): array
    {
        return [
            'OW' => [
                'dutch' => 'Oppervlaktewater',
                'english' => 'Surface water',
                'description' => 'Water in rivers, lakes, seas, canals',
            ],
            'LT' => [
                'dutch' => 'Lucht',
                'english' => 'Air',
                'description' => 'Atmospheric measurements',
            ],
            'BS' => [
                'dutch' => 'Bodem/Sediment',
                'english' => 'Soil/Sediment',
                'description' => 'Bottom sediment or soil',
            ],
            'ZS' => [
                'dutch' => 'Zwevende Stof',
                'english' => 'Suspended matter',
                'description' => 'Particles suspended in water',
            ],
            'OE' => [
                'dutch' => 'Oever',
                'english' => 'Shore/Bank',
                'description' => 'Riverbank or shore measurements',
            ],
            'OR' => [
                'dutch' => 'Organisme',
                'english' => 'Organism',
                'description' => 'Biota/organism samples',
            ],
            'NVT' => [
                'dutch' => 'Niet Van Toepassing',
                'english' => 'Not applicable',
                'description' => 'No compartment applies',
            ],
            'NT' => [
                'dutch' => 'Niet Te Bepalen',
                'english' => 'Not determined',
                'description' => 'Cannot be determined',
            ],
            'PM' => [
                'dutch' => 'Particulate Matter',
                'english' => 'Particulate matter',
                'description' => 'Air quality particles',
            ],
        ];
    }

    /**
     * Grootheden - what is being measured.
     *
     * @return array<string, array{dutch: string, english: string, unit: string|null, description: string, category: string}>
     */
    public static function getGrootheden(): array
    {
        return [
            // Water Level & Tides
            'WATHTE' => [
                'dutch' => 'Waterhoogte',
                'english' => 'Water height',
                'unit' => 'cm',
                'description' => 'Water level relative to NAP',
                'category' => 'water_level',
            ],
            'GGH' => [
                'dutch' => 'Gemiddeld Getij Hoogwater',
                'english' => 'Mean high water',
                'unit' => 'cm',
                'description' => 'Average high tide level',
                'category' => 'water_level',
            ],
            'GGT' => [
                'dutch' => 'Gemiddeld Getij',
                'english' => 'Mean tide',
                'unit' => 'cm',
                'description' => 'Average tide level',
                'category' => 'water_level',
            ],
            'HH' => [
                'dutch' => 'Hoogste Hoogwater',
                'english' => 'Highest high water',
                'unit' => 'cm',
                'description' => 'Maximum recorded high water',
                'category' => 'water_level',
            ],
            'HHT' => [
                'dutch' => 'Hoogste Hoogwater Tijd',
                'english' => 'Highest high water time',
                'unit' => null,
                'description' => 'Time of highest high water',
                'category' => 'water_level',
            ],
            'LG' => [
                'dutch' => 'Laagste Laagwater',
                'english' => 'Lowest low water',
                'unit' => 'cm',
                'description' => 'Minimum recorded low water',
                'category' => 'water_level',
            ],
            'NG' => [
                'dutch' => 'Normaal Getij',
                'english' => 'Normal tide',
                'unit' => 'cm',
                'description' => 'Normal tide level',
                'category' => 'water_level',
            ],
            'NGWTTL' => [
                'dutch' => 'Normaal Getij Waterstand',
                'english' => 'Normal tide water level',
                'unit' => 'cm',
                'description' => 'Normal tidal water level',
                'category' => 'water_level',
            ],
            'SLOTGHW' => [
                'dutch' => 'Slotgemiddeld Hoogwater',
                'english' => 'Final mean high water',
                'unit' => 'cm',
                'description' => 'Computed mean high water',
                'category' => 'water_level',
            ],
            'SLOTGLW' => [
                'dutch' => 'Slotgemiddeld Laagwater',
                'english' => 'Final mean low water',
                'unit' => 'cm',
                'description' => 'Computed mean low water',
                'category' => 'water_level',
            ],
            'SLOTGWAT' => [
                'dutch' => 'Slotgemiddeld Water',
                'english' => 'Final mean water',
                'unit' => 'cm',
                'description' => 'Computed mean water level',
                'category' => 'water_level',
            ],
            'SPGH' => [
                'dutch' => 'Spring Hoogwater',
                'english' => 'Spring high water',
                'unit' => 'cm',
                'description' => 'Spring tide high water',
                'category' => 'water_level',
            ],
            'SPGT' => [
                'dutch' => 'Spring Getij',
                'english' => 'Spring tide',
                'unit' => 'cm',
                'description' => 'Spring tide level',
                'category' => 'water_level',
            ],
            'HOOGWTDG' => [
                'dutch' => 'Hoogwater Dag',
                'english' => 'High water day',
                'unit' => null,
                'description' => 'Daily high water',
                'category' => 'water_level',
            ],
            'HOOGWTNT' => [
                'dutch' => 'Hoogwater Nacht',
                'english' => 'High water night',
                'unit' => null,
                'description' => 'Nightly high water',
                'category' => 'water_level',
            ],
            'LAAGWTDG' => [
                'dutch' => 'Laagwater Dag',
                'english' => 'Low water day',
                'unit' => null,
                'description' => 'Daily low water',
                'category' => 'water_level',
            ],
            'GETVVG' => [
                'dutch' => 'Getijverschuiving',
                'english' => 'Tidal shift',
                'unit' => 'min',
                'description' => 'Tidal time difference',
                'category' => 'water_level',
            ],

            // Waves
            'Hm0' => [
                'dutch' => 'Spectrale golfhoogte',
                'english' => 'Significant wave height',
                'unit' => 'cm',
                'description' => 'From energy spectrum 30-500 mHz (4x std dev)',
                'category' => 'waves',
            ],
            'Hmax' => [
                'dutch' => 'Maximale golfhoogte',
                'english' => 'Maximum wave height',
                'unit' => 'cm',
                'description' => 'Highest individual wave',
                'category' => 'waves',
            ],
            'H1/3' => [
                'dutch' => 'Significante golfhoogte',
                'english' => 'Significant wave height',
                'unit' => 'cm',
                'description' => 'Average of highest 1/3 of waves (~7% < Hm0)',
                'category' => 'waves',
            ],
            'H1/10' => [
                'dutch' => 'Golfhoogte 1/10',
                'english' => 'Wave height 1/10',
                'unit' => 'cm',
                'description' => 'Average of highest 1/10 of waves',
                'category' => 'waves',
            ],
            'H1/50' => [
                'dutch' => 'Golfhoogte 1/50',
                'english' => 'Wave height 1/50',
                'unit' => 'cm',
                'description' => 'Average of highest 1/50 of waves',
                'category' => 'waves',
            ],
            'GOLFHTE' => [
                'dutch' => 'Golfhoogte',
                'english' => 'Wave height',
                'unit' => 'cm',
                'description' => 'General wave height',
                'category' => 'waves',
            ],
            'HTE3' => [
                'dutch' => 'Laagfrequentie golfhoogte',
                'english' => 'Low-freq wave height',
                'unit' => 'cm',
                'description' => 'From energy spectrum 30-100 mHz',
                'category' => 'waves',
            ],
            'Tm02' => [
                'dutch' => 'Gemiddelde golfperiode',
                'english' => 'Mean wave period',
                'unit' => 's',
                'description' => 'Zero-crossing period from spectrum',
                'category' => 'waves',
            ],
            'Tm01' => [
                'dutch' => 'Gemiddelde golfperiode',
                'english' => 'Mean wave period',
                'unit' => 's',
                'description' => 'First moment wave period',
                'category' => 'waves',
            ],
            'Tm-10' => [
                'dutch' => 'Golfperiode',
                'english' => 'Energy wave period',
                'unit' => 's',
                'description' => 'Energy period (-1/0 spectral moment)',
                'category' => 'waves',
            ],
            'Tmax' => [
                'dutch' => 'Maximale golfperiode',
                'english' => 'Maximum wave period',
                'unit' => 's',
                'description' => 'Longest wave period',
                'category' => 'waves',
            ],
            'T1/3' => [
                'dutch' => 'Significante golfperiode',
                'english' => 'Significant wave period',
                'unit' => 's',
                'description' => 'Average period of highest 1/3',
                'category' => 'waves',
            ],
            'T_H1/3' => [
                'dutch' => 'Periode bij H1/3',
                'english' => 'Period at H1/3',
                'unit' => 's',
                'description' => 'Period associated with H1/3',
                'category' => 'waves',
            ],
            'T_Hmax' => [
                'dutch' => 'Periode bij Hmax',
                'english' => 'Period at Hmax',
                'unit' => 's',
                'description' => 'Period of the maximum wave',
                'category' => 'waves',
            ],
            'Th0' => [
                'dutch' => 'Gemiddelde golfrichting',
                'english' => 'Mean wave direction',
                'unit' => '°',
                'description' => 'Spectral mean direction (from true N)',
                'category' => 'waves',
            ],
            'Th3' => [
                'dutch' => 'Golfrichting',
                'english' => 'Wave direction',
                'unit' => '°',
                'description' => 'Mean direction of H1/3 waves (from true N)',
                'category' => 'waves',
            ],
            'Fp' => [
                'dutch' => 'Piekfrequentie',
                'english' => 'Peak frequency',
                'unit' => 'Hz',
                'description' => 'Dominant wave frequency',
                'category' => 'waves',
            ],

            // Temperature
            'T' => [
                'dutch' => 'Temperatuur',
                'english' => 'Temperature',
                'unit' => '°C',
                'description' => 'Water or air temperature',
                'category' => 'temperature',
            ],
            'TE' => [
                'dutch' => 'Temperatuur extern',
                'english' => 'External temperature',
                'unit' => '°C',
                'description' => 'External sensor temperature',
                'category' => 'temperature',
            ],
            'TE3' => [
                'dutch' => 'Temperatuur 3m',
                'english' => 'Temperature at 3m',
                'unit' => '°C',
                'description' => 'Temperature at 3 meter depth',
                'category' => 'temperature',
            ],

            // Wind
            'WINDSHD' => [
                'dutch' => 'Windsnelheid',
                'english' => 'Wind speed',
                'unit' => 'm/s',
                'description' => 'Wind speed',
                'category' => 'wind',
            ],
            'WINDRTG' => [
                'dutch' => 'Windrichting',
                'english' => 'Wind direction',
                'unit' => '°',
                'description' => 'Direction wind comes from (0=N)',
                'category' => 'wind',
            ],
            'WINDST' => [
                'dutch' => 'Windstoot',
                'english' => 'Wind gust',
                'unit' => 'm/s',
                'description' => 'Maximum gust speed',
                'category' => 'wind',
            ],
            'WINDSHD_SD' => [
                'dutch' => 'Windsnelheid SD',
                'english' => 'Wind speed std dev',
                'unit' => 'm/s',
                'description' => 'Standard deviation of wind speed',
                'category' => 'wind',
            ],
            'WINDRTG_SD' => [
                'dutch' => 'Windrichting SD',
                'english' => 'Wind direction std dev',
                'unit' => '°',
                'description' => 'Standard deviation of direction',
                'category' => 'wind',
            ],
            'WS1' => [
                'dutch' => 'Windsnelheid 1',
                'english' => 'Wind speed 1',
                'unit' => 'm/s',
                'description' => 'Wind speed sensor 1',
                'category' => 'wind',
            ],
            'WS10' => [
                'dutch' => 'Windsnelheid 10m',
                'english' => 'Wind speed 10m',
                'unit' => 'm/s',
                'description' => 'Wind speed at 10m height',
                'category' => 'wind',
            ],
            'WR1' => [
                'dutch' => 'Windrichting 1',
                'english' => 'Wind direction 1',
                'unit' => '°',
                'description' => 'Wind direction sensor 1',
                'category' => 'wind',
            ],

            // Current & Flow
            'STROOMSHD' => [
                'dutch' => 'Stroomsnelheid',
                'english' => 'Current speed',
                'unit' => 'm/s',
                'description' => 'Water current velocity',
                'category' => 'current',
            ],
            'STROOMRTG' => [
                'dutch' => 'Stroomrichting',
                'english' => 'Current direction',
                'unit' => '°',
                'description' => 'Direction current flows to',
                'category' => 'current',
            ],
            'Q' => [
                'dutch' => 'Debiet',
                'english' => 'Discharge',
                'unit' => 'm³/s',
                'description' => 'Water flow rate',
                'category' => 'current',
            ],
            'Qo' => [
                'dutch' => 'Debiet oppervlak',
                'english' => 'Surface discharge',
                'unit' => 'm³/s',
                'description' => 'Surface layer discharge',
                'category' => 'current',
            ],
            'Qs' => [
                'dutch' => 'Debiet sediment',
                'english' => 'Sediment discharge',
                'unit' => 'kg/s',
                'description' => 'Sediment transport rate',
                'category' => 'current',
            ],

            // Water Quality
            'SALNTT' => [
                'dutch' => 'Saliniteit',
                'english' => 'Salinity',
                'unit' => 'g/kg',
                'description' => 'Salt concentration',
                'category' => 'water_quality',
            ],
            'CONCTTE' => [
                'dutch' => 'Concentratie',
                'english' => 'Concentration',
                'unit' => 'mg/l',
                'description' => 'General concentration',
                'category' => 'water_quality',
            ],
            'TROEBHD' => [
                'dutch' => 'Troebelheid',
                'english' => 'Turbidity',
                'unit' => 'NTU',
                'description' => 'Water clarity/turbidity',
                'category' => 'water_quality',
            ],
            'pH' => [
                'dutch' => 'Zuurgraad',
                'english' => 'pH value',
                'unit' => null,
                'description' => 'Acidity/alkalinity',
                'category' => 'water_quality',
            ],
            'ZICHT' => [
                'dutch' => 'Zicht',
                'english' => 'Visibility',
                'unit' => 'm',
                'description' => 'Secchi depth / visibility',
                'category' => 'water_quality',
            ],
            'GELDHD' => [
                'dutch' => 'Geleidbaarheid',
                'english' => 'Conductivity',
                'unit' => 'mS/cm',
                'description' => 'Electrical conductivity',
                'category' => 'water_quality',
            ],
            'DICHTHD' => [
                'dutch' => 'Dichtheid',
                'english' => 'Density',
                'unit' => 'kg/m³',
                'description' => 'Water density',
                'category' => 'water_quality',
            ],
            'FLUORCTE' => [
                'dutch' => 'Fluorescentie',
                'english' => 'Fluorescence',
                'unit' => null,
                'description' => 'Chlorophyll fluorescence',
                'category' => 'water_quality',
            ],
            'WATOZT' => [
                'dutch' => 'Watertemperatuur zout',
                'english' => 'Salt water temp',
                'unit' => '°C',
                'description' => 'Saline water temperature',
                'category' => 'water_quality',
            ],

            // Atmospheric
            'LUCHTDK' => [
                'dutch' => 'Luchtdruk',
                'english' => 'Air pressure',
                'unit' => 'hPa',
                'description' => 'Atmospheric pressure',
                'category' => 'atmospheric',
            ],

            // Physical Dimensions
            'HOOGTE' => [
                'dutch' => 'Hoogte',
                'english' => 'Height',
                'unit' => 'm',
                'description' => 'General height measurement',
                'category' => 'dimensions',
            ],
            'DIKTE' => [
                'dutch' => 'Dikte',
                'english' => 'Thickness',
                'unit' => 'm',
                'description' => 'Layer thickness',
                'category' => 'dimensions',
            ],
            'LENGTE' => [
                'dutch' => 'Lengte',
                'english' => 'Length',
                'unit' => 'm',
                'description' => 'Length measurement',
                'category' => 'dimensions',
            ],
            'BREEDTE' => [
                'dutch' => 'Breedte',
                'english' => 'Width',
                'unit' => 'm',
                'description' => 'Width measurement',
                'category' => 'dimensions',
            ],
            'WATDTE' => [
                'dutch' => 'Waterdiepte',
                'english' => 'Water depth',
                'unit' => 'm',
                'description' => 'Depth of water',
                'category' => 'dimensions',
            ],
            'HEFHTE' => [
                'dutch' => 'Hefhoogte',
                'english' => 'Lift height',
                'unit' => 'm',
                'description' => 'Vertical lift/rise',
                'category' => 'dimensions',
            ],
            'KRUINHTE' => [
                'dutch' => 'Kruinhoogte',
                'english' => 'Crest height',
                'unit' => 'm',
                'description' => 'Crest/top height',
                'category' => 'dimensions',
            ],

            // Other
            'NVT' => [
                'dutch' => 'Niet Van Toepassing',
                'english' => 'Not applicable',
                'unit' => null,
                'description' => 'No measurement type applies',
                'category' => 'other',
            ],
            'ECHO' => [
                'dutch' => 'Echo',
                'english' => 'Echo',
                'unit' => null,
                'description' => 'Sonar/echo measurement',
                'category' => 'other',
            ],
            'VERSIE' => [
                'dutch' => 'Versie',
                'english' => 'Version',
                'unit' => null,
                'description' => 'Data version',
                'category' => 'other',
            ],
        ];
    }

    /**
     * Get description for a compartiment code.
     *
     * @return array{dutch: string, english: string, description: string}|null
     */
    public static function getCompartiment(string $code): ?array
    {
        return self::getCompartimenten()[$code] ?? null;
    }

    /**
     * Get description for a grootheid code.
     *
     * @return array{dutch: string, english: string, unit: string|null, description: string, category: string}|null
     */
    public static function getGrootheid(string $code): ?array
    {
        return self::getGrootheden()[$code] ?? null;
    }

    /**
     * Get all category names.
     *
     * @return string[]
     */
    public static function getCategories(): array
    {
        return [
            'water_level' => 'Water Level & Tides',
            'waves' => 'Waves',
            'temperature' => 'Temperature',
            'wind' => 'Wind',
            'current' => 'Current & Flow',
            'water_quality' => 'Water Quality',
            'atmospheric' => 'Atmospheric',
            'dimensions' => 'Physical Dimensions',
            'other' => 'Other',
        ];
    }
}
