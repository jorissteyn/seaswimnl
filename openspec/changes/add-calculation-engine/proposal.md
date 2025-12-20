# Change: Add Calculation Engine

## Why
Raw water and weather data is hard to interpret at a glance. The calculation engine computes meaningful swim metrics that help users quickly decide if conditions are suitable for swimming.

## What Changes
- Create swim safety score calculation (traffic light: green/yellow/red)
- Create comfort index calculation (1-10 scale)
- Create best time to swim recommendation based on conditions
- Implement simple rule-based calculations with hardcoded thresholds
- Add calculated metrics to conditions output in all interfaces (CLI, Dashboard, API)

## Impact
- Affected specs: `calculation-engine` (new capability)
- Affected code: `src/Domain/Service/`, `src/Application/`
- Dependencies: Requires `add-core-data-retrieval` to be completed first
- No breaking changes (new capability, extends existing outputs)
