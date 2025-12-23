# Tasks: Remember Selected Location

## Implementation Tasks

- [x] **1. Save location to localStorage on selection**
  - In `App.vue`, update `selectLocation()` to save `location.id` to localStorage when a location is selected
  - Use key `seaswim:selectedLocationId`

- [x] **2. Clear localStorage on selection clear**
  - In `App.vue`, update `selectLocation()` to remove the localStorage entry when `location` is `null` (user pressed X)

- [x] **3. Restore location on page load**
  - In `App.vue`, after `fetchLocations()` completes successfully, check localStorage for a saved location ID
  - If found and the location exists in the fetched list, call `selectLocation()` with that location
  - If the saved location ID no longer exists in the list (location removed), clear the invalid entry

## Validation

- [x] **4. Manual testing**
  - Select a location → refresh page → location should be restored
  - Select a location → press X → refresh page → no location should be selected
  - Select a location → verify localStorage contains the ID
  - Press X → verify localStorage entry is removed
