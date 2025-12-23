# Capability: KNMI Station Matching

Match RWS water measurement locations to the most appropriate KNMI weather station using fuzzy name matching.

## ADDED Requirements

### Requirement: Match location to station by name
The system MUST find the best matching KNMI station for a given RWS location using fuzzy name matching.

#### Scenario: Exact first-word match
- **Given** RWS location "Vlissingen havenmond"
- **And** KNMI station "Vlissingen" exists
- **When** finding matching station
- **Then** station "Vlissingen" (code 310) is returned

#### Scenario: Match with normalized names
- **Given** RWS location "Hoek.v" (with dot abbreviation)
- **And** KNMI station "Hoek van Holland" exists
- **When** finding matching station
- **Then** station "Hoek van Holland" (code 330) is returned

### Requirement: Handle unmatched locations
The system MUST gracefully handle RWS locations that cannot be matched to a KNMI station.

#### Scenario: No matching station found
- **Given** RWS location "Offshore platform" with no name similarity to any KNMI station
- **When** finding matching station
- **Then** null is returned
- **And** the dashboard shows "Weather unavailable" for that location

### Requirement: Account for RWS naming conventions
The system MUST handle common RWS location name patterns when matching.

#### Scenario: Location with directional suffix
- **Given** RWS location "Scheveningen buitenhaven zuidelijk"
- **When** extracting the base location name
- **Then** "Scheveningen" is used for matching

#### Scenario: Location with measurement type suffix
- **Given** RWS location "IJmuiden stroommeetpaal"
- **When** extracting the base location name
- **Then** "IJmuiden" is used for matching
