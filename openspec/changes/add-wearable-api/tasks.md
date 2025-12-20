## 1. API Platform Setup
- [ ] 1.1 Install API Platform (`ddev composer require api-platform/core`)
- [ ] 1.2 Configure `config/packages/api_platform.yaml` with versioned prefix
- [ ] 1.3 Set up JSON-only format and cache headers
- [ ] 1.4 Verify OpenAPI docs available at `/api/v1/docs`

## 2. Output DTOs
- [ ] 2.1 Create `src/Infrastructure/ApiPlatform/Dto/LocationOutput.php`
- [ ] 2.2 Create `src/Infrastructure/ApiPlatform/Dto/WaterConditionsOutput.php`
- [ ] 2.3 Create `src/Infrastructure/ApiPlatform/Dto/WeatherConditionsOutput.php`
- [ ] 2.4 Create `src/Infrastructure/ApiPlatform/Dto/ConditionsOutput.php`

## 3. API Resources
- [ ] 3.1 Create `src/Infrastructure/ApiPlatform/Resource/LocationResource.php`
- [ ] 3.2 Create `src/Infrastructure/ApiPlatform/Resource/ConditionsResource.php`
- [ ] 3.3 Configure operations with custom providers

## 4. State Providers
- [ ] 4.1 Create `src/Infrastructure/ApiPlatform/State/LocationProvider.php`
- [ ] 4.2 Create `src/Infrastructure/ApiPlatform/State/ConditionsProvider.php`
- [ ] 4.3 Wire providers to Application layer use cases
- [ ] 4.4 Implement DTO mapping from domain entities

## 5. Testing
- [ ] 5.1 Write integration tests for GET /api/v1/locations
- [ ] 5.2 Write integration tests for GET /api/v1/locations/{id}
- [ ] 5.3 Write integration tests for GET /api/v1/conditions/{location}
- [ ] 5.4 Test 404 responses for unknown resources
- [ ] 5.5 Verify cache headers in responses

## 6. Documentation
- [ ] 6.1 Add API section to README
- [ ] 6.2 Document endpoints and response formats
- [ ] 6.3 Add example curl commands
