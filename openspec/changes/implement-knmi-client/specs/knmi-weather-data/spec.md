# Capability: KNMI Weather Data

Fetch and provide current weather conditions from KNMI weather stations.

## ADDED Requirements

### Requirement: Fetch hourly weather data
The system MUST fetch hourly weather data from the KNMI daggegevens API.

#### Scenario: Fetch current weather for station
- **Given** a valid KNMI station code "260"
- **When** requesting weather data for the current hour
- **Then** the system fetches data from `https://www.daggegevens.knmi.nl/klimatologie/uurgegevens`
- **And** returns temperature, wind speed, wind direction, and humidity

### Requirement: Normalize KNMI data units
The system MUST convert KNMI's 0.1-base units to standard units.

#### Scenario: Temperature conversion
- **Given** KNMI returns temperature value 145
- **When** the data is normalized
- **Then** the temperature is 14.5°C

#### Scenario: Wind speed conversion
- **Given** KNMI returns wind speed value 35
- **When** the data is normalized
- **Then** the wind speed is 3.5 m/s

### Requirement: Cache weather data by station
The system MUST cache weather data per station code to avoid duplicate API calls.

#### Scenario: Multiple locations same station
- **Given** RWS locations "Scheveningen" and "IJmuiden" both map to KNMI station "210"
- **When** weather is requested for both locations
- **Then** only one API call is made to KNMI
- **And** both locations receive the same cached weather data
