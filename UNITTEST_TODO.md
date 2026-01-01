# Unit Test Coverage Report

Generated: 2026-01-01

**Overall Coverage: 71.9% (2118/2947 statements)**

**Tests: 776 | Assertions: 4,196**

## Fully Covered (100%)

| File | Coverage |
|------|----------|
| Application/Port/RwsLocationRepositoryInterface.php | 100% |
| Application/Port/SwimmingSpotRepositoryInterface.php | 100% |
| Application/Port/TidalInfoProviderInterface.php | 100% |
| Application/Port/WaterConditionsProviderInterface.php | 100% |
| Application/Port/WeatherConditionsProviderInterface.php | 100% |
| Application/Port/WeatherStationRepositoryInterface.php | 100% |
| Application/UseCase/ClearCache.php | 100% |
| Application/UseCase/RefreshLocations.php | 100% |
| Domain/Entity/CalculatedMetrics.php | 100% |
| Domain/Entity/WaterConditions.php | 100% |
| Domain/Entity/WeatherConditions.php | 100% |
| Domain/Service/MeasurementCodes.php | 100% |
| Domain/Service/NearestRwsLocationFinder.php | 100% |
| Domain/Service/NearestRwsLocationMatcher.php | 100% |
| Domain/Service/SafetyScoreCalculator.php | 100% |
| Domain/Service/TideCalculator.php | 100% |
| Domain/ValueObject/ComfortIndex.php | 100% |
| Domain/ValueObject/RwsLocation.php | 100% |
| Domain/ValueObject/SafetyScore.php | 100% |
| Domain/ValueObject/Sunpower.php | 100% |
| Domain/ValueObject/SwimmingSpot.php | 100% |
| Domain/ValueObject/Temperature.php | 100% |
| Domain/ValueObject/TideEvent.php | 100% |
| Domain/ValueObject/TideInfo.php | 100% |
| Domain/ValueObject/TideType.php | 100% |
| Domain/ValueObject/WaterHeight.php | 100% |
| Domain/ValueObject/WaveDirection.php | 100% |
| Domain/ValueObject/WaveHeight.php | 100% |
| Domain/ValueObject/WavePeriod.php | 100% |
| Domain/ValueObject/WeatherStation.php | 100% |
| Domain/ValueObject/WindSpeed.php | 100% |
| Infrastructure/ApiPlatform/Dto/ConditionsOutput.php | 100% |
| Infrastructure/ApiPlatform/Dto/LocationOutput.php | 100% |
| Infrastructure/ApiPlatform/Dto/MetricsOutput.php | 100% |
| Infrastructure/ApiPlatform/Dto/SwimmingSpotOutput.php | 100% |
| Infrastructure/ApiPlatform/Dto/WaterConditionsOutput.php | 100% |
| Infrastructure/ApiPlatform/Dto/WeatherConditionsOutput.php | 100% |
| Infrastructure/ApiPlatform/State/ConditionsProvider.php | 100% |
| Infrastructure/ApiPlatform/State/LocationProvider.php | 100% |
| Infrastructure/ApiPlatform/State/SwimmingSpotProvider.php | 100% |
| Infrastructure/Console/Command/CacheClearCommand.php | 100% |
| Infrastructure/Console/Command/LocationsRefreshCommand.php | 100% |
| Infrastructure/Controller/Api/LocationsController.php | 100% |
| Infrastructure/Controller/Api/SwimmingSpotsController.php | 100% |
| Infrastructure/ExternalApi/Client/BuienradarHttpClientInterface.php | 100% |
| Infrastructure/ExternalApi/Client/RwsHttpClientInterface.php | 100% |
| Infrastructure/ExternalApi/RijkswaterstaatTidalAdapter.php | 100% |
| Infrastructure/Kernel.php | 100% |
| Infrastructure/Service/LocationBlacklist.php | 100% |
| Kernel.php | 100% |

## Good Coverage (80-99%)

| File | Coverage |
|------|----------|
| Infrastructure/Controller/Api/MeasurementsController.php | 98.9% |
| Infrastructure/Repository/JsonFileRwsLocationRepository.php | 97.7% |
| Infrastructure/Repository/JsonFileWeatherStationRepository.php | 97.4% |
| Infrastructure/ExternalApi/Client/RwsHttpClient.php | 97.0% |
| Domain/Service/WeatherStationMatcher.php | 96.9% |
| Infrastructure/Repository/CsvSwimmingSpotRepository.php | 96.2% |
| Domain/Service/ComfortIndexCalculator.php | 91.0% |
| Infrastructure/Controller/Api/ConditionsController.php | 90.1% |
| Infrastructure/ExternalApi/BuienradarAdapter.php | 88.9% |
| Infrastructure/ExternalApi/Client/BuienradarHttpClient.php | 88.8% |
| Infrastructure/ExternalApi/RijkswaterstaatAdapter.php | 87.0% |

## Medium Coverage (50-79%)

| File | Covered | Total | Coverage |
|------|---------|-------|----------|
| Application/UseCase/GetConditionsForSwimmingSpot.php | 70 | 101 | 69.3% |

## Low Coverage (<50%) - TODO

| File | Covered | Total | Coverage |
|------|---------|-------|----------|
| Infrastructure/ApiPlatform/Resource/ConditionsResource.php | 0 | 10 | 0.0% |
| Infrastructure/ApiPlatform/Resource/LocationResource.php | 0 | 14 | 0.0% |
| Infrastructure/ApiPlatform/Resource/SwimmingSpotResource.php | 0 | 14 | 0.0% |
| Infrastructure/Console/Command/ConditionsCommand.php | 0 | 220 | 0.0% |
| Infrastructure/Console/Command/LocationsClassifyWaterTypeCommand.php | 0 | 104 | 0.0% |
| Infrastructure/Console/Command/LocationsListCommand.php | 0 | 107 | 0.0% |
| Infrastructure/Console/Command/LocationsMatchCommand.php | 0 | 35 | 0.0% |
| Infrastructure/Console/Command/LocationsScanStaleCommand.php | 0 | 112 | 0.0% |
| Infrastructure/Console/Command/NearestBuoyCommand.php | 0 | 35 | 0.0% |
| Infrastructure/Console/Command/RwsDebugCommand.php | 0 | 85 | 0.0% |
| Infrastructure/Controller/DashboardController.php | 0 | 1 | 0.0% |

