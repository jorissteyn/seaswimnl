# Seaswim

Water condition data for sea swimming locations in the Netherlands.

## Overview

Seaswim provides real-time water and weather conditions for swimming locations along the Dutch coast. It helps swimmers make informed decisions about when and where to swim.

**Features:**
- Dashboard for viewing water conditions at swim locations
- CLI tool for quick access to conditions data
- API for wearable devices (smartwatches, fitness trackers)

## Tech Stack

- **Backend:** PHP 8.4, Symfony 7.4
- **Frontend:** Vue.js 3, Webpack Encore
- **API:** API Platform
- **Local Development:** DDEV
- **Code Quality:** PHP-CS-Fixer, Psalm, PHPUnit

## Prerequisites

- [DDEV](https://ddev.readthedocs.io/en/stable/) installed
- Docker running

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

## CLI Tool

```bash
# Show conditions for a location
ddev exec bin/seaswim seaswim:conditions <location-id>

# Show conditions as JSON
ddev exec bin/seaswim seaswim:conditions <location-id> --json

# Refresh location data from Rijkswaterstaat
ddev exec bin/seaswim seaswim:locations:refresh

# Fetch fresh data for all locations
ddev exec bin/seaswim seaswim:fetch

# Clear API cache
ddev exec bin/seaswim seaswim:cache:clear

# List all commands
ddev exec bin/seaswim list seaswim
```

## Environment Variables

Copy `.env` to `.env.local` and configure:

```bash
# Rijkswaterstaat API (WaterWebservices)
RWS_API_URL=https://ddapi20-waterwebservices.rijkswaterstaat.nl

# KNMI API (Open Data Platform)
KNMI_API_URL=https://api.dataplatform.knmi.nl/open-data/v1
KNMI_API_KEY=your-api-key-here
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

### KNMI Open Data

Weather data is fetched from the KNMI Open Data Platform:
- Air temperature
- Wind speed and direction
- UV index

## Caching

API responses are cached using Symfony Cache (filesystem adapter):
- Water conditions: 15 minutes TTL
- Weather conditions: 30 minutes TTL
- Stale data is served if the API is unavailable

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

### Rijkswaterstaat Data Sources
- [Data Overheid - Waterinfo Extra](https://waterinfo-extra.rws.nl/data-sites/data-overheid-nl/)
- [Rijkswaterstaat Waterdata](https://rijkswaterstaatdata.nl/waterdata/)
- [Water Web Services Discussions](https://github.com/Rijkswaterstaat/WaterWebservices/discussions)
