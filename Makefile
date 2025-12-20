.PHONY: help start stop install test lint analyse build ci clean

.DEFAULT_GOAL := help

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
	ddev composer install
	ddev npm install

## —— Quality ————————————————————————————————————————————————————————————————————

test: ## Run PHPUnit tests
	ddev exec vendor/bin/phpunit

lint: ## Run PHP-CS-Fixer
	ddev exec vendor/bin/php-cs-fixer fix --dry-run --diff

lint-fix: ## Run PHP-CS-Fixer and fix issues
	ddev exec vendor/bin/php-cs-fixer fix

analyse: ## Run Psalm static analysis
	ddev exec vendor/bin/psalm

## —— Frontend ———————————————————————————————————————————————————————————————————

build: ## Build frontend assets
	ddev npm run build

watch: ## Watch frontend assets for changes
	ddev npm run watch

## —— CI —————————————————————————————————————————————————————————————————————————

ci: lint analyse test build ## Run all CI checks

## —— Cleanup ————————————————————————————————————————————————————————————————————

clean: ## Clear caches
	ddev exec bin/console cache:clear
	rm -rf var/cache/*
