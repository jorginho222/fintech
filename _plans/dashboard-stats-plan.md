# Plan: Dashboard Stats — Credit Request Applications Section

Spec: `_specs/dashboard-stats.md`
Branch: `claude/feature/dashboard-stats` (already created)

## Context

The application dashboard will show summary figures grouped by section. Each section gets its own endpoint in the module it summarizes, so it can be guarded by that resource's permission. This plan covers the first section only: for the authenticated company, the total number of credit request applications and how many are approved, rejected and pending evaluation, counted over all time.

Existing pieces this builds on:
- `CreditRequestApplicationStatus` (`src/CreditRequest/Domain/Model/CreditRequestApplicationStatus.php`) — `EvaluationPending`, `Approved`, `Rejected`. Mapped as a string enum column (`status`) in the application's XML mapping.
- `CreditRequestApplicationRepositoryInterface` / `DoctrineCreditRequestApplicationRepository` — `search(companyId)` already scopes by `IDENTITY(creditRequestApplication.company) = :companyId`; the new query uses the same scoping.
- `CreditRequestApplicationSearcher` — resolves the company through `AuthenticatedCompanyIdProviderInterface`; the new use case follows it.
- `CreditRequestApplicationSearchGetController` — `#[IsGranted(Permission::CreditRequestApplicationList->value)]`; the new controller reuses that permission.

## Approach

### 1. Repository — count per status

Add to `CreditRequestApplicationRepositoryInterface`:
- `countByStatus(string $companyId): array` — returns `array<string, int>` keyed by status value; statuses with no applications are simply absent.

Implement in `DoctrineCreditRequestApplicationRepository` with a single grouped query (`SELECT status, COUNT(id) … WHERE IDENTITY(company) = :companyId GROUP BY status`), so counting happens in the database rather than by loading entities. Normalize the status key to its string value (Doctrine may hydrate the enum-typed field in scalar results as the enum or as the raw string; confirm during implementation and handle it in one place).

No new repository binding needed — the interface is already aliased in `config/services.yaml`.

### 2. Output DTO — `src/CreditRequest/Application/DTO/CreditRequestApplicationStatsDto.php`

Readonly DTO with four `int` properties: `total`, `approved`, `rejected`, `evaluationPending`.

### 3. Use case — `src/CreditRequest/Application/UseCase/CreditRequestApplicationStatsCalculator.php`

Injects `CreditRequestApplicationRepositoryInterface` and `AuthenticatedCompanyIdProviderInterface`. `execute(): CreditRequestApplicationStatsDto`:
- Calls `countByStatus()` for the authenticated company.
- Reads each of the three known statuses with a default of `0`.
- `total` = sum of **all** counts returned by the repository (so it counts every application, even one in a status the breakdown doesn't know yet).

### 4. Controller — `src/CreditRequest/UI/Api/Controller/CreditRequestApplicationStatsGetController.php`

- `#[IsGranted(Permission::CreditRequestApplicationList->value)]`
- `#[Route('/credit-request-application/stats', name: 'credit_request_application_stats', methods: ['GET'])]` (served as `/api/v1/credit-request-application/stats`).
- Returns `200` with `{"total": n, "approved": n, "rejected": n, "evaluationPending": n}` (integers).

No changes needed to `config/routes.yaml` (the `credit_request_controllers` entry already loads every controller under `src/CreditRequest/UI/Api/Controller/`) or to `services.yaml`. No migration: no mapping changes.

## Testing

1. **Functional** — `tests/Functional/CreditRequest/CreditRequestApplicationStatsGetControllerTest.php`, modeled on `CreditRequestApplicationSearchGetControllerTest` (`WebTestCase` + `ApiAuthenticationTrait`, applications created via `POST /api/v1/credit-request/apply`; approve/reject them through `EntityManagerInterface` using the entity's `approve()` / `reject()`):
   - Applications in each status → correct total and per-status counts.
   - No applications → all four counts are `0`.
   - Another company's applications are excluded.
   - Member without `credit_request_application.list` (via `createMemberAndLogin`) → `403`; member with it → `200`.
2. **Integration** — `tests/Integration/CreditRequest/DoctrineCreditRequestApplicationRepositoryTest.php`, modeled on `tests/Integration/Company/DoctrineUserRepositoryTest.php`: `countByStatus()` returns the right count per status, omits empty statuses, and is scoped to the company.
3. **Unit (guard against new statuses)** — `tests/Unit/CreditRequest/Application/UseCase/CreditRequestApplicationStatsCalculatorTest.php`: stub the repository to return one application for **every** `CreditRequestApplicationStatus::cases()` value and assert `total === approved + rejected + evaluationPending`. If a status is ever added without extending the breakdown, this test fails and forces the decision (see the spec's edge case).

Note: the spec's Testing Guidelines don't yet list the guard test (3); add a line for it there when implementing.

## Verification

1. `make bash`, then:
   - `bin/phpunit tests/Functional/CreditRequest/CreditRequestApplicationStatsGetControllerTest.php`
   - `bin/phpunit tests/Integration/CreditRequest/DoctrineCreditRequestApplicationRepositoryTest.php`
   - `bin/phpunit tests/Unit/CreditRequest/Application/UseCase/CreditRequestApplicationStatsCalculatorTest.php`
2. `make test` — no regressions.
3. Manually call `GET /api/v1/credit-request-application/stats` with a valid bearer token and confirm the response shape.
