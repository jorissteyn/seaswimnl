# Design: Dashboard

## Context
The dashboard is a Vue.js single-page application that displays water and weather conditions for a selected swim location. It's a simple, focused interface without auto-refresh or multi-location views.

## Goals / Non-Goals

**Goals:**
- Display conditions for a single selected location
- Provide location selector to switch between locations
- Show water and weather data in a clear, readable format
- Keep the UI simple and fast-loading

**Non-Goals:**
- Multi-location overview/grid
- Auto-refresh or real-time updates
- Mobile app (web only)
- User accounts or personalization

## Decisions

### Frontend Architecture

```
assets/
├── app.js              # Vue.js entry point
├── components/
│   ├── App.vue              # Root component
│   ├── LocationSelector.vue # Dropdown to pick location
│   ├── ConditionsPanel.vue  # Main conditions display
│   ├── WaterConditions.vue  # Water data card
│   └── WeatherConditions.vue # Weather data card
└── styles/
    └── app.css         # Minimal custom CSS
```

**Rationale:** Simple component structure. No state management library needed for single-location view.

### API Endpoints

```
GET /api/locations              # List available locations
GET /api/conditions/{location}  # Get conditions for location
```

**Rationale:** Two endpoints cover all dashboard needs. JSON responses consumed by Vue.js.

### Backend Controllers

```
src/Infrastructure/Controller/
├── DashboardController.php    # Serves the SPA HTML page
└── Api/
    ├── LocationsController.php    # GET /api/locations
    └── ConditionsController.php   # GET /api/conditions/{location}
```

**Rationale:** Controllers are thin adapters calling Application layer use cases.

### Styling

- No CSS framework (keep bundle small)
- CSS custom properties for theming
- Mobile-responsive with simple media queries
- Minimal, clean design focused on readability

**Rationale:** Avoids framework bloat. Conditions data should be scannable at a glance.

### Data Flow

```
User selects location
  → Vue component calls /api/conditions/{location}
  → Controller calls GetConditionsForLocation use case
  → Use case calls ports (WaterConditionsProvider, WeatherConditionsProvider)
  → Data returned as JSON
  → Vue component renders conditions
```

**Rationale:** Follows hexagonal architecture. Frontend is just another adapter.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| Stale data displayed | Show "last updated" timestamp prominently |
| API errors | Display user-friendly error message in UI |

## Open Questions
- None currently
