# Plan: Company Total Debt Calculation

Spec: `_specs/company-total-debt-calculation-spec.md`
Branch: `claude/feature/company-total-debt-calculation` (already created)

## Context

The frontend "Total Debt" view is already built (see `/home/ivanur/projects/fintech-front/_specs/total-debt-view-spec.md`) and already calls a specific backend contract that doesn't exist yet. Users need to see, for their company, the total outstanding debt from today through the end of a month they pick (a "limit month"), so they can plan cash flow. This plan implements just the backend side: a new endpoint that computes that total, scoped to the authenticated company, and rejects nonsensical input (a limit month before the current one).

I inspected the actual frontend code (not just its spec) to understand the existing contract:
- `src/total-debt/api/total-debt-api.ts` currently calls `GET /installment/total-debt?month=<n>&year=<n>`.
- `src/total-debt/types/total-debt.ts` expects back `{ totalAmount: string | number }` — a raw value, not pre-formatted currency (the frontend formats it client-side).
- No other params are sent; "from today" is entirely the backend's responsibility to infer.

Per explicit direction, the backend route is `/company-total-debt` instead of the frontend's current `/installment/total-debt` (query params `month`/`year` stay as the frontend already sends them). This means the frontend's `total-debt-api.ts` needs a matching update to the new path — included as a step below so the two stay in sync.

Investigation also found that `CreditRequestRepositoryInterface::findPendingInstallmentsToPay(companyId, periodStart, periodEnd)` (implemented in `DoctrineCreditRequestRepository`) already does almost exactly what's needed: scopes by company, filters to `Active` credit requests, includes only `Pending`/`Overdue` installments (excludes `Paid`), and takes a half-open `[periodStart, periodEnd)` date range. No repository or persistence changes are needed — this feature is a new Application/UI-layer use case that calls this existing method with a different range (today → first day of the month after the limit month, instead of a single calendar month).

## Approach

Follow the existing sibling feature (`InstallmentPendingToPaySearchDto` / `InstallmentPendingToPaySearcher` / `InstallmentPendingToPaySearchGetController`, all in `src/CreditRequest/`) as the structural template, since it's the closest existing analogue in the same module and layer. Per the spec, this must be a *distinct* calculation path (not reusing that use case), but it reuses the same underlying repository method.

### 1. New DTO — `src/CreditRequest/Application/DTO/TotalDebtSearchDto.php`

Self-validating DTO built from `Request` + `ValidatorInterface` in its constructor, matching `InstallmentPendingToPaySearchDto`'s exact style:
- `month`: `#[Assert\NotBlank]`, `#[Assert\Range(min: 1, max: 12)]`
- `year`: `#[Assert\NotBlank]`, `#[Assert\Positive]`
- Read from `$request->query->get('month', 0)` / `get('year', 0)`, cast to `int`.
- New rule (not present on the sibling DTO): a `#[Assert\Callback]` method — modeled on `CreditRequestApplicationResultDecisionDto::validateDecisionConsistency` — that compares `(year, month)` against `(new \DateTimeImmutable('today'))`'s current year/month, and adds a violation (`atPath('month')`) if the limit precedes the current month/year. Guard the callback so it's skipped when `month`/`year` are already out of their basic valid range (avoids a confusing double violation on e.g. `month=0`).
- Any violation (basic or callback) throws `ValidationFailedException`, exactly like the sibling DTO — this is already caught by the existing `ApiExceptionSubscriber` and rendered as a 422 with no new wiring needed.

### 2. New UseCase — `src/CreditRequest/Application/UseCase/TotalDebtCalculator.php`

Constructor-injects `CreditRequestRepositoryInterface` and `AuthenticatedCompanyIdProviderInterface` (both autowired, same as the sibling use case). `execute(TotalDebtSearchDto $dto): string` — returns the total debt already summed, as a decimal string (not `Installment[]`; the summation now lives here instead of the controller):
- `$periodStart = new \DateTimeImmutable('today')` — literally today, not the first of any month. This is what makes "limit month = current month" correctly include only today-onward: the range never starts at the beginning of a month, always at today.
- `$periodEnd = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $dto->year, $dto->month)))->modify('first day of next month')` — same `first day of next month` trick as the sibling use case, anchored on the limit month, giving an exclusive upper bound equivalent to "through the end of the limit month, inclusive."
- No defensive check needed for `periodEnd <= periodStart` — the DTO's callback already guarantees the limit month/year is on or after the current one, so `periodEnd` is always strictly after `periodStart`.
- `$installments = $this->creditRequestRepository->findPendingInstallmentsToPay($this->authenticatedCompanyIdProvider->getCompanyId(), $periodStart, $periodEnd);` unchanged.
- Sums via `array_reduce($installments, static fn (string $total, Installment $installment): string => bcadd($total, $installment->getTotalAmount(), 2), '0.00')` (moved here from the sibling controller's pattern) and returns that string directly — already rounded to 2 decimals via `bcadd`'s scale argument, already a string.

### 3. New Controller — `src/CreditRequest/UI/Api/Controller/TotalDebtGetController.php`

Single-action `__invoke`, matching the sibling controller's structure:
- `#[Route('/company-total-debt', name: 'company_total_debt', methods: ['GET'])]`.
- Builds `TotalDebtSearchDto`, calls `$totalAmount = $this->totalDebtCalculator->execute($dto);` — the controller receives the already-summed string directly, with no summation logic of its own.
- Returns `new JsonResponse(['totalAmount' => $totalAmount], Response::HTTP_OK)` — the total amount as a string (natural output of `bcadd`), no installments list and no serializer dependency, since the spec only wants the single total (unlike the sibling endpoint, which also returns the installment list).

### 4. Frontend update — `/home/ivanur/projects/fintech-front/src/total-debt/api/total-debt-api.ts`

Update the request path from `/installment/total-debt` to `/company-total-debt` so the frontend calls the new backend route. No change needed to the query params (`month`/`year` already match) or to `TotalDebt`'s type (`totalAmount: string | number` already accepts the string the backend returns).

### No changes needed to:
- `CreditRequestRepositoryInterface` / `DoctrineCreditRequestRepository` — reused as-is.
- `config/routes.yaml` — the `credit_request_controllers` resource already attribute-registers everything under `src/CreditRequest/UI/Api/Controller/`.
- `config/services.yaml` — autowiring already covers the new DTO/UseCase/Controller (only `Domain/Model/` and `Kernel.php` are excluded); no new repository interface is being added, so no new alias entry is needed.

## Testing

New Functional test file: `tests/Functional/CreditRequest/TotalDebtGetControllerTest.php`, modeled on `tests/Functional/CreditRequest/InstallmentPendingToPaySearchGetControllerTest.php` (`WebTestCase` + `ApiAuthenticationTrait`, real entities persisted via `EntityManagerInterface`, `$client->jsonRequest('GET', ..., [], self::bearer($token))`). Since the feature is inherently "today"-relative, compute test dates from `new \DateTimeImmutable('today')` at run time rather than hardcoding calendar dates. Cover, per the spec's Testing Guidelines:
1. Pending + overdue installments due within today→limit-month-end sum correctly; paid and out-of-range installments are excluded.
2. Limit month equals the current month: an installment due earlier this month (before today) is excluded; one due today or later is included.
3. No qualifying installments → `totalAmount` is `'0.00'`, not an error.
4. A second company's installment is excluded from the authenticated company's total (cross-company isolation, same pattern as the sibling test).
5. Invalid `month`/`year` (out of range or missing) → `422` validation error.
6. `month`/`year` earlier than the current month/year → `422` validation error (not a zero total).

No new Integration test: `tests/Integration/` has no existing precedent in this repo, and the repository method being reused is unmodified. No DTO-level Unit test: no such convention exists yet for the sibling DTO either, and the new callback logic (a date comparison) is fully exercised by Functional scenarios 5–6 above.

## Verification

1. `make bash`, then `bin/phpunit tests/Functional/CreditRequest/TotalDebtGetControllerTest.php` — all new scenarios pass.
2. `bin/phpunit --testsuite Functional` — no regressions in existing CreditRequest tests.
3. Manually hit `GET /api/v1/company-total-debt?month=<current>&year=<current-year>` with a valid bearer token (via `make bash` + a REST client or curl) and confirm the response shape is exactly `{"totalAmount": "..."}` (string), then confirm the frontend's Total Debt view (after its `total-debt-api.ts` update) renders correctly against the real backend (`make up`, point the frontend dev server's API base at it).
