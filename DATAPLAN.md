# Seaswim Dashboard - Data Sources

This document describes all data displayed in the Seaswim dashboard, their sources, and how they are fetched and transformed.

## API Endpoints

| API | Base URL | Method | Timeout | Cache TTL |
|-----|----------|--------|---------|-----------|
| **RWS Rijkswaterstaat** | `https://ddapi20-waterwebservices.rijkswaterstaat.nl` | POST | 30s | 1 hour |
| **Buienradar** | `https://data.buienradar.nl` | GET | 30s | 1 hour |

---

## Water Conditions Card

**Source:** RWS Rijkswaterstaat
**Endpoint:** `POST /ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen`

| Field | Display Name | Grootheid | Compartiment | JSON Path | Unit | Transform |
|-------|--------------|-----------|--------------|-----------|------|-----------|
| Water Temperature | Temperature | `T` | `OW` | `WaarnemingenLijst[].MetingenLijst[].Meetwaarde.Waarde_Numeriek` | °C | None |
| Water Height | Current Water Height | `WATHTE` | `OW` | Same path | m | ÷100 (cm→m) |
| Wave Height | Wave Height | `Hm0` | `OW` | Same path | m | ÷100 (cm→m), display as cm if <1m |
| Wave Period | Wave Period | `Tm02` | `OW` | Same path | s | None |
| Wave Direction | Wave Direction | `Th3` | `OW` | Same path | ° | Converted to compass + visual |
| Wind Speed | Wind on water | `WINDSHD` | `LT` | Same path | km/h | ×3.6 (m/s→km/h), Beaufort scale |
| Wind Direction | Wind on water | `WINDRTG` | `LT` | Same path | - | degrees→compass (N, NNO, NO, etc.) |
| Measured At | Last updated | - | - | `WaarnemingenLijst[].MetingenLijst[].Tijdstip` | - | ISO 8601→locale string |

### Request Payload Structure

```json
{
  "LocatieLijst": [{"Code": "locationCode"}],
  "AquoPlusWaarnemingMetadataLijst": [
    {"AquoMetadata": {"Compartiment": {"Code": "OW"}, "Grootheid": {"Code": "T"}}},
    {"AquoMetadata": {"Compartiment": {"Code": "OW"}, "Grootheid": {"Code": "WATHTE"}}},
    {"AquoMetadata": {"Compartiment": {"Code": "OW"}, "Grootheid": {"Code": "Hm0"}}},
    {"AquoMetadata": {"Compartiment": {"Code": "LT"}, "Grootheid": {"Code": "WINDSHD"}}},
    {"AquoMetadata": {"Compartiment": {"Code": "LT"}, "Grootheid": {"Code": "WINDRTG"}}},
    {"AquoMetadata": {"Compartiment": {"Code": "OW"}, "Grootheid": {"Code": "Tm02"}}},
    {"AquoMetadata": {"Compartiment": {"Code": "OW"}, "Grootheid": {"Code": "Th3"}}}
  ]
}
```

---

## Weather Conditions Card

**Source:** Buienradar
**Endpoint:** `GET /2.0/feed/json`

| Field | Display Name | JSON Path | Unit | Transform |
|-------|--------------|-----------|------|-----------|
| Air Temperature | Air Temperature | `actual.stationmeasurements[].temperature` | °C | None |
| Wind Speed | Wind | `actual.stationmeasurements[].windspeed` | km/h | ×3.6 (m/s→km/h), Beaufort scale |
| Wind Direction | Wind | `actual.stationmeasurements[].winddirection` | - | Already compass format |
| Sunpower | Sunpower | `actual.stationmeasurements[].sunpower` | W/m² | None, bar shows 0-1000 scale |
| Measured At | Last updated | `actual.stationmeasurements[].timestamp` | - | ISO 8601→locale string |

### Station Selection

The nearest Buienradar weather station is matched to the selected RWS location using:
- Station coordinates: `actual.stationmeasurements[].lat`, `actual.stationmeasurements[].lon`
- Haversine distance calculation
- Station code stored in `stationid` field

---

## Tides Card

**Source:** RWS Rijkswaterstaat
**Endpoint:** `POST /ONLINEWAARNEMINGENSERVICES/OphalenWaarnemingen`

| Field | Display Name | JSON Path | Unit | Transform |
|-------|--------------|-----------|------|-----------|
| Previous Tide | Previous Low/High Tide | Calculated from predictions | - | TideCalculator |
| Next Tide | Next Low/High Tide | Calculated from predictions | - | TideCalculator |
| Next High Tide | Next High Tide | Calculated from predictions | - | TideCalculator |
| Next Low Tide | Next Low Tide | Calculated from predictions | - | TideCalculator |
| Tide Height | (height) | `WaarnemingenLijst[0].MetingenLijst[].Meetwaarde.Waarde_Numeriek` | cm NAP | None |
| Tide Time | (time) | `WaarnemingenLijst[0].MetingenLijst[].Tijdstip` | - | Formatted as HH:mm |
| Current Water Height | Current Water Height | From water conditions | cm NAP | ×100 (m→cm) |

### Request Payload Structure

```json
{
  "Locatie": {"Code": "locationCode"},
  "AquoPlusWaarnemingMetadata": {
    "AquoMetadata": {
      "Grootheid": {"Code": "WATHTE"},
      "ProcesType": "astronomisch"
    }
  },
  "Periode": {
    "Begindatumtijd": "2025-12-25T10:00:00.000+01:00",
    "Einddatumtijd": "2025-12-26T10:00:00.000+01:00"
  }
}
```

**Prediction Window:** -12 hours to +12 hours from current time

### Tide Graph

- Wave curve: Cosine interpolation between low and high tide
- Dot position X: Based on current water height using arccos (follows curve)
- Dot position Y: Based on current water height relative to NAP (0 = middle line)
- NAP reference line: Dashed line at vertical center

---

## Swim Metrics Card

**Source:** Calculated from RWS + Buienradar data

### Safety Score

| Level | Criteria |
|-------|----------|
| SAFE (green) | Water temp ≥15°C, waves ≤1m, wind ≤20 km/h |
| CAUTION (yellow) | Water temp 10-15°C, waves 1-2m, wind 20-40 km/h |
| DANGEROUS (red) | Water temp <10°C, waves >2m, wind >40 km/h |

### Comfort Index (1-10 scale)

| Factor | Weight | Ideal Value |
|--------|--------|-------------|
| Water Temperature | 40% | 18-22°C |
| Air Temperature | 20% | 20-25°C |
| Wind Speed | 20% | <10 km/h |
| Sunpower | 10% | 300-600 W/m² |
| Wave Height | 10% | <0.3m |

---

## Raw Measurements Card

**Source:** RWS Rijkswaterstaat
**Endpoint:** `POST /ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen`

Fetches all available measurements for a location using these grootheden:
- `T`, `WATHTE`, `Hm0`, `Hmax`, `Tm02`, `Tm01`, `Th3`, `Th0`, `Fp`
- `WINDSHD`, `WINDRTG`, `WINDST`, `STROOMSHD`, `STROOMRTG`
- `SALNTT`, `GELDHD`, `LUCHTDK`, `Q`

For both compartimenten: `OW` (surface water), `LT` (air)

Returns deduplicated list with most recent value per grootheid/compartiment combination.

---

## Fallback Station Logic

When data is missing from the selected location, the system attempts to fetch from the nearest station with that capability:

| Capability | Grootheid Code | Max Candidates Tried |
|------------|----------------|----------------------|
| Wave Height | `Hm0` | 5 |
| Wave Period | `Tm02` | 5 |
| Wave Direction | `Th3` | 5 |
| Tidal Data | `WATHTE` | 5 |

Stations are sorted by Haversine distance and tried in order until one returns valid data.

---

## Data Freshness

- **RWS data validation:** Only data from "today" is accepted; older data is rejected
- **Blacklisted locations:** Locations with known stale data are excluded from the selector (see `blacklist.txt`)

---

## Caching Strategy

| Data Type | Cache Key | TTL | Stale Fallback |
|-----------|-----------|-----|----------------|
| Water Conditions | `rws.water.{locationId}` | 1 hour | 4 hours |
| Weather Conditions | `buienradar.weather.{stationCode}` | 1 hour | 4 hours |
| Tidal Predictions | `rws.tides.{locationId}` | 1 hour | None |
| Raw Measurements | None | - | - |

---

## Unit Conversions

| Conversion | Formula | Used For |
|------------|---------|----------|
| cm → m | value ÷ 100 | Water height, wave height |
| m/s → km/h | value × 3.6 | Wind speed |
| Degrees → Compass | Round to nearest 22.5° | Wind/wave direction |
| km/h → Beaufort | Lookup table | Wind display |

### Compass Directions (Dutch)

```
N, NNO, NO, ONO, O, OZO, ZO, ZZO, Z, ZZW, ZW, WZW, W, WNW, NW, NNW
```

### Beaufort Scale Thresholds (m/s)

| Bft | Min m/s | Description |
|-----|---------|-------------|
| 0 | 0 | Calm |
| 1 | 0.3 | Light air |
| 2 | 1.6 | Light breeze |
| 3 | 3.4 | Gentle breeze |
| 4 | 5.5 | Moderate breeze |
| 5 | 8.0 | Fresh breeze |
| 6 | 10.8 | Strong breeze |
| 7 | 13.9 | Near gale |
| 8 | 17.2 | Gale |
| 9 | 20.8 | Strong gale |
| 10 | 24.5 | Storm |
| 11 | 28.5 | Violent storm |
| 12 | 32.7 | Hurricane |

---

## File References

### Backend
- `src/Infrastructure/ExternalApi/Client/RwsHttpClient.php` - RWS API client
- `src/Infrastructure/ExternalApi/Client/BuienradarHttpClient.php` - Buienradar API client
- `src/Infrastructure/ExternalApi/RijkswaterstaatAdapter.php` - RWS data adapter
- `src/Infrastructure/ExternalApi/BuienradarAdapter.php` - Buienradar data adapter
- `src/Infrastructure/ExternalApi/RijkswaterstaatTidalAdapter.php` - Tidal data adapter
- `src/Application/UseCase/GetConditionsForLocation.php` - Main data aggregation
- `src/Domain/Service/MeasurementCodes.php` - Code descriptions

### Frontend
- `assets/components/WaterConditions.vue` - Water conditions display
- `assets/components/WeatherConditions.vue` - Weather conditions display
- `assets/components/TidesCard.vue` - Tides display with graph
- `assets/components/SwimMetrics.vue` - Safety/comfort metrics
- `assets/components/MeasurementsCard.vue` - Raw measurements table
