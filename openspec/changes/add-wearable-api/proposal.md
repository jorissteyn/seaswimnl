# Change: Add Wearable API

## Why
Wearable devices (smartwatches, fitness trackers) need a REST API to fetch water and weather conditions. The API should be lightweight and optimized for low-bandwidth devices.

## What Changes
- Install and configure API Platform
- Create DTO classes for API responses (LocationOutput, ConditionsOutput)
- Implement custom operations for locations and conditions endpoints
- Expose public API at `/api/v1/` prefix
- No authentication required (public API)

## Impact
- Affected specs: `wearable-api` (new capability)
- Affected code: `src/Infrastructure/ApiPlatform/`, `config/packages/api_platform.yaml`
- Dependencies: Requires `add-project-setup` and `add-core-data-retrieval` to be completed first
- No breaking changes (new capability)
