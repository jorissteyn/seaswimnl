## 1. CLI Wrapper
- [ ] 1.1 Create `bin/seaswim` executable script
- [ ] 1.2 Configure script to bootstrap Symfony and run console
- [ ] 1.3 Add `bin/seaswim` to `.gitattributes` with executable flag

## 2. Location Storage
- [ ] 2.1 Create `var/data/` directory structure
- [ ] 2.2 Add `var/data/` to `.gitignore`
- [ ] 2.3 Create `LocationRepository` interface in Application layer
- [ ] 2.4 Create `JsonFileLocationRepository` in Infrastructure layer

## 3. Commands
- [ ] 3.1 Create `src/Infrastructure/Console/Command/ConditionsCommand.php`
- [ ] 3.2 Create `src/Infrastructure/Console/Command/LocationsRefreshCommand.php`
- [ ] 3.3 Create `src/Infrastructure/Console/Command/FetchCommand.php`
- [ ] 3.4 Create `src/Infrastructure/Console/Command/CacheClearCommand.php`
- [ ] 3.5 Register commands as services with `seaswim:` prefix

## 4. Output Formatting
- [ ] 4.1 Create output formatter service for table/JSON output
- [ ] 4.2 Add `--json` option to commands
- [ ] 4.3 Add `--quiet` option to commands

## 5. Use Cases
- [ ] 5.1 Create `GetConditionsForLocation` use case in Application layer
- [ ] 5.2 Create `RefreshLocations` use case in Application layer
- [ ] 5.3 Create `FetchAllData` use case in Application layer
- [ ] 5.4 Create `ClearCache` use case in Application layer

## 6. Testing
- [ ] 6.1 Write unit tests for use cases
- [ ] 6.2 Write integration tests for commands (with mocked services)
- [ ] 6.3 Test `bin/seaswim` wrapper execution

## 7. Documentation
- [ ] 7.1 Add CLI usage section to README
- [ ] 7.2 Add `--help` descriptions to all commands
