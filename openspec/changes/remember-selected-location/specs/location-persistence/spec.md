# Capability: Location Persistence

Persist the user's selected swim location in the browser's localStorage.

## ADDED Requirements

### Requirement: Save selected location
The dashboard MUST save the selected location ID to localStorage when a user selects a location.

#### Scenario: User selects a location
- **Given** the user is on the dashboard
- **When** the user selects "Scheveningen" from the location dropdown
- **Then** the location ID is saved to localStorage under key `seaswim:selectedLocationId`

### Requirement: Restore selected location on load
The dashboard MUST restore the previously selected location when the page loads.

#### Scenario: User revisits dashboard with saved location
- **Given** localStorage contains a saved location ID for "Scheveningen"
- **And** "Scheveningen" exists in the available locations
- **When** the page loads
- **Then** "Scheveningen" is automatically selected
- **And** the conditions for "Scheveningen" are fetched

#### Scenario: Saved location no longer exists
- **Given** localStorage contains a saved location ID that no longer exists
- **When** the page loads
- **Then** no location is selected
- **And** the invalid localStorage entry is removed

### Requirement: Clear saved location
The dashboard MUST clear the saved location from localStorage when the user explicitly clears their selection.

#### Scenario: User clears selection using X button
- **Given** a location is currently selected
- **When** the user clicks the X (clear) button in the search bar
- **Then** the localStorage entry is removed
- **And** no location is selected
