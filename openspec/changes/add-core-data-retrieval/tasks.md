## 1. Domain Layer
- [x] 1.1 Create `src/Domain/ValueObject/Temperature.php` (Celsius, nullable)
- [x] 1.2 Create `src/Domain/ValueObject/WaveHeight.php` (meters)
- [x] 1.3 Create `src/Domain/ValueObject/WaterHeight.php` (meters, tide level relative to NAP)
- [x] 1.4 Create `src/Domain/ValueObject/WindSpeed.php` (m/s)
- [x] 1.5 Create `src/Domain/ValueObject/UVIndex.php` (0-11+ scale)
- [x] 1.6 Create `src/Domain/ValueObject/WaterQuality.php` (enum)
- [x] 1.7 Create `src/Domain/ValueObject/Location.php` (coordinates + name)
- [x] 1.8 Create `src/Domain/Entity/WaterConditions.php`
- [x] 1.9 Create `src/Domain/Entity/WeatherConditions.php`
- [x] 1.10 Write unit tests for value objects

## 2. Application Layer (Ports)
- [x] 2.1 Create `src/Application/Port/WaterConditionsProviderInterface.php`
- [x] 2.2 Create `src/Application/Port/WeatherConditionsProviderInterface.php`

## 3. Configuration
- [x] 3.1 Create `config/packages/seaswim.yaml` with API settings (timeouts, cache TTLs)
- [x] 3.2 Add environment variables to `.env` (RWS_API_URL, KNMI_API_URL, KNMI_API_KEY)
- [x] 3.3 Create Configuration class for Symfony bundle configuration
- [x] 3.4 Document environment variables in README

## 4. Infrastructure Layer (HTTP Clients)
- [x] 4.1 Install Symfony HTTP Client (`ddev composer require symfony/http-client`)
- [x] 4.2 Create `src/Infrastructure/ExternalApi/Client/RwsHttpClient.php`
- [x] 4.3 Create `src/Infrastructure/ExternalApi/Client/KnmiHttpClient.php`
- [x] 4.4 Configure HTTP client services in `config/services.yaml`

## 5. Infrastructure Layer (Adapters)
- [x] 5.1 Create `src/Infrastructure/ExternalApi/RijkswaterstaatAdapter.php`
- [x] 5.2 Create `src/Infrastructure/ExternalApi/KnmiAdapter.php`
- [x] 5.3 Register adapters as services implementing the ports

## 6. Caching
- [x] 6.1 Install Symfony Cache (`ddev composer require symfony/cache`)
- [x] 6.2 Configure FilesystemAdapter cache pool in `config/packages/cache.yaml`
- [x] 6.3 Add caching decorator or integrate caching in adapters
- [x] 6.4 Implement stale-while-revalidate pattern for API failures

## 7. Testing
- [x] 7.1 Write unit tests for WaterConditions entity
- [x] 7.2 Write unit tests for WeatherConditions entity
- [x] 7.3 Write integration tests for RijkswaterstaatAdapter (with mocked HTTP)
- [x] 7.4 Write integration tests for KnmiAdapter (with mocked HTTP)
- [x] 7.5 Write tests for caching behavior

## 8. Documentation
- [x] 8.1 Document RWS API endpoints used in adapter
- [x] 8.2 Document KNMI API endpoints used in adapter
- [x] 8.3 Add inline comments for cache TTL configuration
