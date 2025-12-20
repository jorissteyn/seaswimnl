# Design: CLI Tool

## Context
The CLI tool provides command-line access to Seaswim functionality. It wraps Symfony Console commands behind a standalone `bin/seaswim` script for convenient invocation.

## Goals / Non-Goals

**Goals:**
- Provide quick access to water conditions from terminal
- Enable location management (refresh from RWS)
- Allow manual cache control (fetch, clear)
- Support scripting and automation

**Non-Goals:**
- Interactive TUI (text user interface)
- Background daemon mode
- Real-time updates/watching

## Decisions

### CLI Structure

```
bin/seaswim <command> [arguments] [options]

Commands:
  conditions <location>    Show water/weather conditions for a location
  locations refresh        Refresh swim locations from RWS data source
  fetch [--force]          Fetch latest data from APIs (respects cache unless --force)
  cache:clear              Clear all cached API responses
```

**Rationale:** Simple, verb-based commands. Follows Unix conventions.

### bin/seaswim Wrapper

```php
#!/usr/bin/env php
<?php
// Thin wrapper that boots Symfony and runs console with 'seaswim:' prefix
require __DIR__.'/../vendor/autoload.php';
// ... bootstrap and run
```

**Rationale:** Standalone script feels native. Internally delegates to Symfony Console commands prefixed with `seaswim:`.

### Command Classes (Infrastructure Layer)

```
src/Infrastructure/Console/Command/
├── ConditionsCommand.php      # seaswim:conditions
├── LocationsRefreshCommand.php # seaswim:locations:refresh
├── FetchCommand.php           # seaswim:fetch
└── CacheClearCommand.php      # seaswim:cache:clear
```

**Rationale:** Commands are infrastructure adapters. They call Application layer use cases.

### Output Formatting

- Default: Human-readable table format
- `--json` flag: JSON output for scripting
- `--quiet` flag: Minimal output (exit codes only)

**Rationale:** Support both human users and scripts.

### Location Data Source

Locations are refreshed from Rijkswaterstaat measurement station data. The `locations refresh` command fetches the list of available stations and stores them locally.

**Storage:** JSON file in `var/data/locations.json`

**Rationale:** Simple file storage avoids database dependency. Locations change infrequently.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| Location file corruption | Validate JSON before overwriting; keep backup |
| API unavailable during refresh | Show clear error message; keep existing locations |

## Open Questions
- None currently
