# Rijkswaterstaat Water API - Instructions for Claude Code

## Overview

The Rijkswaterstaat (Dutch government) provides free access to water measurement data through their WaterWebservices API. This includes water levels, temperatures, wave heights, flow rates, and chemical measurements across the Netherlands.

**License:** CC0 (public domain)

## Base URLs

```
Metadata:    https://ddapi20-waterwebservices.rijkswaterstaat.nl/METADATASERVICES/
Observations: https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/
```

All requests are `POST` with `Content-Type: application/json`.

---

## Step 1: Get the Catalog (What's Available)

First, retrieve the catalog to see what measurements exist and where.

**Endpoint:** `POST /METADATASERVICES/OphalenCatalogus`

```json
{
  "CatalogusFilter": {
    "Compartimenten": true,
    "Grootheden": true,
    "Parameters": true
  }
}
```

**Response contains:**
- `AquoMetadataLijst` - Metadata about measurements
- `LocatieLijst` - Measurement locations
- `AquoMetadataLocatieLijst` - Links metadata to locations

---

## Aquo Standard Codes

The RWS API uses the [Aquo standard](https://www.aquo.nl/) for water data exchange. Two key concepts:

- **Compartiment**: Where the measurement is taken (e.g., surface water, air)
- **Grootheid**: What is being measured (e.g., water height, temperature)

### Compartimenten (Measurement Environment)

| Code | Dutch | English | Description |
|------|-------|---------|-------------|
| `OW` | Oppervlaktewater | Surface water | Water in rivers, lakes, seas, canals |
| `LT` | Lucht | Air | Atmospheric measurements |
| `BS` | Bodem/Sediment | Soil/Sediment | Bottom sediment or soil |
| `ZS` | Zwevende Stof | Suspended matter | Particles suspended in water |
| `OE` | Oever | Shore/Bank | Riverbank or shore measurements |
| `OR` | Organisme | Organism | Biota/organism samples |
| `NVT` | Niet Van Toepassing | Not applicable | No compartment applies |
| `NT` | Niet Te Bepalen | Not determined | Cannot be determined |
| `PM` | Particulate Matter | Particulate matter | Air quality particles |

### Grootheden (Measurement Types)

#### Water Level & Tides

| Code | Dutch | English | Unit | Description |
|------|-------|---------|------|-------------|
| `WATHTE` | Waterhoogte | Water height | cm | Water level relative to NAP |
| `GGH` | Gemiddeld Getij Hoogwater | Mean high water | cm | Average high tide level |
| `GGT` | Gemiddeld Getij | Mean tide | cm | Average tide level |
| `HH` | Hoogste Hoogwater | Highest high water | cm | Maximum recorded high water |
| `HHT` | Hoogste Hoogwater Tijd | Highest high water time | - | Time of highest high water |
| `LG` | Laagste Laagwater | Lowest low water | cm | Minimum recorded low water |
| `NG` | Normaal Getij | Normal tide | cm | Normal tide level |
| `NGWTTL` | Normaal Getij Waterstand | Normal tide water level | cm | Normal tidal water level |
| `SLOTGHW` | Slotgemiddeld Hoogwater | Final mean high water | cm | Computed mean high water |
| `SLOTGLW` | Slotgemiddeld Laagwater | Final mean low water | cm | Computed mean low water |
| `SLOTGWAT` | Slotgemiddeld Water | Final mean water | cm | Computed mean water level |
| `SPGH` | Spring Hoogwater | Spring high water | cm | Spring tide high water |
| `SPGT` | Spring Getij | Spring tide | cm | Spring tide level |
| `HOOGWTDG` | Hoogwater Dag | High water day | - | Daily high water |
| `HOOGWTNT` | Hoogwater Nacht | High water night | - | Nightly high water |
| `LAAGWTDG` | Laagwater Dag | Low water day | - | Daily low water |
| `GETVVG` | Getijverschuiving | Tidal shift | min | Tidal time difference |

#### Waves

| Code | Dutch | English | Unit | Description |
|------|-------|---------|------|-------------|
| `Hm0` | Spectrale golfhoogte | Significant wave height | cm | From energy spectrum 30-500 mHz (4×σ) |
| `Hmax` | Maximale golfhoogte | Maximum wave height | cm | Highest individual wave |
| `H1/3` | Significante golfhoogte | Significant wave height | cm | Average of highest 1/3 of waves (~7% < Hm0) |
| `H1/10` | Golfhoogte 1/10 | Wave height 1/10 | cm | Average of highest 1/10 of waves |
| `H1/50` | Golfhoogte 1/50 | Wave height 1/50 | cm | Average of highest 1/50 of waves |
| `GOLFHTE` | Golfhoogte | Wave height | cm | General wave height |
| `HTE3` | Laagfrequentie golfhoogte | Low-freq wave height | cm | From energy spectrum 30-100 mHz |
| `Tm02` | Gemiddelde golfperiode | Mean wave period | s | Zero-crossing period from spectrum |
| `Tm01` | Gemiddelde golfperiode | Mean wave period | s | First moment wave period |
| `Tm-10` | Golfperiode | Energy wave period | s | Energy period (-1/0 spectral moment) |
| `Tmax` | Maximale golfperiode | Maximum wave period | s | Longest wave period |
| `T1/3` | Significante golfperiode | Significant wave period | s | Average period of highest 1/3 |
| `T_H1/3` | Periode bij H1/3 | Period at H1/3 | s | Period associated with H1/3 |
| `T_Hmax` | Periode bij Hmax | Period at Hmax | s | Period of the maximum wave |
| `Th0` | Gemiddelde golfrichting | Mean wave direction | ° | Spectral mean direction (from true N) |
| `Th3` | Golfrichting | Wave direction | ° | Mean direction of H1/3 waves (from true N) |
| `Fp` | Piekfrequentie | Peak frequency | Hz | Dominant wave frequency |

#### Temperature

| Code | Dutch | English | Unit | Description |
|------|-------|---------|------|-------------|
| `T` | Temperatuur | Temperature | °C | Water or air temperature |
| `TE` | Temperatuur extern | External temperature | °C | External sensor temperature |
| `TE3` | Temperatuur 3m | Temperature at 3m | °C | Temperature at 3 meter depth |

#### Wind

| Code | Dutch | English | Unit | Description |
|------|-------|---------|------|-------------|
| `WINDSHD` | Windsnelheid | Wind speed | m/s | Wind speed |
| `WINDRTG` | Windrichting | Wind direction | ° | Direction wind comes from (0=N) |
| `WINDST` | Windstoot | Wind gust | m/s | Maximum gust speed |
| `WINDSHD_SD` | Windsnelheid SD | Wind speed std dev | m/s | Standard deviation of wind speed |
| `WINDRTG_SD` | Windrichting SD | Wind direction std dev | ° | Standard deviation of direction |
| `WS1` | Windsnelheid 1 | Wind speed 1 | m/s | Wind speed sensor 1 |
| `WS10` | Windsnelheid 10m | Wind speed 10m | m/s | Wind speed at 10m height |
| `WR1` | Windrichting 1 | Wind direction 1 | ° | Wind direction sensor 1 |

#### Current & Flow

| Code | Dutch | English | Unit | Description |
|------|-------|---------|------|-------------|
| `STROOMSHD` | Stroomsnelheid | Current speed | m/s | Water current velocity |
| `STROOMRTG` | Stroomrichting | Current direction | ° | Direction current flows to |
| `Q` | Debiet | Discharge | m³/s | Water flow rate |
| `Qo` | Debiet oppervlak | Surface discharge | m³/s | Surface layer discharge |
| `Qs` | Debiet sediment | Sediment discharge | kg/s | Sediment transport rate |

#### Water Quality

| Code | Dutch | English | Unit | Description |
|------|-------|---------|------|-------------|
| `SALNTT` | Saliniteit | Salinity | g/kg | Salt concentration |
| `CONCTTE` | Concentratie | Concentration | mg/l | General concentration |
| `TROEBHD` | Troebelheid | Turbidity | NTU | Water clarity/turbidity |
| `pH` | Zuurgraad | pH value | - | Acidity/alkalinity |
| `ZICHT` | Zicht | Visibility | m | Secchi depth / visibility |
| `GELDHD` | Geleidbaarheid | Conductivity | mS/cm | Electrical conductivity |
| `DICHTHD` | Dichtheid | Density | kg/m³ | Water density |
| `FLUORCTE` | Fluorescentie | Fluorescence | - | Chlorophyll fluorescence |
| `WATOZT` | Watertemperatuur zout | Salt water temp | °C | Saline water temperature |

#### Atmospheric

| Code | Dutch | English | Unit | Description |
|------|-------|---------|------|-------------|
| `LUCHTDK` | Luchtdruk | Air pressure | hPa | Atmospheric pressure |

#### Physical Dimensions

| Code | Dutch | English | Unit | Description |
|------|-------|---------|------|-------------|
| `HOOGTE` | Hoogte | Height | m | General height measurement |
| `DIKTE` | Dikte | Thickness | m | Layer thickness |
| `LENGTE` | Lengte | Length | m | Length measurement |
| `BREEDTE` | Breedte | Width | m | Width measurement |
| `WATDTE` | Waterdiepte | Water depth | m | Depth of water |
| `HEFHTE` | Hefhoogte | Lift height | m | Vertical lift/rise |
| `KRUINHTE` | Kruinhoogte | Crest height | m | Crest/top height |

#### Statistics & Percentiles

| Code | Dutch | English | Description |
|------|-------|---------|-------------|
| `50%_L` | 50% lijn | 50th percentile | Median value |
| `70%_L` | 70% lijn | 70th percentile | 70th percentile |
| `80%_L` | 80% lijn | 80th percentile | 80th percentile |
| `90%_L` | 90% lijn | 90th percentile | 90th percentile |
| `D10`-`D90` | Doorval | Grain size | Particle size distribution |

#### Other / Technical

| Code | Dutch | English | Description |
|------|-------|---------|-------------|
| `NVT` | Niet Van Toepassing | Not applicable | No measurement type applies |
| `ECHO` | Echo | Echo | Sonar/echo measurement |
| `VERSIE` | Versie | Version | Data version |
| `INDCTOPDT` | Indicator opdat | Update indicator | Data update flag |
| `ISI` | ISI | ISI | Imposex index (biota) |
| `VDSI` | VDSI | VDSI | Vas deferens sequence index |
| `RPSI` | RPSI | RPSI | Relative penis size index |

---

## Step 2: Check if Data Exists (Optional)

**Endpoint:** `POST /ONLINEWAARNEMINGENSERVICES/CheckWaarnemingenAanwezig`

```json
{
  "LocatieLijst": [{"Code": "hoekvanholland"}],
  "AquoMetadataLijst": [{
    "Compartiment": {"Code": "OW"},
    "Grootheid": {"Code": "WATHTE"}
  }],
  "Periode": {
    "Begindatumtijd": "2024-01-01T00:00:00.000+01:00",
    "Einddatumtijd": "2024-02-01T00:00:00.000+01:00"
  }
}
```

---

## Step 3: Retrieve Observations

**Endpoint:** `POST /ONLINEWAARNEMINGENSERVICES/OphalenWaarnemingen`

### Example: Water levels at Hoek van Holland

```json
{
  "Locatie": {"Code": "hoekvanholland"},
  "AquoPlusWaarnemingMetadata": {
    "AquoMetadata": {
      "Compartiment": {"Code": "OW"},
      "Grootheid": {"Code": "WATHTE"},
      "ProcesType": "meting"
    }
  },
  "Periode": {
    "Begindatumtijd": "2024-12-01T00:00:00.000+01:00",
    "Einddatumtijd": "2024-12-02T00:00:00.000+01:00"
  }
}
```

### Example: Water temperature at Vlissingen

```json
{
  "Locatie": {"Code": "vlissingen"},
  "AquoPlusWaarnemingMetadata": {
    "AquoMetadata": {
      "Compartiment": {"Code": "OW"},
      "Grootheid": {"Code": "T"}
    }
  },
  "Periode": {
    "Begindatumtijd": "2024-12-01T00:00:00.000+01:00",
    "Einddatumtijd": "2024-12-02T00:00:00.000+01:00"
  }
}
```

### Example: Tidal predictions (astronomical)

```json
{
  "Locatie": {"Code": "ameland.nes"},
  "AquoPlusWaarnemingMetadata": {
    "AquoMetadata": {
      "Grootheid": {"Code": "WATHTE"},
      "ProcesType": "astronomisch"
    }
  },
  "Periode": {
    "Begindatumtijd": "2025-01-01T00:00:00.000+01:00",
    "Einddatumtijd": "2025-02-01T00:00:00.000+01:00"
  }
}
```

### Example: Weather-based water level forecast

```json
{
  "Locatie": {"Code": "hoekvanholland"},
  "AquoPlusWaarnemingMetadata": {
    "AquoMetadata": {
      "Grootheid": {"Code": "WATHTE"},
      "ProcesType": "verwachting"
    }
  },
  "Periode": {
    "Begindatumtijd": "2025-01-01T00:00:00.000+01:00",
    "Einddatumtijd": "2025-01-03T00:00:00.000+01:00"
  }
}
```

---

## Step 4: Get Latest Observations

**Endpoint:** `POST /ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen`

Returns the most recent measurement for each location/parameter combination.

```json
{
  "LocatieLijst": [
    {"Code": "vlissingen"},
    {"Code": "hoekvanholland"}
  ],
  "AquoPlusWaarnemingMetadataLijst": [
    {
      "AquoMetadata": {
        "Compartiment": {"Code": "OW"},
        "Grootheid": {"Code": "T"}
      },
      "WaarnemingMetadata": {
        "OpdrachtgevendeInstantieLijst": ["RIKZMON_TEMP"]
      }
    },
    {
      "AquoMetadata": {
        "Compartiment": {"Code": "LT"},
        "Grootheid": {"Code": "T"}
      },
      "WaarnemingMetadata": {
        "OpdrachtgevendeInstantieLijst": ["RIKZ_METEO"]
      }
    }
  ]
}
```

---

## Common Location Codes

| Location | Code |
|----------|------|
| Hoek van Holland | `hoekvanholland` |
| Vlissingen | `vlissingen` |
| IJmuiden | `ijmuiden` |
| Den Helder | `denhelder` |
| Ameland (Nes) | `ameland.nes` |
| Scheveningen | `scheveningen` |
| Rotterdam | `rotterdam` |
| Lobith (Rhine) | `lobith` |
| Lobith pontoon | `lobith.ponton` |
| Eijsden (Meuse) | `eijsden` |

---

## Filtering Options

### By Process Type
- `"ProcesType": "meting"` - Actual measurements
- `"ProcesType": "verwachting"` - Weather-based forecasts
- `"ProcesType": "astronomisch"` - Tidal predictions

### By Quality Code
Only include validated data:
```json
"WaarnemingMetadata": {
  "KwaliteitswaardecodeLijst": ["00", "10", "20", "25", "30", "40"]
}
```

### By Sampling Height
For depth-specific measurements (e.g., salinity):
```json
"WaarnemingMetadata": {
  "BemonsteringshoogteLijst": ["-250"]
}
```

---

## Important Limits

| Limit | Value |
|-------|-------|
| Max observations per request | 160,000 |
| Typical measurement interval | 10 minutes |
| Max period for 10-min data | ~3 years per request |

**Calculation:** 6 measurements/hour × 24 hours × 365 days = 52,560/year

---

## Python Example

```python
import requests

BASE_URL = "https://ddapi20-waterwebservices.rijkswaterstaat.nl"

def get_water_levels(location: str, start: str, end: str) -> dict:
    """
    Fetch water level measurements.
    
    Args:
        location: Location code (e.g., 'hoekvanholland')
        start: Start datetime ISO format (e.g., '2024-12-01T00:00:00.000+01:00')
        end: End datetime ISO format
    
    Returns:
        JSON response with observations
    """
    url = f"{BASE_URL}/ONLINEWAARNEMINGENSERVICES/OphalenWaarnemingen"
    
    payload = {
        "Locatie": {"Code": location},
        "AquoPlusWaarnemingMetadata": {
            "AquoMetadata": {
                "Compartiment": {"Code": "OW"},
                "Grootheid": {"Code": "WATHTE"},
                "ProcesType": "meting"
            }
        },
        "Periode": {
            "Begindatumtijd": start,
            "Einddatumtijd": end
        }
    }
    
    response = requests.post(url, json=payload)
    response.raise_for_status()
    return response.json()


def get_catalog() -> dict:
    """Fetch the full catalog of available measurements."""
    url = f"{BASE_URL}/METADATASERVICES/OphalenCatalogus"
    
    payload = {
        "CatalogusFilter": {
            "Compartimenten": True,
            "Grootheden": True,
            "Parameters": True
        }
    }
    
    response = requests.post(url, json=payload)
    response.raise_for_status()
    return response.json()
```

---

## Recommended Python Packages

Instead of raw API calls, consider using:

- **ddlpy** (Deltares): `pip install ddlpy`
- **rws-waterinfo** (Datalab): `pip install rws-waterinfo`

These packages handle the API complexity for you.

---

## Coordinate System

Coordinates are returned in **ETRS89 lat/lon (EPSG:4258)**, which is practically identical to WGS84 for display purposes.

---

## Notes

- All times use ISO 8601 format with timezone offset (`+01:00` = Dutch winter time)
- The API has no uptime guarantee; don't use for critical applications
- Chemical measurements may have detection limits indicated by `"Waarde_Limietsymbool": "<"`
- Classic API (`waterwebservices.rijkswaterstaat.nl`) will be deprecated end of April 2026
