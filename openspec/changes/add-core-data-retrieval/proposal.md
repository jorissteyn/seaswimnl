# Change: Add Core Data Retrieval

## Why
Seaswim needs to fetch real-time water and weather conditions from Dutch government data sources to provide swimmers with accurate, up-to-date information for their swimming decisions.

## What Changes
- Integrate Rijkswaterstaat API for water data (temperature, wave height, water height, currents, water quality)
- Integrate KNMI API for weather data (wind, UV index, air temperature)
- Implement Symfony Cache with filesystem adapter for API response caching
- Create domain entities for water conditions and weather data
- Define ports (interfaces) for data retrieval in the Application layer
- Implement adapters for external APIs in the Infrastructure layer

## Impact
- Affected specs: `core-data-retrieval` (new capability)
- Affected code: `src/Domain/`, `src/Application/`, `src/Infrastructure/`
- Dependencies: Requires `add-project-setup` to be completed first
- No breaking changes (new capability)
