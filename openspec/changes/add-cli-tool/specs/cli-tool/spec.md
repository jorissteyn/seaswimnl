## ADDED Requirements

### Requirement: CLI Wrapper Script
The system SHALL provide a `bin/seaswim` executable script for invoking CLI commands.

#### Scenario: Execute CLI command
- **WHEN** running `bin/seaswim <command>`
- **THEN** the corresponding Symfony console command is executed
- **AND** output is displayed to stdout

#### Scenario: Show help
- **WHEN** running `bin/seaswim` without arguments or with `--help`
- **THEN** available commands are listed with descriptions

### Requirement: Conditions Command
The system SHALL provide a `conditions` command to display water and weather conditions for a location.

#### Scenario: Show conditions for location
- **WHEN** running `bin/seaswim conditions <location>`
- **THEN** water conditions (temperature, wave height, water height, quality) are displayed
- **AND** weather conditions (wind, UV index, air temperature) are displayed
- **AND** data timestamps are shown

#### Scenario: Location not found
- **WHEN** running `bin/seaswim conditions <unknown-location>`
- **THEN** an error message is displayed
- **AND** exit code is non-zero

#### Scenario: JSON output
- **WHEN** running `bin/seaswim conditions <location> --json`
- **THEN** conditions are output as JSON

### Requirement: Locations Refresh Command
The system SHALL provide a `locations refresh` command to update swim locations from RWS data.

#### Scenario: Refresh locations
- **WHEN** running `bin/seaswim locations refresh`
- **THEN** locations are fetched from Rijkswaterstaat
- **AND** locations are stored in `var/data/locations.json`
- **AND** a summary is displayed (count of locations updated)

#### Scenario: Refresh fails
- **WHEN** running `bin/seaswim locations refresh` and the API is unavailable
- **THEN** an error message is displayed
- **AND** existing locations are preserved
- **AND** exit code is non-zero

### Requirement: Fetch Command
The system SHALL provide a `fetch` command to trigger data retrieval from external APIs.

#### Scenario: Fetch data
- **WHEN** running `bin/seaswim fetch`
- **THEN** water and weather data is fetched for all known locations
- **AND** responses are cached
- **AND** a summary is displayed

#### Scenario: Force fetch ignoring cache
- **WHEN** running `bin/seaswim fetch --force`
- **THEN** data is fetched even if cache is valid
- **AND** cache is updated with fresh data

### Requirement: Cache Clear Command
The system SHALL provide a `cache:clear` command to remove cached API responses.

#### Scenario: Clear cache
- **WHEN** running `bin/seaswim cache:clear`
- **THEN** all cached API responses are deleted
- **AND** confirmation message is displayed

#### Scenario: Clear cache when empty
- **WHEN** running `bin/seaswim cache:clear` with no cached data
- **THEN** a message indicates cache was already empty

### Requirement: Output Formatting
The system SHALL support multiple output formats for CLI commands.

#### Scenario: Default table output
- **WHEN** running a command without format flags
- **THEN** output is displayed as a human-readable table

#### Scenario: JSON output
- **WHEN** running a command with `--json` flag
- **THEN** output is formatted as JSON

#### Scenario: Quiet mode
- **WHEN** running a command with `--quiet` flag
- **THEN** only errors are displayed
- **AND** success/failure is indicated by exit code only
