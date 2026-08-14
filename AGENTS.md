# Repository Guidelines

## Project Structure & Module Organization

This is a PHP 8.3+ Symfony 7.4 API organized by domain module and Clean Architecture layer. Production code lives under `src/{Module}/`; the main modules are `Company`, `CreditRequest`, and `Shared`. Keep business rules in `Domain`, orchestration and DTOs in `Application`, adapters and Doctrine repositories in `Infrastructure`, and HTTP/console entry points in `UI`.

Doctrine XML mappings belong in `src/{Module}/Infrastructure/Persistence/Doctrine/Mapping`; schema changes belong in `migrations/`. Symfony configuration is under `config/`, the web entry point is `public/index.php`, and container setup is in `docker/` and `docker-compose.yml`. Tests mirror application concerns under `tests/Unit`, `tests/Integration`, and `tests/Functional`.

## Build, Test, and Development Commands

Use the repository's Docker-backed Make targets:

- `make up` starts PHP, nginx, PostgreSQL, and Adminer.
- `make install` installs Composer dependencies in the PHP container.
- `make migrate` applies Doctrine migrations non-interactively.
- `make test` runs the complete PHPUnit suite.
- `make bash` opens a shell in the PHP container.
- `make down` stops the development stack; `make build` rebuilds images without cache.

From `make bash`, run a focused test with `bin/phpunit tests/Unit/.../SomeTest.php` or a suite with `bin/phpunit --testsuite Functional`. After mapping changes, generate a migration with `bin/console doctrine:migrations:diff` and review it before committing.

## Coding Style & Naming Conventions

Follow PSR-4 (`App\\` maps to `src/`) and existing PHP style: four-space indentation, strict types, one class per file, and explicit type declarations. Use `PascalCase` classes, `camelCase` methods/properties, and descriptive suffixes such as `Dto`, `Interface`, `Controller`, `MessageHandler`, and `Test`. Keep domain models framework-independent; define Doctrine metadata in XML rather than entity attributes. Bind repository interfaces explicitly in `config/services.yaml`.

## Testing Guidelines

PHPUnit 11 is configured in `phpunit.xml.dist`. Name tests `*Test.php` and test methods for observable behavior. Unit tests must avoid infrastructure; integration tests may exercise Doctrine; functional tests cover HTTP and console workflows. Add regression coverage for bug fixes. No coverage threshold is configured, so prioritize meaningful paths and domain edge cases.

## Commit & Pull Request Guidelines

Recent commits use concise, imperative, sentence-case subjects, for example `Add scheduled command to mark overdue installments`. Keep each commit focused. Pull requests should explain the behavior change, note migrations or configuration updates, link the relevant issue, and include test evidence. For API changes, document affected routes and representative requests/responses; add screenshots only when a visual interface is involved.
