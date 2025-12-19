## ADDED Requirements

### Requirement: Symfony Project Structure
The project SHALL use Symfony 7.2 with PHP 8.4 and follow hexagonal architecture with Domain, Application, and Infrastructure layers.

#### Scenario: Project boots successfully
- **WHEN** running `bin/console` or starting the web server
- **THEN** Symfony kernel boots without errors

#### Scenario: Hexagonal layers are separated
- **WHEN** examining the `src/` directory
- **THEN** it contains `Domain/`, `Application/`, and `Infrastructure/` directories
- **AND** Domain layer has no Symfony framework imports

### Requirement: DDEV Local Development
The project SHALL use DDEV for local development with PHP 8.4 and Node.js support.

#### Scenario: DDEV starts successfully
- **WHEN** running `ddev start`
- **THEN** the development environment starts with PHP 8.4
- **AND** the web server is accessible at the configured URL

#### Scenario: DDEV runs PHP commands
- **WHEN** running `ddev exec bin/console`
- **THEN** Symfony console commands execute in the container

### Requirement: Makefile Commands
The project SHALL provide a Makefile with DDEV-based commands for common development tasks.

#### Scenario: Make commands are available
- **WHEN** running `make help` or examining the Makefile
- **THEN** commands for start, stop, install, test, lint, analyse, build, and ci are documented

#### Scenario: Make install sets up dependencies
- **WHEN** running `make install`
- **THEN** Composer dependencies are installed
- **AND** npm dependencies are installed

#### Scenario: Make ci runs all checks
- **WHEN** running `make ci`
- **THEN** linting, static analysis, tests, and build are executed

### Requirement: Frontend Build Pipeline
The project SHALL use Webpack Encore to compile Vue.js 3 assets into `public/build/`.

#### Scenario: Assets compile successfully
- **WHEN** running `make build` or `npm run build`
- **THEN** compiled assets appear in `public/build/`
- **AND** no compilation errors occur

#### Scenario: Development mode with hot reload
- **WHEN** running `npm run watch`
- **THEN** assets recompile automatically on file changes

### Requirement: Filesystem Cache Directory
The project SHALL use `var/api-cache/` for caching external API responses.

#### Scenario: Cache directory is writable
- **WHEN** the application attempts to write cache files
- **THEN** files are created in `var/api-cache/`
- **AND** the directory is excluded from version control

### Requirement: CI Pipeline
The project SHALL have a GitHub Actions workflow that runs on pull requests and pushes to main.

#### Scenario: CI runs quality checks
- **WHEN** a pull request is opened or code is pushed to main
- **THEN** PHP-CS-Fixer checks code style
- **AND** Psalm runs static analysis
- **AND** PHPUnit runs the test suite
- **AND** npm build verifies frontend compilation

#### Scenario: CI blocks merge on failure
- **WHEN** any CI job fails
- **THEN** the workflow reports failure status
- **AND** merge is blocked (via branch protection rules)

### Requirement: Static Analysis with Psalm
The project SHALL include Psalm configured at level 3 for static type checking.

#### Scenario: Psalm can analyse the codebase
- **WHEN** running `make analyse` or `vendor/bin/psalm`
- **THEN** Psalm analyses all PHP files in `src/`
- **AND** reports any type errors or issues

#### Scenario: Psalm baseline is available
- **WHEN** legacy issues exist
- **THEN** a baseline file can suppress known issues

### Requirement: Testing with PHPUnit
The project SHALL include PHPUnit configured for testing.

#### Scenario: Tests can be executed
- **WHEN** running `make test` or `vendor/bin/phpunit`
- **THEN** the test suite executes
- **AND** results are reported

#### Scenario: Test directories are configured
- **WHEN** examining `phpunit.xml.dist`
- **THEN** test directories for unit and integration tests are defined

### Requirement: Code Style with PHP-CS-Fixer
The project SHALL include PHP-CS-Fixer configured with PSR-12.

#### Scenario: Code style is checkable
- **WHEN** running `make lint` or `vendor/bin/php-cs-fixer fix --dry-run`
- **THEN** style violations are reported

#### Scenario: Code style is fixable
- **WHEN** running `vendor/bin/php-cs-fixer fix`
- **THEN** style violations are automatically corrected

### Requirement: Project README
The project SHALL have a README.md with setup instructions and development workflow.

#### Scenario: README contains setup instructions
- **WHEN** reading README.md
- **THEN** it explains how to install DDEV
- **AND** how to start the project with `make start` and `make install`

#### Scenario: README documents available commands
- **WHEN** reading README.md
- **THEN** it lists the available Makefile commands
