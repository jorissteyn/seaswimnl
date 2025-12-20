## 1. Value Objects
- [ ] 1.1 Create `src/Domain/ValueObject/SafetyScore.php` (enum: Green, Yellow, Red)
- [ ] 1.2 Create `src/Domain/ValueObject/ComfortIndex.php` (integer 1-10)
- [ ] 1.3 Create `src/Domain/ValueObject/SwimRecommendation.php` (type + explanation)

## 2. Domain Services
- [ ] 2.1 Create `src/Domain/Service/SafetyScoreCalculator.php`
- [ ] 2.2 Implement safety score rules (green/yellow/red thresholds)
- [ ] 2.3 Create `src/Domain/Service/ComfortIndexCalculator.php`
- [ ] 2.4 Implement weighted comfort calculation
- [ ] 2.5 Create `src/Domain/Service/SwimTimeRecommender.php`
- [ ] 2.6 Implement recommendation logic

## 3. Integration
- [ ] 3.1 Create `CalculatedMetrics` aggregate to hold all computed values
- [ ] 3.2 Update `GetConditionsForLocation` use case to include calculations
- [ ] 3.3 Wire calculation services in Symfony DI container

## 4. Output Updates
- [ ] 4.1 Update CLI ConditionsCommand to display metrics
- [ ] 4.2 Update Dashboard ConditionsPanel to show metrics
- [ ] 4.3 Update API ConditionsOutput DTO to include metrics
- [ ] 4.4 Add visual indicators (colors/icons) for safety score in UI

## 5. Testing
- [ ] 5.1 Write unit tests for SafetyScoreCalculator
- [ ] 5.2 Write unit tests for ComfortIndexCalculator
- [ ] 5.3 Write unit tests for SwimTimeRecommender
- [ ] 5.4 Test edge cases (missing data, boundary values)
- [ ] 5.5 Integration tests for metrics in outputs

## 6. Documentation
- [ ] 6.1 Document threshold values in README or design doc
- [ ] 6.2 Add explanation of score meanings in UI
