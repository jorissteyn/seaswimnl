## ADDED Requirements

### Requirement: API Platform Integration
The system SHALL use API Platform to expose a REST API for wearable devices.

#### Scenario: API Platform configured
- **WHEN** the application boots
- **THEN** API Platform is available at `/api/v1/`
- **AND** OpenAPI documentation is generated

#### Scenario: JSON responses
- **WHEN** calling any API endpoint
- **THEN** responses are in JSON format
- **AND** appropriate Content-Type header is set

### Requirement: Locations Collection Endpoint
The system SHALL provide an endpoint to list all swim locations.

#### Scenario: Get all locations
- **WHEN** calling GET /api/v1/locations
- **THEN** a JSON array of locations is returned
- **AND** each location includes id, name, latitude, and longitude

#### Scenario: Empty locations
- **WHEN** no locations are configured
- **THEN** an empty array is returned

### Requirement: Single Location Endpoint
The system SHALL provide an endpoint to get a single location by ID.

#### Scenario: Get location by ID
- **WHEN** calling GET /api/v1/locations/{id}
- **THEN** the location details are returned as JSON

#### Scenario: Location not found
- **WHEN** calling GET /api/v1/locations/{unknown-id}
- **THEN** a 404 response is returned
- **AND** error details are in JSON format

### Requirement: Conditions Endpoint
The system SHALL provide an endpoint to get conditions for a location.

#### Scenario: Get conditions
- **WHEN** calling GET /api/v1/conditions/{location}
- **THEN** water and weather conditions are returned as JSON
- **AND** an updatedAt timestamp is included

#### Scenario: Conditions include water data
- **WHEN** conditions are returned
- **THEN** water temperature, wave height, water height, and quality are included

#### Scenario: Conditions include weather data
- **WHEN** conditions are returned
- **THEN** wind speed, wind direction, UV index, and air temperature are included

#### Scenario: Location not found for conditions
- **WHEN** calling GET /api/v1/conditions/{unknown-location}
- **THEN** a 404 response is returned

### Requirement: DTO Output
The system SHALL use DTOs as API response objects, not domain entities.

#### Scenario: Location output DTO
- **WHEN** returning location data
- **THEN** LocationOutput DTO is serialized to JSON

#### Scenario: Conditions output DTO
- **WHEN** returning conditions data
- **THEN** ConditionsOutput DTO with nested water/weather DTOs is serialized

### Requirement: Cache Headers
The system SHALL include cache headers in API responses.

#### Scenario: Cache-Control header
- **WHEN** calling any API endpoint
- **THEN** Cache-Control header is set with max-age
- **AND** wearable devices can cache responses appropriately

### Requirement: OpenAPI Documentation
The system SHALL generate OpenAPI documentation automatically.

#### Scenario: OpenAPI spec available
- **WHEN** calling GET /api/v1/docs.json
- **THEN** OpenAPI 3.0 specification is returned

#### Scenario: Swagger UI available
- **WHEN** navigating to /api/v1/docs
- **THEN** Swagger UI is displayed for interactive testing
