# Design: Calculation Engine

## Context
Users need quick, actionable insights from raw water and weather data. The calculation engine transforms conditions into swim safety scores, comfort indices, and timing recommendations using simple rule-based logic.

## Goals / Non-Goals

**Goals:**
- Compute swim safety score (traffic light)
- Compute comfort index (1-10)
- Recommend best time to swim
- Keep calculations in Domain layer (no framework dependencies)
- Use simple, understandable rules

**Non-Goals:**
- Machine learning or predictive models
- User-configurable thresholds (hardcoded for MVP)
- Historical trend analysis
- Personalized recommendations

## Decisions

### Domain Services

```
src/Domain/Service/
├── SafetyScoreCalculator.php    # Computes safety score
├── ComfortIndexCalculator.php   # Computes comfort index
└── SwimTimeRecommender.php      # Recommends best time
```

**Rationale:** Domain services contain pure business logic. No dependencies on infrastructure.

### Safety Score (Traffic Light)

| Score | Meaning | Conditions |
|-------|---------|------------|
| Green | Safe | Water temp ≥ 15°C, waves < 1m, quality Good, wind < 20 km/h |
| Yellow | Caution | Water temp 10-15°C, waves 1-2m, quality Moderate, wind 20-40 km/h |
| Red | Unsafe | Water temp < 10°C, waves > 2m, quality Poor, wind > 40 km/h |

**Logic:** Any single red condition → Red. Any yellow (no red) → Yellow. Otherwise → Green.

**Rationale:** Conservative approach prioritizes safety. Single dangerous factor triggers warning.

### Comfort Index (1-10)

Factors and weights:
- Water temperature: 40% (optimal 18-22°C)
- Air temperature: 20% (optimal 20-25°C)
- Wind speed: 20% (optimal < 10 km/h)
- UV index: 10% (optimal 3-5)
- Wave height: 10% (optimal < 0.5m)

**Formula:** Weighted average of individual factor scores (each 0-10).

**Rationale:** Simple linear scoring. Water temperature matters most for swim comfort.

### Best Time Recommendation

Based on current conditions and time of day:
- "Now" - Current conditions are green/good
- "Later today" - Conditions expected to improve
- "Tomorrow" - Today's conditions are poor
- "Not recommended" - Extended poor conditions

**Note:** MVP uses current conditions only. Forecast integration deferred.

**Rationale:** Simple recommendation based on current state. Forecast requires additional data source.

### Value Objects

```
src/Domain/ValueObject/
├── SafetyScore.php      # Enum: Green, Yellow, Red
├── ComfortIndex.php     # Integer 1-10
└── SwimRecommendation.php # Enum + explanation text
```

**Rationale:** Strong typing for calculated values. Enums prevent invalid states.

### Integration Points

Calculations are added to the existing conditions flow:
1. `GetConditionsForLocation` use case fetches raw data
2. Calculation services compute metrics
3. Output includes both raw data and calculated metrics

**Rationale:** Calculations are derived data, not persisted. Computed on-demand.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| Thresholds may not suit all swimmers | Document thresholds; make configurable in future |
| Missing data affects calculations | Use conservative scores when data unavailable |
| Oversimplified recommendations | Clear that this is guidance, not guarantee |

## Open Questions
- None currently
