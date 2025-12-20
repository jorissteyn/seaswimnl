# Change: Add CLI Tool

## Why
Users need a command-line interface to check water conditions, manage swim locations, and control data retrieval. The CLI serves as a primary interface for power users and enables scripting/automation.

## What Changes
- Create `bin/seaswim` standalone CLI wrapper
- Implement `conditions <location>` command to display current water/weather data
- Implement `locations refresh` command to refresh locations from RWS data source
- Implement `fetch` command to manually trigger data retrieval from APIs
- Implement `cache:clear` command to clear cached API responses
- Use Symfony Console component for command infrastructure

## Impact
- Affected specs: `cli-tool` (new capability)
- Affected code: `src/Infrastructure/Console/`, `bin/seaswim`
- Dependencies: Requires `add-project-setup` and `add-core-data-retrieval` to be completed first
- No breaking changes (new capability)
