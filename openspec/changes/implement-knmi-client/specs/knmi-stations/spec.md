# Capability: KNMI Station Storage

Store and retrieve KNMI weather station metadata for use in weather data fetching.

## ADDED Requirements

### Requirement: Store KNMI stations
The system MUST persist KNMI weather stations to a JSON file for later retrieval.

#### Scenario: Stations are saved during refresh
- **Given** the RefreshLocations command is executed
- **When** KNMI stations are fetched from the API
- **Then** the stations are saved to `var/data/knmi-stations.json`
- **And** each station includes code, name, latitude, and longitude

### Requirement: Retrieve KNMI stations
The system MUST provide access to stored KNMI stations by code or as a full list.

#### Scenario: Find station by code
- **Given** KNMI stations have been refreshed
- **When** requesting station with code "260"
- **Then** the system returns the station for De Bilt

#### Scenario: List all stations
- **Given** KNMI stations have been refreshed
- **When** requesting all stations
- **Then** the system returns the complete list of stored stations
