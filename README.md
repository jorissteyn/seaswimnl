# Seaswim NL

Smart swimming conditions dashboard for the Dutch coast.

## Overview

The Seaswim NL dashboard aggregates data from multiple buoys, measurement stations, and weather services to provide real-time swimming conditions for locations along the Dutch coast. The system intelligently combines water temperature, wave height, tide levels, and weather data into actionable swimming recommendations.

**Data Sources:**
- **Rijkswaterstaat Waterdata** - Water temperature, wave height, and tide levels from coastal measurement stations and buoys
- **Buienradar** - Real-time weather conditions (air temperature, wind, humidity)
- **Basisregistratie Grootschalige Topografie (BGT)** - Water body classification for matching stations to swim locations

**Features:**
- Dashboard combining multi-source data into clear swimming conditions
- Smart station matching (finds nearest relevant buoys/stations per location)
- CLI tool for quick access to conditions data

## Dashboard

The web dashboard provides an interactive view of swimming conditions:

- **Spot selector** - Choose from configured swimming locations (selection persisted in localStorage)
- **Tide graph** - Visual SVG graph showing tide cycle with current position marker
- **Conditions cards** - Water temperature, wave height, weather, and calculated safety metrics
- **Tooltips** - Hover for additional context on measurements

## Tech Stack

- **Backend:** PHP 8.4, Symfony 7.4
- **Frontend:** Vue.js 3, Webpack Encore
- **API:** API Platform
- **Local Development:** DDEV
- **Code Quality:** PHP-CS-Fixer, Psalm, PHPUnit
- **Storage:** File-based (JSON, CSV) - no database required
- **AI Development:** [OpenSpec](https://github.com/openspec-dev/openspec) for spec-driven changes

## Prerequisites

- [DDEV](https://ddev.readthedocs.io/en/stable/) installed
- Docker running
- Node.js 20+ (for frontend builds outside DDEV)

## Quick Start

```bash
# Start the development environment
make start

# Install dependencies
make install

# Build frontend assets
make build
```

The application will be available at the URL shown by DDEV (typically https://seaswim.ddev.site).

## Development Commands

```bash
make start      # Start DDEV environment
make stop       # Stop DDEV environment
make install    # Install dependencies (composer + npm)
make test       # Run PHPUnit tests
make lint       # Check code style with PHP-CS-Fixer
make lint-fix   # Fix code style issues
make analyse    # Run Psalm static analysis
make build      # Build frontend assets
make watch      # Watch frontend assets for changes
make ci         # Run all CI checks
make clean      # Clear caches
```

## CI Pipeline

GitHub Actions runs on push/PR to main branches:

| Job | Description |
|-----|-------------|
| **lint** | PHP-CS-Fixer code style check |
| **analyse** | Psalm static analysis |
| **test** | PHPUnit tests |
| **build** | Frontend asset compilation |

## REST API

The application exposes REST endpoints for accessing swimming conditions:

| Endpoint | Description |
|----------|-------------|
| `GET /api/swimming-spots` | List all swimming spots |
| `GET /api/conditions/{spot}` | Get conditions for a swimming spot |
| `GET /api/locations` | List all RWS measurement locations |
| `GET /api/measurements/{spotId}` | Get raw measurements for a spot |
| `GET /api/measurement-codes` | List available measurement codes |

## CLI Tool

```bash
# Show conditions for a location
ddev exec bin/seaswim seaswim:conditions <location-id>

# Show conditions as JSON
ddev exec bin/seaswim seaswim:conditions <location-id> --json

# Refresh location data from Rijkswaterstaat
ddev exec bin/seaswim seaswim:locations:refresh

# Clear API cache
ddev exec bin/seaswim seaswim:cache:clear

# List all commands
ddev exec bin/seaswim list seaswim
```

### Location Management Commands

```bash
# List all locations (RWS stations and weather stations)
ddev exec bin/seaswim seaswim:locations:list

# Filter by water type (sea, lake, river)
ddev exec bin/seaswim seaswim:locations:list --water-type=sea

# Filter by measurement capability (Hm0=waves, T=temperature, WATHTE=water height)
ddev exec bin/seaswim seaswim:locations:list --filter=Hm0

# Find nearest station with a specific capability
ddev exec bin/seaswim seaswim:locations:nearest-station <location-id> --capability=Hm0

# Debug RWS API responses for a location
ddev exec bin/seaswim seaswim:rws:debug <location-id>

# Classify water types using BGT data
ddev exec bin/seaswim seaswim:locations:classify-water-type
```

## Environment Variables

Copy `.env` to `.env.local` and configure:

```bash
# Rijkswaterstaat API (WaterWebservices)
RWS_API_URL=https://ddapi20-waterwebservices.rijkswaterstaat.nl

# Buienradar API
BUIENRADAR_API_URL=https://data.buienradar.nl/2.0/feed/json
```

## API Endpoints Used

### Rijkswaterstaat WaterWebservices

The application fetches water data from the Rijkswaterstaat WaterWebservices API:

- **POST `/METADATASERVICES/OphalenCatalogus`** - Get catalog of locations and measurements
- **POST `/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen`** - Get latest observations

Data retrieved:
- Water temperature (`T` - Compartiment OW)
- Water height/tide level (`WATHTE` - relative to NAP)
- Wave height (`Hm0`)

See [RWS.md](RWS.md) for detailed API documentation.

### Buienradar

Weather data is fetched from the Buienradar JSON feed (`https://data.buienradar.nl/2.0/feed/json`):
- Air temperature
- Wind speed and direction (with Beaufort scale)
- Humidity

**Station Matching:** Buienradar stations are automatically matched to RWS locations using fuzzy name matching (Levenshtein distance). The `seaswim:locations:refresh` command fetches both RWS locations and Buienradar stations.

## Caching

API responses are cached using Symfony Cache (filesystem adapter) stored in `var/cache/`:

| Data Type | Cache TTL | Stale Fallback |
|-----------|-----------|----------------|
| Water conditions | 1 hour | 4 hours |
| Weather conditions | 1 hour | 4 hours |
| Tidal predictions | 1 hour | 4 hours |

**How caching works:**
1. Fresh data is fetched from the API and cached
2. Subsequent requests within TTL return cached data (no API call)
3. When TTL expires, fresh data is fetched
4. If API fails, stale cached data is served (up to 4x TTL)
5. Specific error messages are shown when APIs fail

**Cache commands:**
```bash
# Clear all API caches
ddev exec bin/seaswim seaswim:cache:clear
```

## Swim Metrics

The application calculates swim metrics from raw data:

### Safety Score (Traffic Light)
| Score | Meaning | Triggers |
|-------|---------|----------|
| Green | Safe | All conditions within safe thresholds |
| Yellow | Caution | Water temp 10-15°C, waves 1-2m, moderate quality, wind 20-40 km/h |
| Red | Unsafe | Water temp <10°C, waves >2m, poor quality, wind >40 km/h |

A single red condition triggers a red score. Any yellow (no red) triggers yellow.

### Comfort Index (1-10)
Weighted average of conditions:
- Water temperature: 40% (optimal 18-22°C)
- Air temperature: 20% (optimal 20-25°C)
- Wind speed: 20% (optimal <10 km/h)
- UV index: 10% (optimal 3-5)
- Wave height: 10% (optimal <0.5m)

### Swim Recommendation
Based on safety score and comfort index:
- **Now** - Safe conditions with good comfort
- **Later today** - Marginal conditions, may improve
- **Not recommended** - Unsafe conditions

## Data Files

### Swimming Spots (`data/swimming-spots.csv`)

CSV file defining swimming locations. Each row maps a named spot to coordinates:

```csv
name,latitude,longitude
Den Helder,52.9672,4.7936
Vlissingen,51.4420,3.6000
```

The system finds the nearest RWS stations for each spot to provide water conditions.

### Location Blacklist (`data/blacklist.txt`)

Stations excluded from matching due to stale or missing data. Generated by `seaswim:locations:scan-stale`. Format:

```
# Comment lines start with #
station.id.here
another.station
```

Blacklisted stations are hidden from `seaswim:locations:list` by default (use `--show-blacklisted` to include).

## Project Structure

```
src/
├── Domain/           # Core business logic (no framework dependencies)
│   ├── Entity/
│   └── ValueObject/
├── Application/      # Use cases, ports (interfaces), DTOs
│   ├── Port/
│   └── UseCase/
└── Infrastructure/   # Adapters (Symfony, external APIs)
    ├── Controller/
    ├── Console/
    ├── Cache/
    └── ExternalApi/
```

## References

### Data Sources
- [Rijkswaterstaat Waterdata](https://rijkswaterstaatdata.nl/waterdata/) - Water measurements
- [Buienradar API](https://data.buienradar.nl/2.0/feed/json) - Weather data
- [PDOK BGT](https://www.pdok.nl/introductie/-/article/basisregistratie-grootschalige-topografie-bgt-) - Water body classification

### Rijkswaterstaat API
- [Data Overheid - Waterinfo Extra](https://waterinfo-extra.rws.nl/data-sites/data-overheid-nl/)
- [Water Web Services Discussions](https://github.com/Rijkswaterstaat/WaterWebservices/discussions)
