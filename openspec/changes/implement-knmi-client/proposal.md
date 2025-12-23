# Proposal: Implement KNMI Client

## Summary
Implement a proper KNMI weather data client using the daggegevens.knmi.nl API, with station caching and fuzzy name matching to link RWS water locations with KNMI weather stations.

## Motivation
The current KnmiHttpClient is a stub that uses a fake API endpoint. The real KNMI API is station-based (not lat/lon based) and uses POST requests with form-encoded data. We need to:
1. Fetch and cache KNMI weather stations
2. Match RWS locations to KNMI stations by fuzzy name matching
3. Fetch current weather conditions from the matched station

## Scope
- **In scope:**
  - Rewrite KnmiHttpClient to use real KNMI hourly data API (`/klimatologie/uurgegevens`)
  - Add KnmiHttpClient method to fetch list of KNMI stations
  - Create KnmiStationRepository to cache stations in JSON file
  - Create domain service for fuzzy name matching between RWS locations and KNMI stations
  - Update RefreshLocations to also refresh KNMI stations
  - Update FetchAllData to pre-fetch weather data for all matched stations
  - Display current weather conditions (temperature, wind, humidity) on dashboard

- **Out of scope:**
  - Daily aggregates or forecasts
  - UV index (not available in KNMI hourly data)
  - Precipitation forecasts

## Approach
1. **Station Storage:** Store KNMI stations in separate `var/data/knmi-stations.json` file
2. **Name Matching:** Fuzzy match RWS location names to KNMI station names, accounting for:
   - RWS names often have suffixes (e.g., "Scheveningen buitenhaven" → "Scheveningen")
   - Dots and special characters in names
   - Case-insensitive matching
3. **Weather Data:** Fetch hourly data for current hour from matched station
4. **Caching:** Cache weather data per station (not per RWS location) to avoid duplicate fetches

## Impact
- **Files affected:**
  - `src/Infrastructure/ExternalApi/Client/KnmiHttpClient.php` (rewrite)
  - `src/Infrastructure/ExternalApi/Client/KnmiHttpClientInterface.php` (extend)
  - `src/Infrastructure/ExternalApi/KnmiAdapter.php` (update)
  - `src/Application/UseCase/RefreshLocations.php` (extend)
  - `src/Application/UseCase/FetchAllData.php` (minor update)
  - New: `src/Infrastructure/Repository/JsonFileKnmiStationRepository.php`
  - New: `src/Application/Port/KnmiStationRepositoryInterface.php`
  - New: `src/Domain/Service/KnmiStationMatcher.php`
  - New: `src/Domain/ValueObject/KnmiStation.php`
- **Breaking changes:** None (existing stub wasn't functional)
- **Dependencies:** None (KNMI API is public, no auth required)
