# Design: Wearable API

## Context
Wearable devices need a REST API to fetch swim conditions. API Platform provides a robust foundation for building RESTful APIs with automatic OpenAPI documentation. Using DTOs as output ensures clean separation from domain entities.

## Goals / Non-Goals

**Goals:**
- Provide REST API for wearable devices
- Use API Platform with custom operations
- Return DTOs (not domain entities) as output
- Automatic OpenAPI/Swagger documentation
- Public API (no authentication)

**Non-Goals:**
- GraphQL support
- WebSocket/real-time updates
- Device registration or push notifications
- Rate limiting (defer to infrastructure layer)

## Decisions

### API Platform Configuration

```yaml
# config/packages/api_platform.yaml
api_platform:
    title: 'Seaswim API'
    version: '1.0.0'
    defaults:
        stateless: true
        cache_headers:
            max_age: 300
            shared_max_age: 600
    formats:
        json: ['application/json']
```

**Rationale:** JSON-only API for simplicity. Cache headers help wearables reduce requests.

### API Endpoints

```
GET /api/v1/locations              # List all swim locations
GET /api/v1/locations/{id}         # Get single location
GET /api/v1/conditions/{location}  # Get conditions for location
```

**Rationale:** Versioned API prefix (`/api/v1/`) allows future breaking changes. RESTful resource naming.

### DTO Structure

```
src/Infrastructure/ApiPlatform/
├── Resource/
│   ├── LocationResource.php       # API Platform resource (DTO)
│   └── ConditionsResource.php     # API Platform resource (DTO)
├── State/
│   ├── LocationProvider.php       # Custom state provider
│   └── ConditionsProvider.php     # Custom state provider
└── Dto/
    ├── LocationOutput.php         # Output DTO
    ├── WaterConditionsOutput.php  # Nested DTO
    └── WeatherConditionsOutput.php # Nested DTO
```

**Rationale:** API Platform resources with custom providers. DTOs decouple API shape from domain entities.

### Output DTO Example

```php
// LocationOutput.php
class LocationOutput
{
    public string $id;
    public string $name;
    public float $latitude;
    public float $longitude;
}

// ConditionsOutput.php
class ConditionsOutput
{
    public string $locationId;
    public WaterConditionsOutput $water;
    public WeatherConditionsOutput $weather;
    public \DateTimeImmutable $updatedAt;
}
```

**Rationale:** Flat, simple structure optimized for JSON serialization. Timestamps help clients show data freshness.

### Custom Operations

Using API Platform's custom state providers to call Application layer use cases:

```php
#[ApiResource(
    operations: [
        new GetCollection(provider: LocationProvider::class),
        new Get(provider: LocationProvider::class),
    ]
)]
class LocationResource { }
```

**Rationale:** Custom providers bridge API Platform to hexagonal architecture. No persistence layer needed.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| API abuse without auth | Cache headers reduce load; add rate limiting at infrastructure level if needed |
| Breaking API changes | Versioned prefix allows `/api/v2/` in future |
| Large payload for wearables | Keep DTOs minimal; consider separate condensed endpoint later |

## Open Questions
- None currently
