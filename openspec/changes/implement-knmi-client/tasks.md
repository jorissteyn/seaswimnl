# Tasks: Implement KNMI Client

## Phase 1: Domain & Data Layer

- [x] **1. Create KnmiStation value object**
  - Add `src/Domain/ValueObject/KnmiStation.php`
  - Properties: code, name, latitude, longitude

- [x] **2. Create KnmiStationRepositoryInterface**
  - Add `src/Application/Port/KnmiStationRepositoryInterface.php`
  - Methods: `findAll()`, `findByCode()`, `saveAll()`

- [x] **3. Create JsonFileKnmiStationRepository**
  - Add `src/Infrastructure/Repository/JsonFileKnmiStationRepository.php`
  - Store in `var/data/knmi-stations.json`

- [x] **4. Create KnmiStationMatcher domain service**
  - Add `src/Domain/Service/KnmiStationMatcher.php`
  - Implement fuzzy name matching algorithm
  - Handle RWS naming conventions (suffixes, dots)

- [x] **5. Write unit tests for KnmiStationMatcher**
  - Test various RWS name patterns
  - Test edge cases (no match, multiple candidates)

## Phase 2: HTTP Client

- [x] **6. Extend KnmiHttpClientInterface**
  - Add `fetchStations(): ?array` method
  - Add `fetchHourlyData(string $stationCode, \DateTimeImmutable $date): ?array` method
  - Update existing `fetchWeatherData()` signature

- [x] **7. Rewrite KnmiHttpClient implementation**
  - Use daggegevens.knmi.nl base URL
  - Implement POST with form-encoded data
  - Fetch hourly data: `T`, `FH`, `DD`, `U` variables
  - Handle 0.1 unit conversion (temp/10, wind/10)
  - Remove unused apiKey dependency

- [x] **8. Write integration test for KnmiHttpClient**
  - Test against real KNMI API (or mock)
  - Verify data normalization

## Phase 3: Adapter Updates

- [x] **9. Update KnmiAdapter**
  - Inject KnmiStationMatcher and KnmiStationRepository
  - Find matching station for location
  - Cache by station code (not location ID)
  - Handle no-match case gracefully

- [x] **10. Update RefreshLocations use case**
  - Inject KnmiHttpClient and KnmiStationRepository
  - Fetch and save KNMI stations
  - Return count of stations refreshed

- [x] **11. Update LocationsRefreshCommand**
  - Display KNMI station count in output

## Phase 4: Service Configuration

- [x] **12. Register new services in Symfony**
  - Add services.yaml entries for new repositories
  - Add services.yaml entries for KnmiStationMatcher
  - Update KnmiHttpClient configuration (remove apiKey, update baseUrl)

## Phase 5: Validation

- [x] **13. Run full test suite**
  - `make test`
  - `make lint`
  - `make analyse`

- [x] **14. Manual end-to-end testing**
  - Run `seaswim:locations:refresh` command
  - Verify KNMI stations are cached
  - Select location on dashboard
  - Verify weather data displays correctly
