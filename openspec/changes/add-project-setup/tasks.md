## 1. DDEV Setup
- [x] 1.1 Run `ddev config` to create `.ddev/config.yaml` (PHP 8.4, nginx-fpm, project type: php)
- [x] 1.2 Configure Node.js version in DDEV
- [x] 1.3 Verify `ddev start` works

## 2. Backend Setup
- [x] 2.1 Initialize Symfony 7.2 skeleton project (`ddev composer create symfony/skeleton:"7.2.*"`)
- [x] 2.2 Set PHP 8.4 requirement in `composer.json`
- [x] 2.3 Create hexagonal directory structure (`src/Domain/`, `src/Application/`, `src/Infrastructure/`)
- [x] 2.4 Move Symfony controllers to `src/Infrastructure/Controller/`
- [x] 2.5 Add `var/api-cache/` to `.gitignore`

## 3. Code Quality Tools
- [x] 3.1 Install PHP-CS-Fixer (`ddev composer require --dev friendsofphp/php-cs-fixer`)
- [x] 3.2 Create `.php-cs-fixer.dist.php` with PSR-12 rules
- [x] 3.3 Install Psalm (`ddev composer require --dev vimeo/psalm`)
- [x] 3.4 Create `psalm.xml` with level 3 configuration
- [x] 3.5 Install PHPUnit (`ddev composer require --dev phpunit/phpunit`)
- [x] 3.6 Configure PHPUnit with `phpunit.xml.dist`
- [x] 3.7 Create `tests/` directory structure (Unit, Integration)
- [x] 3.8 Add placeholder test to verify PHPUnit works

## 4. Frontend Setup
- [x] 4.1 Install Webpack Encore (`ddev composer require symfony/webpack-encore-bundle`)
- [x] 4.2 Run `ddev npm install`
- [x] 4.3 Install Vue.js 3 (`ddev npm install vue@3 vue-loader@17`)
- [x] 4.4 Configure `webpack.config.js` for Vue.js
- [x] 4.5 Create minimal `assets/app.js` entry point
- [x] 4.6 Verify `ddev npm run build` succeeds

## 5. Makefile
- [x] 5.1 Create `Makefile` with DDEV-wrapped commands
- [x] 5.2 Add `start`, `stop` targets
- [x] 5.3 Add `install` target (composer + npm)
- [x] 5.4 Add `test` target (PHPUnit)
- [x] 5.5 Add `lint` target (PHP-CS-Fixer)
- [x] 5.6 Add `analyse` target (Psalm)
- [x] 5.7 Add `build` target (npm build)
- [x] 5.8 Add `ci` target (lint + analyse + test + build)
- [x] 5.9 Add `help` target documenting all commands

## 6. CI Pipeline
- [x] 6.1 Create `.github/workflows/ci.yml`
- [x] 6.2 Add PHP lint job (PHP-CS-Fixer)
- [x] 6.3 Add Psalm static analysis job
- [x] 6.4 Add PHPUnit test job
- [x] 6.5 Add npm build job
- [x] 6.6 Verify workflow runs on push

## 7. Documentation
- [x] 7.1 Create `README.md` with project overview
- [x] 7.2 Add prerequisites section (DDEV installation)
- [x] 7.3 Add quick start instructions
- [x] 7.4 Document available Makefile commands
- [x] 7.5 Update `openspec/project.md` with confirmed tech choices (PHP 8.4, Symfony 7.4, DDEV, no database)
