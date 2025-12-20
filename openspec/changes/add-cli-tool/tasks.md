## 1. CLI Wrapper
- [x] 1.1 Create `bin/seaswim` executable script
- [x] 1.2 Configure script to bootstrap Symfony and run console
- [x] 1.3 Add `bin/seaswim` to `.gitattributes` with executable flag

## 2. Location Storage
- [x] 2.1 Create `var/data/` directory structure
- [x] 2.2 Add `var/data/` to `.gitignore`
- [x] 2.3 Create `LocationRepository` interface in Application layer
- [x] 2.4 Create `JsonFileLocationRepository` in Infrastructure layer

## 3. Commands
- [x] 3.1 Create `src/Infrastructure/Console/Command/ConditionsCommand.php`
- [x] 3.2 Create `src/Infrastructure/Console/Command/LocationsRefreshCommand.php`
- [x] 3.3 Create `src/Infrastructure/Console/Command/FetchCommand.php`
- [x] 3.4 Create `src/Infrastructure/Console/Command/CacheClearCommand.php`
- [x] 3.5 Register commands as services with `seaswim:` prefix

## 4. Output Formatting
- [x] 4.1 Create output formatter service for table/JSON output
- [x] 4.2 Add `--json` option to commands
- [x] 4.3 Add `--quiet` option to commands

## 5. Use Cases
- [x] 5.1 Create `GetConditionsForLocation` use case in Application layer
- [x] 5.2 Create `RefreshLocations` use case in Application layer
- [x] 5.3 Create `FetchAllData` use case in Application layer
- [x] 5.4 Create `ClearCache` use case in Application layer

## 6. Testing
- [x] 6.1 Write unit tests for use cases
- [x] 6.2 Write integration tests for commands (with mocked services)
- [x] 6.3 Test `bin/seaswim` wrapper execution

## 7. Documentation
- [x] 7.1 Add CLI usage section to README
- [x] 7.2 Add `--help` descriptions to all commands
