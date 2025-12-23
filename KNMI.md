# KNMI Weather Data API Documentation

This document provides all information needed to programmatically access weather data from the Royal Netherlands Meteorological Institute (KNMI).

## Overview

KNMI provides three main API endpoints for retrieving historical weather data:

| Endpoint | Description | Data Type |
|----------|-------------|-----------|
| `/klimatologie/daggegevens` | Daily weather data from automatic weather stations | Daily aggregates |
| `/klimatologie/uurgegevens` | Hourly weather data from automatic weather stations | Hourly measurements |
| `/klimatologie/monv/reeksen` | Daily precipitation data from rain gauge stations | Daily rain/snow |

**Base URL:** `https://www.daggegevens.knmi.nl`

## Authentication

No authentication required. The API is publicly accessible.

## Request Method

All endpoints use **HTTP POST** with form-encoded data (`application/x-www-form-urlencoded`).

## Response Formats

Specify format using the `fmt` parameter:
- `csv` (default) - Comma-separated values with header comments
- `json` - JSON format
- `xml` - XML format

---

## Endpoint 1: Daily Weather Data

**URL:** `https://www.daggegevens.knmi.nl/klimatologie/daggegevens`

### Parameters

| Parameter | Required | Format | Description |
|-----------|----------|--------|-------------|
| `start` | Yes | `YYYYMMDD` | Start date |
| `end` | Yes | `YYYYMMDD` | End date |
| `stns` | Yes | `NUM:NUM:...` or `ALL` | Station numbers separated by `:` |
| `vars` | No | `VAR:VAR:...` or group | Variables separated by `:` (default: ALL) |
| `fmt` | No | `csv`/`json`/`xml` | Output format (default: csv) |
| `inseason` | No | `Y` | Filter to seasonal period only |

### Variable Groups (Daily)

| Group | Variables | Description |
|-------|-----------|-------------|
| `WIND` | DD, FG, FHX, FX | Wind direction and speed |
| `TEMP` | TG, TN, TX, T10N | Temperature (avg, min, max, min at 10cm) |
| `SUNR` | SQ, SP, Q | Sunshine duration and global radiation |
| `PRCP` | DR, RH, EV24 | Precipitation and evaporation |
| `PRES` | PG, PGX, PGN | Sea level pressure (avg, max, min) |
| `VICL` | VVN, VVX, NG | Visibility and cloud cover |
| `MSTR` | UG, UX, UN | Relative humidity (avg, max, min) |
| `ALL` | All variables | Complete dataset |

### Individual Variables (Daily)

| Variable | Unit | Description |
|----------|------|-------------|
| `DDVEC` | degrees | Vector mean wind direction |
| `FG` | 0.1 m/s | Daily mean wind speed |
| `FHX` | 0.1 m/s | Maximum hourly mean wind speed |
| `FX` | 0.1 m/s | Maximum wind gust |
| `TG` | 0.1 °C | Daily mean temperature |
| `TN` | 0.1 °C | Minimum temperature |
| `TX` | 0.1 °C | Maximum temperature |
| `T10N` | 0.1 °C | Minimum temperature at 10cm |
| `SQ` | 0.1 hour | Sunshine duration |
| `SP` | % | Sunshine duration percentage |
| `Q` | J/cm² | Global radiation |
| `DR` | 0.1 hour | Precipitation duration |
| `RH` | 0.1 mm | Daily precipitation (-1 = <0.05mm) |
| `RHX` | 0.1 mm | Maximum hourly precipitation |
| `EV24` | 0.1 mm | Potential evapotranspiration (Makkink) |
| `PG` | 0.1 hPa | Mean sea level pressure |
| `PGX` | 0.1 hPa | Maximum sea level pressure |
| `PGN` | 0.1 hPa | Minimum sea level pressure |
| `VVN` | code | Minimum visibility |
| `VVX` | code | Maximum visibility |
| `NG` | octants | Mean cloud cover (9 = sky invisible) |
| `UG` | % | Mean relative humidity |
| `UX` | % | Maximum relative humidity |
| `UN` | % | Minimum relative humidity |

### Example Request (Daily)

```bash
curl -X POST "https://www.daggegevens.knmi.nl/klimatologie/daggegevens" \
  -d "start=20240101" \
  -d "end=20240131" \
  -d "stns=260" \
  -d "vars=TEMP:PRCP" \
  -d "fmt=json"
```

```python
import requests

response = requests.post(
    "https://www.daggegevens.knmi.nl/klimatologie/daggegevens",
    data={
        "start": "20240101",
        "end": "20240131",
        "stns": "260:235:280",
        "vars": "TG:TN:TX:RH",
        "fmt": "json"
    }
)
data = response.json()
```

---

## Endpoint 2: Hourly Weather Data

**URL:** `https://www.daggegevens.knmi.nl/klimatologie/uurgegevens`

### Parameters

| Parameter | Required | Format | Description |
|-----------|----------|--------|-------------|
| `start` | Yes | `YYYYMMDDHH` | Start datetime (HH = hour 01-24) |
| `end` | Yes | `YYYYMMDDHH` | End datetime (HH = hour 01-24) |
| `stns` | Yes | `NUM:NUM:...` or `ALL` | Station numbers |
| `vars` | No | `VAR:VAR:...` or group | Variables (default: ALL) |
| `fmt` | No | `csv`/`json`/`xml` | Output format |

**Note:** The hour range in `start` and `end` determines which hours are returned for each day. For example, `start=2024010106` and `end=2024013112` returns hours 6-12 for each day in the range.

### Variable Groups (Hourly)

| Group | Variables | Description |
|-------|-----------|-------------|
| `WIND` | DD, FH, FF, FX | Wind |
| `TEMP` | T, T10N, TD | Temperature |
| `SUNR` | SQ, Q | Sunshine and radiation |
| `PRCP` | DR, RH | Precipitation |
| `VICL` | VV, N, U | Visibility, cloud cover, humidity |
| `WEER` | M, R, S, O, Y, WW | Weather phenomena |
| `ALL` | All variables | Complete dataset |

### Individual Variables (Hourly)

| Variable | Unit | Description |
|----------|------|-------------|
| `DD` | degrees | Wind direction (360=N, 90=E, 180=S, 270=W, 0=calm) |
| `FH` | 0.1 m/s | Hourly mean wind speed |
| `FF` | 0.1 m/s | Mean wind speed (last 10 min of hour) |
| `FX` | 0.1 m/s | Maximum wind gust |
| `T` | 0.1 °C | Temperature at 1.5m |
| `T10N` | 0.1 °C | Minimum temperature at 10cm (6-hour period) |
| `TD` | 0.1 °C | Dew point temperature |
| `SQ` | 0.1 hour | Sunshine duration |
| `Q` | J/cm² | Global radiation |
| `DR` | 0.1 hour | Precipitation duration |
| `RH` | 0.1 mm | Hourly precipitation (-1 = <0.05mm) |
| `VV` | code | Visibility |
| `N` | octants | Cloud cover |
| `U` | % | Relative humidity |
| `WW` | code | Present weather code |
| `M` | 0/1 | Fog indicator |
| `R` | 0/1 | Rain indicator |
| `S` | 0/1 | Snow indicator |
| `O` | 0/1 | Thunder indicator |
| `Y` | 0/1 | Ice formation indicator |

### Example Request (Hourly)

```python
import requests

response = requests.post(
    "https://www.daggegevens.knmi.nl/klimatologie/uurgegevens",
    data={
        "start": "2024010101",
        "end": "2024010724",
        "stns": "260",
        "vars": "T:RH:DD:FH",
        "fmt": "json"
    }
)
data = response.json()
```

---

## Endpoint 3: Daily Precipitation Data

**URL:** `https://www.daggegevens.knmi.nl/klimatologie/monv/reeksen`

### Parameters

All parameters are **optional** for this endpoint:

| Parameter | Required | Format | Description |
|-----------|----------|--------|-------------|
| `start` | No | `YYYYMMDD` | Start date (default: 1st of previous month) |
| `end` | No | `YYYYMMDD` | End date (default: most recent validated data) |
| `stns` | No | `NUM:NUM:...` | Station numbers |
| `fmt` | No | `csv`/`json`/`xml` | Output format |

### Output Variables

| Variable | Unit | Description |
|----------|------|-------------|
| `RD` | 0.1 mm | Daily precipitation |
| `SX` | cm | Snow cover (codes >996 have special meaning) |

### Example Request (Precipitation)

```python
import requests

response = requests.post(
    "https://www.daggegevens.knmi.nl/klimatologie/monv/reeksen",
    data={
        "start": "20240101",
        "end": "20240131",
        "fmt": "json"
    }
)
data = response.json()
```

---

## Weather Station Codes

### Main Automatic Weather Stations

| Code | Name | Location | Coordinates |
|------|------|----------|-------------|
| 210 | Valkenburg | South Holland | 52.17°N, 4.42°E |
| 235 | De Kooy | North Holland | 52.92°N, 4.79°E |
| 240 | Schiphol | Amsterdam Airport | 52.30°N, 4.77°E |
| 260 | De Bilt | Utrecht (reference station) | 52.10°N, 5.18°E |
| 270 | Leeuwarden | Friesland | 53.22°N, 5.75°E |
| 275 | Deelen | Gelderland | 52.06°N, 5.87°E |
| 280 | Eelde | Groningen | 53.13°N, 6.59°E |
| 290 | Twenthe | Overijssel | 52.27°N, 6.90°E |
| 310 | Vlissingen | Zeeland | 51.44°N, 3.60°E |
| 344 | Rotterdam | Zestienhoven | 51.96°N, 4.45°E |
| 350 | Gilze-Rijen | North Brabant | 51.57°N, 4.93°E |
| 370 | Eindhoven | North Brabant | 51.45°N, 5.42°E |
| 375 | Volkel | North Brabant | 51.66°N, 5.71°E |
| 380 | Maastricht | Limburg | 50.91°N, 5.77°E |

### Using All Stations

Use `stns=ALL` to retrieve data from all available stations.

---

## Response Format Examples

### CSV Response Structure

```
# Header comments starting with #
# SOURCE: ROYAL NETHERLANDS METEOROLOGICAL INSTITUTE (KNMI)
# ...station metadata...
# ...variable descriptions...
# STN,YYYYMMDD,   TG,   TN,   TX
  260,20240101,   45,   20,   72
  260,20240102,   38,   15,   58
```

### JSON Response Structure

```json
[
  {
    "station_code": 260,
    "date": "20240101",
    "TG": 45,
    "TN": 20,
    "TX": 72
  }
]
```

---

## Visibility Code Reference

| Code | Distance |
|------|----------|
| 0 | < 100 m |
| 1-49 | (code × 100) to ((code+1) × 100) m |
| 50-55 | 5-6 km |
| 56-79 | (code - 50) to (code - 49) km |
| 80-88 | (code × 5 - 370) to (code × 5 - 365) km |
| 89 | > 70 km |

---

## Important Notes

1. **Data Units:** Most measurements use 0.1 as the base unit (e.g., 45 = 4.5°C, 120 = 12.0 mm)
2. **Missing Data:** Empty fields or specific codes indicate missing data
3. **Precipitation < 0.05mm:** Indicated as -1
4. **Time Zone:** All times are in UTC
5. **Data Availability:** Historical data availability varies by station and time period
6. **Rate Limiting:** No official rate limits, but be respectful with request frequency
7. **Inhomogeneous Data:** Station relocations and equipment changes mean these series are not suitable for trend analysis

---

## Complete Python Implementation

```python
import requests
from datetime import datetime
from typing import Optional, List, Literal

class KNMIClient:
    BASE_URL = "https://www.daggegevens.knmi.nl/klimatologie"
    
    def get_daily_data(
        self,
        start: str,
        end: str,
        stations: List[str] | str = "ALL",
        variables: List[str] | str = "ALL",
        fmt: Literal["csv", "json", "xml"] = "json"
    ) -> dict | str:
        """
        Retrieve daily weather data.
        
        Args:
            start: Start date (YYYYMMDD)
            end: End date (YYYYMMDD)
            stations: List of station codes or "ALL"
            variables: List of variable codes or group name or "ALL"
            fmt: Response format
        
        Returns:
            Weather data in specified format
        """
        stns = ":".join(stations) if isinstance(stations, list) else stations
        vars_param = ":".join(variables) if isinstance(variables, list) else variables
        
        response = requests.post(
            f"{self.BASE_URL}/daggegevens",
            data={
                "start": start,
                "end": end,
                "stns": stns,
                "vars": vars_param,
                "fmt": fmt
            }
        )
        response.raise_for_status()
        return response.json() if fmt == "json" else response.text
    
    def get_hourly_data(
        self,
        start: str,
        end: str,
        stations: List[str] | str = "ALL",
        variables: List[str] | str = "ALL",
        fmt: Literal["csv", "json", "xml"] = "json"
    ) -> dict | str:
        """
        Retrieve hourly weather data.
        
        Args:
            start: Start datetime (YYYYMMDDHH, HH = 01-24)
            end: End datetime (YYYYMMDDHH, HH = 01-24)
            stations: List of station codes or "ALL"
            variables: List of variable codes or group name or "ALL"
            fmt: Response format
        
        Returns:
            Weather data in specified format
        """
        stns = ":".join(stations) if isinstance(stations, list) else stations
        vars_param = ":".join(variables) if isinstance(variables, list) else variables
        
        response = requests.post(
            f"{self.BASE_URL}/uurgegevens",
            data={
                "start": start,
                "end": end,
                "stns": stns,
                "vars": vars_param,
                "fmt": fmt
            }
        )
        response.raise_for_status()
        return response.json() if fmt == "json" else response.text
    
    def get_precipitation_data(
        self,
        start: Optional[str] = None,
        end: Optional[str] = None,
        stations: Optional[List[str] | str] = None,
        fmt: Literal["csv", "json", "xml"] = "json"
    ) -> dict | str:
        """
        Retrieve daily precipitation data from rain gauge stations.
        
        Args:
            start: Start date (YYYYMMDD), optional
            end: End date (YYYYMMDD), optional
            stations: List of station codes, optional
            fmt: Response format
        
        Returns:
            Precipitation data in specified format
        """
        data = {"fmt": fmt}
        if start:
            data["start"] = start
        if end:
            data["end"] = end
        if stations:
            data["stns"] = ":".join(stations) if isinstance(stations, list) else stations
        
        response = requests.post(
            f"{self.BASE_URL}/monv/reeksen",
            data=data
        )
        response.raise_for_status()
        return response.json() if fmt == "json" else response.text


# Usage example
if __name__ == "__main__":
    client = KNMIClient()
    
    # Get daily temperature and precipitation for De Bilt
    daily_data = client.get_daily_data(
        start="20240101",
        end="20240107",
        stations=["260"],
        variables=["TG", "TN", "TX", "RH"]
    )
    print("Daily data:", daily_data)
    
    # Get hourly data for multiple stations
    hourly_data = client.get_hourly_data(
        start="2024010112",
        end="2024010212",
        stations=["260", "240"],
        variables="TEMP"
    )
    print("Hourly data:", hourly_data)
```

---

## Using wget/curl

```bash
# Daily data
wget -O daily_data.csv --post-data="start=20240101&end=20240131&stns=260&vars=TEMP:PRCP" \
  "https://www.daggegevens.knmi.nl/klimatologie/daggegevens"

# Hourly data (JSON)
curl -X POST "https://www.daggegevens.knmi.nl/klimatologie/uurgegevens" \
  -d "start=2024010101&end=2024010724&stns=260&vars=T:RH&fmt=json" \
  -o hourly_data.json

# Precipitation data
curl -X POST "https://www.daggegevens.knmi.nl/klimatologie/monv/reeksen" \
  -d "start=20240101&end=20240131&fmt=json"
```
