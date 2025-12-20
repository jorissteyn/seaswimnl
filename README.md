# Seaswim

Water condition data for sea swimming locations in the Netherlands.

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

## Environment Variables

Copy `.env` to `.env.local` and configure:

```bash
# Rijkswaterstaat API
RWS_API_URL=https://waterinfo.rws.nl/api

# KNMI API
KNMI_API_URL=https://api.dataplatform.knmi.nl/open-data/v1
KNMI_API_KEY=your-api-key-here
```

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
