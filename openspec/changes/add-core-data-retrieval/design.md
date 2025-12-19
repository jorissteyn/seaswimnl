# Design: Core Data Retrieval

## Context
Seaswim needs to aggregate water and weather data from multiple Dutch government sources. The data must be cached to reduce API calls and improve response times. Following hexagonal architecture, external API access should be isolated in adapters.

## Goals / Non-Goals

**Goals:**
- Fetch water conditions from Rijkswaterstaat (RWS)
- Fetch weather data from KNMI
- Cache API responses using Symfony Cache (filesystem adapter)
- Keep domain logic independent of external APIs
- Handle API failures gracefully

**Non-Goals:**
- Real-time streaming data (polling/scheduled refresh is sufficient)
- Historical data analysis
- User authentication for data access

## Decisions

### Data Sources

**Rijkswaterstaat (RWS):**
- API: Waterinfo API (https://waterinfo.rws.nl)
- Data: Water temperature, wave height, water height (tide level), currents, water quality
- Format: JSON

**KNMI:**
- API: KNMI Open Data API
- Data: Wind speed/direction, UV index, air temperature, precipitation
- Format: JSON

### Domain Model

```
src/Domain/
├── Entity/
│   ├── WaterConditions.php      # Aggregate for water data
│   └── WeatherConditions.php    # Aggregate for weather data
└── ValueObject/
    ├── Temperature.php          # Celsius, nullable
    ├── WaveHeight.php           # Meters
    ├── WaterHeight.php          # Meters (tide level relative to NAP)
    ├── WindSpeed.php            # m/s or km/h
    ├── UVIndex.php              # 0-11+ scale
    ├── WaterQuality.php         # Enum: Good, Moderate, Poor, Unknown
    └── Location.php             # Coordinates + name
```

**Rationale:** Value objects enforce invariants and make the domain expressive. Entities are framework-agnostic.

### Ports (Application Layer)

```
src/Application/Port/
├── WaterConditionsProviderInterface.php
└── WeatherConditionsProviderInterface.php
```

**Rationale:** Ports define what the application needs without specifying how. Adapters implement the how.

### Adapters (Infrastructure Layer)

```
src/Infrastructure/ExternalApi/
├── RijkswaterstaatAdapter.php   # Implements WaterConditionsProviderInterface
├── KnmiAdapter.php              # Implements WeatherConditionsProviderInterface
└── Client/
    ├── RwsHttpClient.php        # HTTP client for RWS API
    └── KnmiHttpClient.php       # HTTP client for KNMI API
```

**Rationale:** Each external API gets its own adapter. HTTP clients handle low-level communication.

### Configuration

**YAML Configuration (`config/packages/seaswim.yaml`):**
```yaml
seaswim:
    apis:
        rijkswaterstaat:
            base_url: '%env(RWS_API_URL)%'
            timeout: 10
        knmi:
            base_url: '%env(KNMI_API_URL)%'
            api_key: '%env(KNMI_API_KEY)%'
            timeout: 10
    cache:
        water_ttl: 900      # 15 minutes
        weather_ttl: 1800   # 30 minutes
```

**Environment Variables (`.env`):**
```
RWS_API_URL=https://waterinfo.rws.nl/api
KNMI_API_URL=https://api.dataplatform.knmi.nl/open-data/v1
KNMI_API_KEY=your-api-key-here
```

**Rationale:** YAML config for static settings (timeouts, TTLs), environment variables for secrets and URLs that vary per environment. Follows Symfony best practices.

### Caching Strategy

- Use Symfony Cache component with FilesystemAdapter
- Cache key: `{provider}_{location}_{datatype}`
- TTL: 15 minutes for water data, 30 minutes for weather
- Cache stored in `var/cache/api/`

**Rationale:** Government APIs have rate limits and data doesn't change rapidly. Caching reduces load and improves UX.

### Error Handling

- Return null/unknown values when API is unavailable
- Log API failures for monitoring
- Never throw exceptions to domain layer from adapters

**Rationale:** Swimmers should see partial data rather than errors. Graceful degradation improves reliability.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| RWS/KNMI API changes | Abstract behind adapters; version API clients |
| Rate limiting | Caching with appropriate TTL |
| API downtime | Return cached data even if stale; mark as "last updated" |
| Data inconsistency between sources | Use timestamps; display data age to users |

## Open Questions
- Exact RWS API endpoints for specific data points (needs investigation during implementation)
- KNMI API authentication requirements (if any)
