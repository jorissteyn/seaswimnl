# Change: Add Project Setup

## Why
Seaswim needs foundational project infrastructure before feature development can begin. This establishes the PHP/Symfony backend, Vue.js frontend scaffolding, local development environment, and CI pipeline.

## What Changes
- Initialize Symfony 7.2 project with PHP 8.4
- Configure DDEV for local development environment
- Create Makefile with DDEV-based commands for common tasks
- Configure Vue.js frontend build with Webpack Encore
- Set up filesystem-based API response caching (`var/api-cache/`)
- Add static analysis with Psalm and testing with PHPUnit
- Create GitHub Actions CI pipeline (linting, static analysis, tests)
- Establish hexagonal architecture directory structure
- Create project README with setup instructions

## Impact
- Affected specs: `project-setup` (new capability)
- Affected code: Entire project structure (greenfield)
- No breaking changes (new project)
