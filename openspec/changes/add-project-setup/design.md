# Design: Project Setup

## Context
Seaswim is a greenfield project for displaying sea swimming conditions in the Netherlands. It requires a solid foundation following hexagonal architecture principles with Symfony as the infrastructure layer.

## Goals / Non-Goals

**Goals:**
- Establish consistent project structure following hexagonal architecture
- Enable local development with DDEV (single command setup)
- Provide Makefile shortcuts for common development tasks
- Ensure code quality with static analysis (Psalm) and tests (PHPUnit)
- Provide CI feedback on code quality and tests
- Support filesystem-based caching for external API responses

**Non-Goals:**
- Database setup (using filesystem cache instead)
- Production deployment configuration
- Kubernetes/cloud-native setup

## Decisions

### Local Development: DDEV
Use DDEV for local development environment.

**Rationale:** DDEV provides consistent, containerized PHP environments with zero configuration. Includes PHP, web server, and Node.js support out of the box.

**Configuration:**
- PHP 8.4
- Web server: nginx-fpm
- Node.js for frontend builds

### Makefile
All common tasks accessible via `make` commands that wrap DDEV.

**Rationale:** Makefile provides discoverable, self-documenting commands. Using DDEV internally ensures consistency between developers and CI.

**Commands:**
- `make start` - Start DDEV environment
- `make stop` - Stop DDEV environment
- `make install` - Install dependencies (composer + npm)
- `make test` - Run PHPUnit tests
- `make lint` - Run PHP-CS-Fixer
- `make analyse` - Run Psalm static analysis
- `make build` - Build frontend assets
- `make ci` - Run all checks (lint, analyse, test, build)

### Directory Structure
```
src/
├── Domain/           # Core business logic (no framework deps)
│   ├── Entity/
│   └── ValueObject/
├── Application/      # Use cases, ports (interfaces), DTOs
│   ├── Port/
│   └── UseCase/
└── Infrastructure/   # Adapters (Symfony, external APIs)
    ├── Controller/
    ├── Cache/
    └── ExternalApi/
```

**Rationale:** Hexagonal architecture keeps domain logic framework-agnostic and testable. Symfony components live in Infrastructure layer only.

### Caching Strategy
- Location: `var/api-cache/`
- Format: JSON files with TTL metadata
- No database required for MVP

**Rationale:** External API responses change infrequently (weather, water conditions). Filesystem cache is simple, requires no additional services, and sufficient for expected traffic.

### Frontend Integration
- Webpack Encore for asset compilation
- Vue.js 3 with Composition API
- Assets compiled to `public/build/`

**Rationale:** Encore integrates well with Symfony and provides modern bundling without complex configuration.

### Static Analysis: Psalm
- Level 3 (moderate strictness) to start
- Can increase strictness over time

**Rationale:** Psalm catches type errors and potential bugs before runtime. Level 3 balances strictness with practical usability for a new project.

### CI Pipeline
- GitHub Actions
- Jobs: PHP-CS-Fixer, Psalm, PHPUnit, npm build
- Uses DDEV or direct PHP/Node for speed

**Rationale:** GitHub Actions is free, integrates with the repository, and covers essential quality gates.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| Filesystem cache may not scale | Acceptable for MVP; can migrate to Redis later if needed |
| PHP 8.4 hosting availability | DDEV handles local dev; CI uses PHP 8.4 image |
| DDEV adds container overhead | Acceptable trade-off for consistency |

## Open Questions
- None currently
