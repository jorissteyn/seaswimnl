# Proposal: Remember Selected Location

## Summary
Persist the user's last selected swim location in localStorage so it's automatically restored when they revisit the dashboard. Allow clearing the remembered location by pressing the clear button (X) in the search bar.

## Motivation
Users who regularly check conditions for the same location shouldn't need to re-select it each time they visit the dashboard. This improves the user experience by reducing repetitive interactions.

## Scope
- **In scope:** localStorage persistence of selected location ID, restore on page load, clear on explicit user action
- **Out of scope:** Server-side persistence, multi-location history, location favorites

## Approach
1. Save the selected location ID to localStorage when a location is selected
2. On page load, after fetching locations, restore the previously selected location if it exists in the available locations list
3. Remove the localStorage entry when the user clears the selection using the X button

## Impact
- **Files affected:** `assets/components/App.vue`
- **Breaking changes:** None
- **Dependencies:** None (localStorage is a browser standard)
