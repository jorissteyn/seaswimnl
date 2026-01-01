.PHONY: help start stop install test test-cov test-cov-serve lint analyse analyze dev build watch ci clean

.DEFAULT_GOAL := help

# Detect if running inside DDEV container
ifdef IS_DDEV_PROJECT
    EXEC :=
    NPM := npm
    COMPOSER := composer
else
    EXEC := ddev exec
    NPM := ddev npm
    COMPOSER := ddev composer
endif

## —— Seaswim Makefile ———————————————————————————————————————————————————————————

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

## —— DDEV ———————————————————————————————————————————————————————————————————————

start: ## Start DDEV environment
	ddev start

stop: ## Stop DDEV environment
	ddev stop

## —— Dependencies ———————————————————————————————————————————————————————————————

install: ## Install all dependencies (composer + npm)
	$(COMPOSER) install
	$(NPM) install

## —— Quality ————————————————————————————————————————————————————————————————————

test: ## Run PHPUnit tests
	$(EXEC) vendor/bin/phpunit

test-cov: ## Run PHPUnit with coverage (requires Xdebug)
	$(EXEC) XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage --coverage-cobertura var/coverage/cobertura.xml

test-cov-serve: ## Serve coverage report in browser (run test-cov first)
	@echo "Coverage report at http://localhost:8888"
	@xdg-open http://localhost:8888 2>/dev/null || open http://localhost:8888 2>/dev/null || true
	php -S localhost:8888 -t var/coverage

lint: ## Run PHP-CS-Fixer
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

lint-fix: ## Run PHP-CS-Fixer and fix issues
	$(EXEC) vendor/bin/php-cs-fixer fix

analyse: ## Run Psalm static analysis
	$(EXEC) vendor/bin/psalm --threads=1

analyze: analyse ## Alias for analyse

## —— Frontend ———————————————————————————————————————————————————————————————————

dev: ## Build frontend assets (fast, for development)
	$(NPM) run dev

build: ## Build frontend assets (production)
	$(NPM) run build

watch: ## Watch frontend assets for changes
	$(NPM) run watch

## —— CI —————————————————————————————————————————————————————————————————————————

ci: lint analyse test build ## Run all CI checks

## —— Cleanup ————————————————————————————————————————————————————————————————————

clean: ## Clear caches
	$(EXEC) bin/console cache:clear
	rm -rf var/cache/*
