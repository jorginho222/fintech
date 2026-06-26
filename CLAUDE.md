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
make composer args="<command>"  # Run arbitrary Composer command inside container
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

Module-first structure: `src/{Module}/{Layer}/`. Each module is self-contained.

```
src/
  {Module}/                     # e.g. Company, CreditRequest
    Domain/
      Model/                    # Entities and value objects — no framework deps
      Repository/               # Repository interfaces only
      Event/                    # Domain events
      Service/                  # Domain services
    Application/
      UseCase/                  # One class per use case
      DTO/                      # Input/output DTOs
    Infrastructure/
      Persistence/
        Doctrine/Mapping/       # XML ORM mappings — one file per entity
        Repository/             # Doctrine implementations of domain interfaces
      Service/
    UI/
      Api/Controller/           # Symfony controllers — PHP attribute routing
      Console/
```

### Modules and domain model

**Company** (`App\Company\…`): `Company` (aggregate root), `TaxStatus` enum, `CompanyRepositoryInterface` → `DoctrineCompanyRepository`

**CreditRequest** (`App\CreditRequest\…`): `CreditRequest` (owned by Company), `CreditRequestStatus` enum, `Installment`, `InstallmentStatus` enum

Cross-module references use explicit `use` imports (e.g. `CreditRequest` references `App\Company\Domain\Model\Company`).

### Key constraints

- **Domain models excluded from container:** each module's `Domain/Model/` is excluded in `config/services.yaml`. No framework annotations or DI on domain classes.
- **ORM mapping:** XML files in `{Module}/Infrastructure/Persistence/Doctrine/Mapping/`. Doctrine config in `doctrine.yaml` has one mapping entry per module. No `#[ORM\…]` on entities.
- **Repository binding:** explicit alias in `config/services.yaml` under `# Repository interface → implementation bindings`.
- **ID strategy:** `strategy="NONE"` — caller generates UUIDs via `symfony/uid`.
- **Routing:** `config/routes.yaml` has one entry per module pointing to its `UI/Api/Controller/`.

## Testing Layout

| Suite | Directory | What goes there |
|---|---|---|
| Unit | `tests/Unit/` | Pure domain logic, no DB |
| Integration | `tests/Integration/` | Repository tests against real DB |
| Functional | `tests/Functional/` | HTTP-level tests via BrowserKit |

`dama/doctrine-test-bundle` wraps each test in a rolled-back transaction. Test DB: `fintech_test`.

## Adding a New Feature (typical flow)

1. Add/update entity in `src/{Module}/Domain/Model/`
2. Add/update repository interface in `src/{Module}/Domain/Repository/`
3. Add XML mapping in `src/{Module}/Infrastructure/Persistence/Doctrine/Mapping/`
4. Implement repository in `src/{Module}/Infrastructure/Persistence/Repository/`
5. Bind interface → implementation in `config/services.yaml`
6. Add use case in `src/{Module}/Application/UseCase/`
7. Add controller in `src/{Module}/UI/Api/Controller/`; register path in `config/routes.yaml`
8. Generate and review migration: `bin/console doctrine:migrations:diff`
