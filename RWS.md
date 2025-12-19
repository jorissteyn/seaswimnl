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

**Key codes to know:**

| Compartiment (where) | Code |
|---------------------|------|
| Surface water | `OW` |
| Air | `LT` |
| Sediment/soil | `BS` |
| Suspended matter | `ZS` |

| Grootheid (what) | Code |
|-----------------|------|
| Water height | `WATHTE` |
| Temperature | `T` |
| Wave height | `Hm0` |
| Flow/discharge | `Q` |
| Concentration | `CONCTTE` |
| Mass fraction | `MASSFTE` |

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
