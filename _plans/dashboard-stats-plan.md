# Plan: Dashboard Stats

Spec: `_specs/dashboard-stats-spec.md`
Branch: `claude/feature/dashboard-stats` (already created)

Sections:
- Part 1 — Credit request applications section (implemented in `bd56df7`)
- Part 2 — Créditos en curso section (see the end of this file)

# Part 1: Credit Request Applications Section

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

---

# Part 2: Créditos en curso Section

## Context

The dashboard gets a second section, "Créditos en curso", with two cards for the authenticated company's active credit requests: "Total creditos" (how many there are) and "Porcentaje pagado" (paid installments over all installments of those credits, pooled). It follows the same one-endpoint-per-section rule as Part 1.

The spec also requires the "Créditos en curso" list to show only active credit requests, to match the stats. That's already the case: the frontend screen calls `GET /credit-request/search`, `CreditRequestSearcher` passes `CreditRequestStatus::Active`, and `tests/Functional/CreditRequest/CreditRequestSearchGetControllerTest.php` (`testSearchReturnsOnlyActiveCreditRequestsOwnedByTheAuthenticatedCompany`) covers it. **No change to the search.**

Existing pieces this builds on:
- `CreditRequestStatus` (`Proposal`, `ProposalExpired`, `Active`, `Paid`) and `InstallmentStatus` (`Pending`, `Paid`, `Overdue`), both string enum columns.
- `CreditRequestRepositoryInterface` / `DoctrineCreditRequestRepository` — `search(companyId, status)` already scopes by `IDENTITY(creditRequest.company) = :companyId` and `creditRequest.status = :status`; the new queries use the same scoping. `Installment` maps `creditRequest` as many-to-one, so installments are joined through it.
- Part 1's use case / DTO / controller trio is the structural template.

## Approach

### 1. Repository — two aggregate queries

Add to `CreditRequestRepositoryInterface`:
- `countByCompanyAndStatus(string $companyId, CreditRequestStatus $status): int` — `COUNT` of the company's credit requests in that status.
- `countInstallmentsByStatus(string $companyId, CreditRequestStatus $creditRequestStatus): array` — `array<string, int>` keyed by installment status value, counting the installments of the company's credit requests in that credit request status (`GROUP BY installment.status`); statuses without installments are absent, like Part 1's `countByStatus()`.

Implement both in `DoctrineCreditRequestRepository` with query builder aggregates (no entity loading).

### 2. Output DTO — `src/CreditRequest/Application/DTO/CreditRequestInProgressStatsDto.php`

Readonly: `int $total`, `?float $paidPercentage`.

### 3. Use case — `src/CreditRequest/Application/UseCase/CreditRequestInProgressStatsCalculator.php`

Injects `CreditRequestRepositoryInterface` and `AuthenticatedCompanyIdProviderInterface`. `execute(): CreditRequestInProgressStatsDto`, always with `CreditRequestStatus::Active`:
- `total` = `countByCompanyAndStatus(companyId, Active)`.
- Installment counts from `countInstallmentsByStatus(companyId, Active)`; `installmentTotal = array_sum(counts)`, `paid = counts[InstallmentStatus::Paid->value] ?? 0`.
- `paidPercentage` = `null` when `installmentTotal === 0` (no active credits), otherwise `round($paid * 100 / $installmentTotal, 2)`.

### 4. Controller — `src/CreditRequest/UI/Api/Controller/CreditRequestInProgressStatsGetController.php`

- `#[IsGranted(Permission::CreditRequestList->value)]`
- `#[Route('/credit-request/stats', name: 'credit_request_stats', methods: ['GET'], priority: 1)]` — `priority: 1` like `CreditRequestSearchGetController`, so `stats` isn't captured by `/credit-request/{id}` (`CreditRequestFindGetController`).
- Returns `200` with `{"total": n, "paidPercentage": 37.5 | null}`.

No changes to `config/routes.yaml`, `services.yaml` or migrations.

## Testing

1. **Functional** — `tests/Functional/CreditRequest/CreditRequestInProgressStatsGetControllerTest.php`, building credit requests and installments through `EntityManagerInterface` like `TotalDebtGetControllerTest`:
   - Active credits with paid, pending and overdue installments → correct total and pooled percentage (installment counts chosen so pooling and per-credit averaging give different results).
   - Proposal, expired proposal and paid credit requests (with installments) are excluded.
   - No active credits → `{"total": 0, "paidPercentage": null}`.
   - Another company's credit requests are excluded.
   - Member without `credit_request.list` → `403`; with it → `200`. Unauthenticated → `401`.
2. **Integration** — `tests/Integration/CreditRequest/DoctrineCreditRequestRepositoryTest.php`: both new methods return the right counts, filter by credit request status and company, and omit installment statuses without installments.
3. **Unit** — `tests/Unit/CreditRequest/Application/UseCase/CreditRequestInProgressStatsCalculatorTest.php` with a stubbed repository: `null` with no installments, `0` with none paid, `100` with all paid, and rounding (1 of 3 → `33.33`).
4. **Search** — no new test; the existing `CreditRequestSearchGetControllerTest` already asserts only active credit requests are returned.

## Verification

1. `make bash`, then run the three new test files with `bin/phpunit <path>`.
2. `make test` — no regressions (including `CreditRequestSearchGetControllerTest` and `CreditRequestFindGetController` routes).
3. Manually call `GET /api/v1/credit-request/stats` with a valid bearer token and confirm the response shape.
