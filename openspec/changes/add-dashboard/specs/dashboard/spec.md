## ADDED Requirements

### Requirement: Dashboard Page
The system SHALL serve a dashboard web page as a Vue.js single-page application.

#### Scenario: Load dashboard
- **WHEN** navigating to the root URL (/)
- **THEN** the dashboard HTML page is served
- **AND** Vue.js application is initialized

#### Scenario: Dashboard displays location selector
- **WHEN** the dashboard loads
- **THEN** a location selector dropdown is visible
- **AND** available locations are populated from the API

### Requirement: Location Selection
The system SHALL allow users to select a swim location from a dropdown.

#### Scenario: Select location
- **WHEN** user selects a location from the dropdown
- **THEN** conditions for that location are fetched from the API
- **AND** conditions are displayed in the panel

#### Scenario: No location selected
- **WHEN** no location is selected
- **THEN** a prompt is shown to select a location

### Requirement: Conditions Display
The system SHALL display water and weather conditions for the selected location.

#### Scenario: Display water conditions
- **WHEN** conditions are loaded for a location
- **THEN** water temperature is displayed in Celsius
- **AND** wave height is displayed in meters
- **AND** water height is displayed in meters
- **AND** water quality status is displayed

#### Scenario: Display weather conditions
- **WHEN** conditions are loaded for a location
- **THEN** wind speed and direction are displayed
- **AND** UV index is displayed
- **AND** air temperature is displayed in Celsius

#### Scenario: Display data timestamp
- **WHEN** conditions are displayed
- **THEN** the "last updated" timestamp is shown

#### Scenario: Handle missing data
- **WHEN** some condition values are unavailable
- **THEN** unavailable values show "N/A" or similar indicator

### Requirement: Locations API Endpoint
The system SHALL provide an API endpoint to list available swim locations.

#### Scenario: Get locations list
- **WHEN** calling GET /api/locations
- **THEN** a JSON array of locations is returned
- **AND** each location includes id and name

### Requirement: Conditions API Endpoint
The system SHALL provide an API endpoint to get conditions for a location.

#### Scenario: Get conditions for location
- **WHEN** calling GET /api/conditions/{location}
- **THEN** JSON with water and weather conditions is returned
- **AND** timestamps are included

#### Scenario: Location not found
- **WHEN** calling GET /api/conditions/{unknown-location}
- **THEN** a 404 response is returned
- **AND** error message is in JSON format

### Requirement: Error Handling
The system SHALL display user-friendly error messages when API calls fail.

#### Scenario: API error
- **WHEN** an API call fails
- **THEN** an error message is displayed in the UI
- **AND** the user can retry

#### Scenario: Network error
- **WHEN** the network is unavailable
- **THEN** a connection error message is displayed
