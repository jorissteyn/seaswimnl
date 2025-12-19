## ADDED Requirements

### Requirement: Water Conditions Retrieval
The system SHALL fetch water conditions from Rijkswaterstaat for a given location.

#### Scenario: Fetch water temperature
- **WHEN** requesting water conditions for a location
- **THEN** the water temperature is returned in Celsius
- **AND** the measurement timestamp is included

#### Scenario: Fetch wave height
- **WHEN** requesting water conditions for a coastal location
- **THEN** the wave height is returned in meters

#### Scenario: Fetch water height
- **WHEN** requesting water conditions for a location
- **THEN** the water height (tide level) is returned in meters relative to NAP

#### Scenario: Fetch water quality
- **WHEN** requesting water conditions for a location
- **THEN** the water quality status is returned (Good, Moderate, Poor, or Unknown)

#### Scenario: Handle unavailable data
- **WHEN** water data is unavailable from the API
- **THEN** null values are returned for unavailable fields
- **AND** an error is logged

### Requirement: Weather Conditions Retrieval
The system SHALL fetch weather conditions from KNMI for a given location.

#### Scenario: Fetch wind data
- **WHEN** requesting weather conditions for a location
- **THEN** wind speed and direction are returned

#### Scenario: Fetch UV index
- **WHEN** requesting weather conditions for a location
- **THEN** the UV index is returned on the standard 0-11+ scale

#### Scenario: Fetch air temperature
- **WHEN** requesting weather conditions for a location
- **THEN** the air temperature is returned in Celsius

#### Scenario: Handle unavailable weather data
- **WHEN** weather data is unavailable from the API
- **THEN** null values are returned for unavailable fields
- **AND** an error is logged

### Requirement: API Response Caching
The system SHALL cache API responses using Symfony Cache with a filesystem adapter.

#### Scenario: Cache water conditions
- **WHEN** water conditions are fetched from the API
- **THEN** the response is cached with a 15-minute TTL

#### Scenario: Cache weather conditions
- **WHEN** weather conditions are fetched from the API
- **THEN** the response is cached with a 30-minute TTL

#### Scenario: Return cached data
- **WHEN** requesting conditions within the cache TTL
- **THEN** cached data is returned without making an API call

#### Scenario: Serve stale cache on API failure
- **WHEN** the API is unavailable
- **AND** cached data exists (even if expired)
- **THEN** the stale cached data is returned
- **AND** the data is marked with its actual timestamp

### Requirement: Domain Entities
The system SHALL represent water and weather conditions as domain entities with value objects.

#### Scenario: Water conditions entity
- **WHEN** water data is retrieved
- **THEN** it is represented as a WaterConditions entity
- **AND** temperature, wave height, water height, and water quality are value objects

#### Scenario: Weather conditions entity
- **WHEN** weather data is retrieved
- **THEN** it is represented as a WeatherConditions entity
- **AND** wind speed, UV index, and temperature are value objects

#### Scenario: Location value object
- **WHEN** specifying a location
- **THEN** it is represented as a Location value object with coordinates and name

### Requirement: API Configuration
The system SHALL configure external APIs via YAML configuration and environment variables.

#### Scenario: YAML configuration for API settings
- **WHEN** configuring the external APIs
- **THEN** static settings (timeouts, cache TTLs) are defined in `config/packages/seaswim.yaml`

#### Scenario: Environment variables for credentials
- **WHEN** configuring API credentials and URLs
- **THEN** sensitive values (API keys, base URLs) are read from environment variables
- **AND** default values are provided in `.env`

#### Scenario: Configuration is injectable
- **WHEN** adapters need configuration values
- **THEN** values are injected via Symfony's configuration system

### Requirement: Provider Ports
The system SHALL define ports (interfaces) for data providers in the Application layer.

#### Scenario: Water conditions port
- **WHEN** the application needs water data
- **THEN** it calls WaterConditionsProviderInterface
- **AND** the implementation is injected via dependency injection

#### Scenario: Weather conditions port
- **WHEN** the application needs weather data
- **THEN** it calls WeatherConditionsProviderInterface
- **AND** the implementation is injected via dependency injection
