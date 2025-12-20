## ADDED Requirements

### Requirement: Safety Score Calculation
The system SHALL compute a swim safety score based on current conditions.

#### Scenario: Green safety score
- **WHEN** water temp ≥ 15°C, waves < 1m, quality is Good, wind < 20 km/h
- **THEN** safety score is Green (safe)

#### Scenario: Yellow safety score
- **WHEN** any condition is in caution range (e.g., water temp 10-15°C)
- **AND** no conditions are in unsafe range
- **THEN** safety score is Yellow (caution)

#### Scenario: Red safety score
- **WHEN** any single condition is in unsafe range (e.g., water temp < 10°C)
- **THEN** safety score is Red (unsafe)

#### Scenario: Missing data handling
- **WHEN** required condition data is unavailable
- **THEN** a conservative (Yellow or Red) score is returned

### Requirement: Comfort Index Calculation
The system SHALL compute a comfort index on a scale of 1-10.

#### Scenario: Optimal conditions
- **WHEN** water temp is 18-22°C, air temp 20-25°C, wind < 10 km/h
- **THEN** comfort index is 8-10

#### Scenario: Suboptimal conditions
- **WHEN** conditions deviate from optimal ranges
- **THEN** comfort index decreases proportionally

#### Scenario: Poor conditions
- **WHEN** multiple factors are far from optimal
- **THEN** comfort index is 1-3

#### Scenario: Weighted calculation
- **WHEN** computing comfort index
- **THEN** water temperature contributes 40%
- **AND** air temperature contributes 20%
- **AND** wind speed contributes 20%
- **AND** UV index contributes 10%
- **AND** wave height contributes 10%

### Requirement: Swim Time Recommendation
The system SHALL provide a recommendation for when to swim.

#### Scenario: Recommend now
- **WHEN** current conditions are safe and comfortable
- **THEN** recommendation is "Now"

#### Scenario: Recommend later
- **WHEN** current conditions are marginal
- **THEN** recommendation is "Later today" or "Tomorrow"

#### Scenario: Not recommended
- **WHEN** conditions are unsafe
- **THEN** recommendation is "Not recommended"

#### Scenario: Recommendation includes explanation
- **WHEN** a recommendation is made
- **THEN** a brief explanation of the reasoning is included

### Requirement: Calculation Value Objects
The system SHALL represent calculated metrics as value objects.

#### Scenario: Safety score value object
- **WHEN** safety score is computed
- **THEN** it is represented as a SafetyScore enum (Green, Yellow, Red)

#### Scenario: Comfort index value object
- **WHEN** comfort index is computed
- **THEN** it is represented as a ComfortIndex with integer value 1-10

#### Scenario: Recommendation value object
- **WHEN** recommendation is computed
- **THEN** it is represented as a SwimRecommendation with type and explanation

### Requirement: Metrics in Conditions Output
The system SHALL include calculated metrics in all conditions outputs.

#### Scenario: CLI output includes metrics
- **WHEN** displaying conditions in CLI
- **THEN** safety score, comfort index, and recommendation are shown

#### Scenario: Dashboard displays metrics
- **WHEN** displaying conditions in dashboard
- **THEN** safety score, comfort index, and recommendation are shown

#### Scenario: API response includes metrics
- **WHEN** returning conditions via API
- **THEN** safety score, comfort index, and recommendation are included in JSON
