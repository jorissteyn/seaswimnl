## 1. Value Objects
- [x] 1.1 Create `src/Domain/ValueObject/SafetyScore.php` (enum: Green, Yellow, Red)
- [x] 1.2 Create `src/Domain/ValueObject/ComfortIndex.php` (integer 1-10)
- [x] 1.3 Create `src/Domain/ValueObject/SwimRecommendation.php` (type + explanation)

## 2. Domain Services
- [x] 2.1 Create `src/Domain/Service/SafetyScoreCalculator.php`
- [x] 2.2 Implement safety score rules (green/yellow/red thresholds)
- [x] 2.3 Create `src/Domain/Service/ComfortIndexCalculator.php`
- [x] 2.4 Implement weighted comfort calculation
- [x] 2.5 Create `src/Domain/Service/SwimTimeRecommender.php`
- [x] 2.6 Implement recommendation logic

## 3. Integration
- [x] 3.1 Create `CalculatedMetrics` aggregate to hold all computed values
- [x] 3.2 Update `GetConditionsForLocation` use case to include calculations
- [x] 3.3 Wire calculation services in Symfony DI container

## 4. Output Updates
- [x] 4.1 Update CLI ConditionsCommand to display metrics
- [x] 4.2 Update Dashboard ConditionsPanel to show metrics
- [x] 4.3 Update API ConditionsOutput DTO to include metrics
- [x] 4.4 Add visual indicators (colors/icons) for safety score in UI

## 5. Testing
- [x] 5.1 Write unit tests for SafetyScoreCalculator
- [x] 5.2 Write unit tests for ComfortIndexCalculator
- [x] 5.3 Write unit tests for SwimTimeRecommender
- [x] 5.4 Test edge cases (missing data, boundary values)
- [x] 5.5 Integration tests for metrics in outputs

## 6. Documentation
- [x] 6.1 Document threshold values in README or design doc
- [x] 6.2 Add explanation of score meanings in UI
