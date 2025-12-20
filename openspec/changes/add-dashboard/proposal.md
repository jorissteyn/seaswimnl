# Change: Add Dashboard

## Why
Users need a visual web interface to view water and weather conditions for swim locations. The dashboard provides an accessible way to check conditions before heading out for a swim.

## What Changes
- Create Vue.js 3 single-page application for the dashboard
- Implement location selector dropdown
- Display water conditions (temperature, wave height, water height, quality)
- Display weather conditions (wind, UV index, air temperature)
- Create Symfony controller to serve the dashboard and API endpoints
- Style with minimal CSS (no framework)

## Impact
- Affected specs: `dashboard` (new capability)
- Affected code: `assets/`, `src/Infrastructure/Controller/`, `templates/`
- Dependencies: Requires `add-project-setup` and `add-core-data-retrieval` to be completed first
- No breaking changes (new capability)
