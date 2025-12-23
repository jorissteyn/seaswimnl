# Design: Implement KNMI Client

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Application Layer                         │
├─────────────────────────────────────────────────────────────────┤
│  RefreshLocations        FetchAllData       GetConditionsForLoc  │
│       │                       │                      │           │
│       ▼                       ▼                      ▼           │
│  ┌─────────────┐    ┌─────────────────┐    ┌─────────────────┐  │
│  │ Location    │    │ Weather         │    │ Weather         │  │
│  │ Repository  │    │ Conditions      │    │ Conditions      │  │
│  │ Interface   │    │ Provider        │    │ Provider        │  │
│  └─────────────┘    │ Interface       │    │ Interface       │  │
│        │            └─────────────────┘    └─────────────────┘  │
└────────┼────────────────────┼────────────────────┼──────────────┘
         │                    │                    │
┌────────┼────────────────────┼────────────────────┼──────────────┐
│        ▼                    ▼                    ▼               │
│  ┌─────────────┐    ┌─────────────────────────────────┐         │
│  │ JsonFile    │    │         KnmiAdapter             │         │
│  │ Location    │    │  ┌─────────────────────────┐    │         │
│  │ Repository  │    │  │ KnmiStationMatcher      │◄───┼─────┐   │
│  └─────────────┘    │  │ (Domain Service)        │    │     │   │
│                     │  └─────────────────────────┘    │     │   │
│                     │              │                  │     │   │
│                     │              ▼                  │     │   │
│                     │  ┌─────────────────────────┐    │     │   │
│                     │  │ KnmiStationRepository   │────┼─────┘   │
│                     │  └─────────────────────────┘    │         │
│                     │              │                  │         │
│                     │              ▼                  │         │
│                     │  ┌─────────────────────────┐    │         │
│                     │  │ KnmiHttpClient          │    │         │
│                     │  └─────────────────────────┘    │         │
│                     └─────────────────────────────────┘         │
│                                                                  │
│                        Infrastructure Layer                      │
└──────────────────────────────────────────────────────────────────┘
```

## Key Components

### 1. KnmiStation Value Object (Domain Layer)
```php
final readonly class KnmiStation
{
    public function __construct(
        private string $code,      // e.g., "260"
        private string $name,      // e.g., "De Bilt"
        private float $latitude,
        private float $longitude,
    ) {}
}
```

### 2. KnmiStationMatcher (Domain Service)
Responsible for finding the best matching KNMI station for a given RWS location name.

**Matching Algorithm:**
1. Normalize both names: lowercase, remove dots, trim
2. Extract first word from RWS name (location names often have suffixes like "buitenhaven", "noordelijk")
3. Check for exact match on first word
4. If no match, use Levenshtein distance on first word
5. Return best match above threshold (or null)

**Example matches:**
| RWS Location | KNMI Station |
|-------------|-------------|
| Scheveningen buitenhaven | Valkenburg (closest coastal) |
| IJmuiden stroommeetpaal | Valkenburg or De Kooy |
| Vlissingen | Vlissingen |

### 3. KnmiHttpClient (Infrastructure Layer)
Implements the real KNMI API using daggegevens.knmi.nl:

```php
interface KnmiHttpClientInterface
{
    public function fetchStations(): ?array;
    public function fetchHourlyData(string $stationCode, \DateTimeImmutable $date): ?array;
}
```

**API Details:**
- Base URL: `https://www.daggegevens.knmi.nl`
- Endpoint: `/klimatologie/uurgegevens`
- Method: POST with form-encoded data
- Variables: `T` (temp), `FH` (wind), `DD` (wind dir), `U` (humidity)
- Units: 0.1 base (45 = 4.5°C, 35 = 3.5 m/s)

### 4. Weather Data Caching Strategy
Cache weather by **station code** (not RWS location ID) to avoid duplicate fetches when multiple RWS locations map to the same KNMI station.

```
Cache key: knmi_weather_{station_code}
TTL: 10 minutes (weather data updates hourly)
```

## Data Flow

### RefreshLocations Command
1. Fetch RWS locations (existing)
2. **NEW:** Fetch KNMI stations from metadata
3. Save RWS locations to `var/data/locations.json`
4. **NEW:** Save KNMI stations to `var/data/knmi-stations.json`

### GetConditionsForLocation
1. Get RWS location by ID
2. Fetch water conditions from RWS (existing)
3. **NEW:** Use KnmiStationMatcher to find matching station
4. **NEW:** Fetch weather from matched station (or return null if no match)
5. Return combined conditions

## KNMI Station List
KNMI has ~50 automatic weather stations. Key coastal stations:
- 210 Valkenburg (near Scheveningen)
- 235 De Kooy (North Holland)
- 310 Vlissingen (Zeeland)
- 330 Hoek van Holland
- 240 Schiphol (inland reference)

Since KNMI doesn't provide a stations API, we'll hardcode the main coastal stations initially with option to expand later.

## Trade-offs

### Station Matching: Name vs. Coordinates
**Chosen: Name-based fuzzy matching**
- Pros: Semantically meaningful, handles Dutch coastal naming conventions
- Cons: May fail for unusual names

**Alternative: Coordinate-based nearest neighbor**
- Pros: Always finds a station, geographically accurate
- Cons: May match inland station for coastal location (weather differs significantly)

### Caching Granularity: Per-station vs. Per-location
**Chosen: Per-station**
- Pros: Fewer API calls, consistent data for same station
- Cons: Requires station-location mapping lookup

## Error Handling
- If KNMI API fails: Return stale cached data
- If no station match: Return null for weather (dashboard shows "Weather unavailable")
- If station has no data: Return null (some stations don't report all variables)
