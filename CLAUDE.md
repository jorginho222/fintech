# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Fintech API built with **Symfony 7.3** following **Domain-Driven Design (DDD)** and **Clean Architecture** principles. PHP 8.3+. The entire dev environment runs in Docker.

## Commands

All commands run inside Docker via Make targets. The PHP container is named `php`.

```bash
make up          # Start all containers (php, nginx:8080, postgres:5432, adminer:8081)
make down        # Stop containers
make build       # Rebuild images from scratch (--no-cache)
make bash        # Shell into the php container
make install     # composer install inside container
make migrate     # Run pending Doctrine migrations
make test        # Run full PHPUnit test suite
```

Run a single test or test suite from inside the container (`make bash` first):
```bash
bin/phpunit tests/Unit/Domain/SomeTest.php
bin/phpunit --testsuite Unit
bin/phpunit --testsuite Integration
bin/phpunit --testsuite Functional
```

Generate a new migration after changing an ORM mapping:
```bash
docker compose exec php bin/console doctrine:migrations:diff
```

## Architecture

The project uses a layered DDD structure under `src/`:

```
Domain/          # Pure business logic — no framework dependencies
  Model/         # Entities and value objects (e.g. Company, TaxStatus enum)
  Repository/    # Repository interfaces only
  Event/         # Domain events (placeholder)
  Service/       # Domain services (placeholder)

Application/     # Orchestration layer
  UseCase/       # One class per use case; depends on Domain interfaces
  DTO/           # Input/output data transfer objects

Infrastructure/  # Framework & external system adapters
  Persistence/
    Doctrine/Mapping/  # XML ORM mappings (not annotations) — one file per entity
    Repository/        # Doctrine implementations of domain repository interfaces

UI/
  Api/Controller/  # Symfony controllers (HTTP entry points)
  Console/         # Symfony console commands
```

**Key constraint:** Domain models (`src/Domain/Model/`) are excluded from Symfony's service container (see `config/services.yaml`). They must stay free of framework annotations and constructor injection.

**ORM mapping:** Doctrine uses XML mapping files in `src/Infrastructure/Persistence/Doctrine/Mapping/`. Entity classes live in `Domain/Model/` but are mapped via XML — no `#[ORM\...]` attributes on domain classes.

**Repository binding:** Interface → implementation wiring is done explicitly in `config/services.yaml` under the `# Repository interface → implementation bindings` section.

**ID strategy:** Entity IDs use `strategy="NONE"` — the caller is responsible for generating UUIDs (use `symfony/uid`).

## Testing Layout

| Suite | Directory | What goes there |
|---|---|---|
| Unit | `tests/Unit/Domain/` | Pure domain logic, no DB |
| Integration | `tests/Integration/Infrastructure/` | Repository tests against real DB |
| Functional | `tests/Functional/UI/` | HTTP-level tests via BrowserKit |

Integration and functional tests use `dama/doctrine-test-bundle` to wrap each test in a rolled-back transaction — no manual teardown needed. The test DB is `fintech_test` (suffixed automatically by the `when@test` Doctrine config).

## Adding a New Feature (typical flow)

1. Add/update entity in `src/Domain/Model/`
2. Add/update repository interface in `src/Domain/Repository/`
3. Add XML mapping in `src/Infrastructure/Persistence/Doctrine/Mapping/`
4. Implement repository in `src/Infrastructure/Persistence/Repository/`
5. Bind interface → implementation in `config/services.yaml`
6. Add use case in `src/Application/UseCase/`
7. Add controller in `src/UI/Api/Controller/`
8. Generate and review migration: `bin/console doctrine:migrations:diff`
