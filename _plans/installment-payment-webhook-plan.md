# Plan: Installment Payment Webhook

Spec: `_specs/installment-payment-webhook-spec.md`
Branch: `claude/feature/installment-payment-webhook` (already created)

## Context

Today `PUT /installment/{id}/pay` (`InstallmentPayPutController`) runs `InstallmentToPaidUpdater` inside a transaction: `Installment::pay()` plus `CreditRequestPaidChecker`. This feature makes payment asynchronous through an external payment webhook service, mirroring the credit request application flow:

| Credit request application (existing) | Installment payment (new) |
|---|---|
| `CreditRequestApplicationHandler` saves, then dispatches `SimulateCreditRequestApplicationMessage` | `InstallmentPaymentRequester` saves the attempt, then dispatches `RequestInstallmentPaymentMessage` |
| `SimulateCreditRequestApplicationMessageHandler` → `CreditRequestApplicationSimulationService` → `CreditRequestApplicationWebhookClientInterface` | `RequestInstallmentPaymentMessageHandler` → `InstallmentPaymentWebhookClientInterface` |
| `PUT /credit-request-application-update` (`CreditRequestApplicationUpdatePutController`), secured by `CreditBureauWebhookAuthenticator` | `PUT /installment-payment-update` (`InstallmentPaymentUpdatePutController`), secured by `InstallmentPaymentGatewayWebhookAuthenticator` |
| `HandleCreditRequestApplicationResultMessage` → `CreditRequestApplicationResultHandler` | `HandleInstallmentPaymentResultMessage` → `InstallmentPaymentResultHandler` |

Plus a new hourly scheduled task for the 72 business hours timeout, changes to the overdue marker, the dashboard "Pagos" section and the serializers, and async email notifications (with a new daily due date reminder task).

Existing pieces this builds on:
- `Installment` (`src/CreditRequest/Domain/Model/Installment.php`) — `pay()` throws `InstallmentAlreadyPaidException` (409); `markOverdue()` only moves pending → overdue.
- `CreditRequestRepositoryInterface` / `DoctrineCreditRequestRepository` — `findInstallmentById`, `saveInstallment` (flushes), `findActiveCreditInstallments` (dashboard), `findWithOverdueInstallments` (overdue marker), `findPendingInstallmentsToPay` (calendar, total debt).
- `CreditRequestSchedule` (`src/CreditRequest/Infrastructure/Scheduler/`) — `RecurringMessage::every(...)` + message + handler + use case.
- `config/packages/messenger.yaml` — `async` transport is AMQP (RabbitMQ), `in-memory://` in tests.
- `config/packages/security.yaml` — `credit_bureau_webhook` firewall + `access_control` rule for the credit bureau callback.
- `CreditRequestProposalExpirationMailer` — notifier interface in `Domain/Service`, Symfony Mailer implementation in `Infrastructure/Service`, `$fromAddress` bound to `%app.mailer.from_address%` (`MAILER_FROM_ADDRESS`, `no-reply@fintech.local`), recipient `Company::getEmail()`. Users have no email address, so recipients are the company email (see spec).
- Application use cases already depend on Symfony components directly when needed (`CreditRequestApplicationHandler` uses `MessageBusInterface`).

## Approach

### 1. Domain — attempt status enum

New `src/CreditRequest/Domain/Model/InstallmentPaymentAttemptStatus.php`, string-backed:
`Processing = 'processing'`, `Approved = 'approved'`, `Rejected = 'rejected'`, `ProcessingFailed = 'processing_failed'`, `ProcessingTimeout = 'processing_timeout'`.

`InstallmentStatus` is not touched.

### 2. Domain — `Installment` changes

Three new nullable properties, initialized to `null` in the constructor: `?InstallmentPaymentAttemptStatus $lastPaymentAttemptStatus`, `?\DateTimeImmutable $lastPaymentAttemptAt` and `?string $lastPaymentAttemptErrorMessage`, with getters. They are only changed together, through these methods (no setters); every method below sets the error message to `null` except `failPaymentAttempt()`:

- `startPaymentAttempt(\DateTimeImmutable $now): void`
  - status `Paid` → `InstallmentAlreadyPaidException`;
  - attempt `Processing` → new `InstallmentPaymentInProcessException`;
  - otherwise attempt = `Processing`, at = `$now`. Installment status unchanged.
- `approvePaymentAttempt(\DateTimeImmutable $now): bool` — if `canReceivePaymentResult()`, attempt = `Approved`, at = `$now`, and calls `pay()`; returns whether it was applied.
- `rejectPaymentAttempt(\DateTimeImmutable $now): bool` — if `canReceivePaymentResult()`, attempt = `Rejected`, at = `$now`; status unchanged; returns whether it was applied.
- `failPaymentAttempt(\DateTimeImmutable $now, string $errorMessage): bool` — same as `rejectPaymentAttempt()` with attempt = `ProcessingFailed`, and stores `$errorMessage`.
- `timeoutPaymentAttempt(\DateTimeImmutable $now): void` — only from `Processing`; attempt = `ProcessingTimeout`, at = `$now`.
- `isPaymentProcessing(): bool` — attempt is `Processing`.
- private `canReceivePaymentResult(): bool` — attempt is `Processing` or `ProcessingTimeout` (covers late results; a paid installment has attempt `Approved`, and a never-attempted one `null`, so both are ignored).
- `markOverdue()` — additionally returns early when `isPaymentProcessing()`.

`pay()` stays as is (used by `approvePaymentAttempt()`), so the "already paid" rule lives in one place.

### 3. Domain — exception

New `src/CreditRequest/Domain/Exception/InstallmentPaymentInProcessException.php`, extends `DomainHttpException`, `409`, message: `El pago de esta cuota está siendo procesado. Espere la confirmación antes de volver a intentarlo.`

### 4. Domain — business hours

New `src/CreditRequest/Domain/Service/BusinessHoursCalculator.php` (plain class, like `InstallmentDueDateCalculator`):
- `addBusinessHours(\DateTimeImmutable $from, int $hours): \DateTimeImmutable` — walks forward counting only time on Monday–Friday (all 24h). If `$from` falls on a weekend, counting starts at the next Monday 00:00. Example: Friday 10:00 + 72h → Wednesday 10:00; Saturday 15:00 + 72h → Thursday 00:00.
- Uses the `DateTimeImmutable`'s own timezone (the app default), no holidays.

### 5. Persistence — mapping, repository, migration

`Installment.orm.xml`:
- `lastPaymentAttemptStatus` → column `last_payment_attempt_status`, `type="string"`, `enum-type="…InstallmentPaymentAttemptStatus"`, `nullable="true"`.
- `lastPaymentAttemptAt` → column `last_payment_attempt_at`, `datetime_immutable`, `nullable="true"`.
- `lastPaymentAttemptErrorMessage` → column `last_payment_attempt_error_message`, `text`, `nullable="true"`.

**Generic installment search.** Instead of adding one `find…` method per use, the installment list queries are merged into a single criteria-based search.

New `src/CreditRequest/Domain/Repository/InstallmentSearchCriteria.php` — readonly value object (no framework deps, never injected), every field optional and combined with AND:
- `?string $companyId` — `IDENTITY(creditRequest.company) = :companyId`
- `?CreditRequestStatus $creditRequestStatus`
- `?InstallmentStatus $status`
- `?InstallmentPaymentAttemptStatus $paymentAttemptStatus` — attempt equals it
- `?InstallmentPaymentAttemptStatus $excludedPaymentAttemptStatus` — `(attempt IS NULL OR attempt != :excluded)`
- `?\DateTimeImmutable $dueFrom` (inclusive), `?\DateTimeImmutable $dueBefore` (exclusive)

Callers build it with named arguments, e.g. `new InstallmentSearchCriteria(companyId: $id, creditRequestStatus: CreditRequestStatus::Active, status: InstallmentStatus::Overdue, excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing)`.

`CreditRequestRepositoryInterface` / `DoctrineCreditRequestRepository`:
- **Add** `searchInstallments(InstallmentSearchCriteria $criteria): array` — joins and fetches `creditRequest` (as `findActiveCreditInstallments` does today), applies only the non-null criteria, ordered by due date then period number.
- **Remove** `findActiveCreditInstallments()` and `findWithOverdueInstallments()`; their callers move to `searchInstallments()`:

  | Caller | Criteria |
  |---|---|
  | Dashboard "Pagos del mes" (pending part) | company, `Active`, `Pending`, `dueFrom` month start, `dueBefore` next month start, exclude `Processing` |
  | Dashboard processing payments | company, `Active`, attempt `Processing` |
  | Dashboard "Pagos vencidos" | company, `Active`, `Overdue`, exclude `Processing` |
  | `InstallmentOverdueMarker` | `Pending`, `dueBefore` now, exclude `Processing` (all companies and credit statuses, as today) |
  | `InstallmentPaymentAttemptTimeoutMarker` | attempt `Processing` (all companies) |

  Excluding processing in the overdue marker's criteria keeps the penalty updater from running on them; the domain guard in `markOverdue()` stays as a safety net.
- **Keep** `findPendingInstallmentsToPay()` as is. Its "overdue at any date OR pending in the period" condition doesn't fit AND-only criteria, `TotalDebtCalculator` also depends on it, and this feature doesn't need it changed: an installment with a processing attempt is always pending or overdue, so the calendar's existing rules already list it (covered by a test).
- **Keep** `countByCompanyAndStatus()` / `countInstallmentsByStatus()` (aggregates, not lists).

Migration: `bin/console doctrine:migrations:diff`, review that it only adds the three nullable columns to `installments` (no backfill).

### 6. Pay flow — use case, message, client

- **Use case** `src/CreditRequest/Application/UseCase/InstallmentPaymentRequester.php` replaces `InstallmentToPaidUpdater` (deleted, with its unit test). Same ownership check (`InstallmentNotFoundException`), then `startPaymentAttempt(new \DateTimeImmutable())`, `saveInstallment()`, then dispatch `RequestInstallmentPaymentMessage($installmentId)`, then `notifyPaymentProcessing()` (step 11). Returns the `CreditRequest`.
- **Controller** `InstallmentPayPutController` is renamed to `InstallmentPayRequestPutController` (file and class), with route `#[Route('/installment/{id}/pay-request', name: 'installment_pay_request', methods: ['PUT'])]` (was `/installment/{id}/pay`, `installment_pay`); permission `Permission::InstallmentPay` unchanged. Update the path in `tests/Functional/UI/PermissionEnforcementTest.php`; the frontend must call the new path. It calls `InstallmentPaymentRequester` **without** the `transactional()` wrapper and returns `202` with the serialized credit request. Rationale: the transport is AMQP, so dispatching inside an open DB transaction could let the worker read the installment before commit, see no processing attempt and send nothing. Saving (one entity, auto-committed by `flush()`) before dispatching avoids that, like `CreditRequestApplicationHandler`. If the dispatch itself fails after the commit, the attempt stays processing and the timeout task releases it.
- **Message** `src/CreditRequest/Application/Message/RequestInstallmentPaymentMessage.php` (installment id), routed to `async` in `messenger.yaml`.
- **Handler** `src/CreditRequest/Application/MessageHandler/RequestInstallmentPaymentMessageHandler.php` — loads the installment; returns without sending if it's missing or `!isPaymentProcessing()`; otherwise calls the client. HTTP errors propagate so Messenger retries and then moves the message to `failed`.
- **Client** `src/CreditRequest/Domain/Service/InstallmentPaymentWebhookClientInterface.php` → `src/CreditRequest/Infrastructure/Service/InstallmentPaymentWebhookClient.php` — `sendRequest(string $installmentId, string $cuit, string $amount)`: `POST` to the configured URL, JSON `{installmentId, cuit, amount}` and header `Idempotency-Key: <installmentId>`.
- **Config** — `.env`: `PAYMENT_GATEWAY_SIMULATION_WEBHOOK_URL`, `PAYMENT_GATEWAY_WEBHOOK_SECRET` (placeholders like the credit bureau ones; set test values in `.env.test`). `services.yaml`: parameters `app.payment_gateway_simulation.webhook_url` / `.webhook_secret`, interface alias and `$webhookUrl` argument next to the credit bureau ones.

### 7. Result flow — endpoint, message, use case

- **Authenticator** `src/CreditRequest/Infrastructure/Security/InstallmentPaymentGatewayWebhookAuthenticator.php` — same as `CreditBureauWebhookAuthenticator` (`X-Webhook-Secret` header, `hash_equals`) with its own secret, user identifier `installment-payment-gateway-webhook` and role `ROLE_INSTALLMENT_PAYMENT_GATEWAY_WEBHOOK`, so each external service has its own credential.
- **Security** — new `installment_payment_gateway_webhook` firewall (pattern `^%app.api_prefix%/installment-payment-update$`, stateless, the new authenticator) placed before `api`, and an `access_control` rule `PUT` → `ROLE_INSTALLMENT_PAYMENT_GATEWAY_WEBHOOK` before the generic `ROLE_COMPANY` rule.
- **DTO** `src/CreditRequest/Application/DTO/InstallmentPaymentResultDto.php` — built from the `Request` and validated in the constructor like `CreditRequestApplicationResultDto`: `installmentId` (`NotBlank`, `Uuid`), `status` (`NotBlank`, `Choice(['APPROVED', 'REJECTED', 'PROCESSING_FAILED'])`), `?string errorMessage` (`NotBlank` and `Length(max: 1000)` only when `status` is `PROCESSING_FAILED`, via `Assert\When`; ignored otherwise), validation messages in Spanish.
- **Controller** `src/CreditRequest/UI/Api/Controller/InstallmentPaymentUpdatePutController.php` — `#[Route('/installment-payment-update', name: 'installment_payment_update', methods: ['PUT'])]`; validates eagerly, dispatches `HandleInstallmentPaymentResultMessage($request->getContent())`, returns `202` with no body.
- **Message + handler** `HandleInstallmentPaymentResultMessage` (raw payload, routed to `async`) and `HandleInstallmentPaymentResultMessageHandler` — rebuilds the DTO and runs the use case inside `TransactionManagerInterface::transactional()`, like `HandleCreditRequestApplicationResultMessageHandler`. Then, **after `transactional()` returns**, passes the outcome it returned to `InstallmentPaymentResultNotifier`. `transactional()` (`EntityManager::wrapInTransaction()`) only returns once the commit succeeded and throws otherwise, so the emails are queued only for committed results; on a failed commit the message is retried and nothing was emailed.
- **Use case** `src/CreditRequest/Application/UseCase/InstallmentPaymentResultHandler.php`:
  - unknown installment → `InstallmentNotFoundException` (message ends in `failed`);
  - `APPROVED` → `approvePaymentAttempt($now)`; if applied, `saveInstallment()` and `CreditRequestPaidChecker::execute()`;
  - `REJECTED` → `rejectPaymentAttempt($now)`; if applied, `saveInstallment()`;
  - `PROCESSING_FAILED` → `failPaymentAttempt($now, $dto->errorMessage)`; if applied, `saveInstallment()`;
  - not applied → no write.
  - It does **not** notify. It returns an `InstallmentPaymentResultOutcome` (`src/CreditRequest/Application/DTO/`, readonly): `Installment $installment`, `?InstallmentPaymentAttemptStatus $appliedAttemptStatus` (`null` when the result was ignored) and `bool $creditRequestPaid` (the checker's return value).
- `CreditRequestPaidChecker::execute()` returns `bool` (whether it changed the credit request to paid) instead of `void`; its only other caller, `InstallmentToPaidUpdater`, is deleted. Update `CreditRequestPaidCheckerTest`.

### 8. Timeout task

- `TimeoutInstallmentPaymentAttemptsMessage` + `TimeoutInstallmentPaymentAttemptsMessageHandler` (passes `new \DateTimeImmutable()`), registered in `CreditRequestSchedule` with `RecurringMessage::every('1 hour', …)`.
- Use case `src/CreditRequest/Application/UseCase/InstallmentPaymentAttemptTimeoutMarker.php` — `execute(\DateTimeImmutable $now): int`: loads `searchInstallments()` with attempt `Processing`, and for each one where `BusinessHoursCalculator::addBusinessHours($attemptAt, 72) <= $now`, calls `timeoutPaymentAttempt($now)`, saves and calls `notifyPaymentProcessingTimeout()`. Returns the count, like `InstallmentOverdueMarker`. The 72-hour filter runs in PHP because it can't be expressed simply in SQL; processing attempts are few at any time.

### 9. Dashboard "Pagos" section

`InstallmentPaymentsDashboardSearcher`:
- `monthPayments` = `searchInstallments()` for pending installments due this month excluding processing, merged with `searchInstallments()` for processing installments, sorted by due date then period number (criteria in the table in step 5). The exclusion avoids listing a processing installment due this month twice; two searches are needed because "pending in the month OR processing" is an OR.
- `overduePayments` = `searchInstallments()` for overdue installments excluding processing.
- Totals keep using `sumTotalAmounts()`, so processing payments count in the "Pagos del mes" total.

The calendar (`InstallmentPendingToPaySearcher`) needs no change (see step 5).

### 10. Serialization

Add `lastPaymentAttemptStatus` (`?->value`) and `lastPaymentAttemptAt` (ATOM or `null`) to `InstallmentSerializer::serialize()` (dashboard + calendar) and `CreditRequestSerializer::serializeInstallment()` (credit request detail + pay response). `lastPaymentAttemptErrorMessage` is **not** serialized (stored for support only, per the spec).

### 11. Email notifications

**Common use case** — `src/Shared/Application/UseCase/EmailSender.php`: `execute(string $from, string $to, string $subject, string $text): void`, builds a `Symfony\Component\Mime\Email` and calls Symfony `MailerInterface::send()`. No other logic, reusable from any module. (`CreditRequestProposalExpirationMailer` could move onto it later; not changed here.)

**Async** — route `Symfony\Component\Mailer\Messenger\SendEmailMessage: async` in `messenger.yaml`. `MailerInterface::send()` then only queues the email; the worker delivers it, with Messenger's retries and `failed` transport. This also makes the existing proposal expiration email async, which is fine (it runs from a scheduled task).

**Notifier port** — `src/CreditRequest/Domain/Service/InstallmentPaymentNotifierInterface.php`, one method per event in the spec's table:
- `notifyPaymentProcessing(Installment)`, `notifyPaymentApproved(Installment)`, `notifyPaymentRejected(Installment)`, `notifyPaymentProcessingFailed(Installment)`, `notifyPaymentProcessingTimeout(Installment)`, `notifyInstallmentOverdue(Installment)`, `notifyDueDateReminder(Installment)`, `notifyCreditRequestPaid(CreditRequest)`.

**Mailer** — `src/CreditRequest/Infrastructure/Service/InstallmentPaymentMailer.php` implements it, like `CreditRequestProposalExpirationMailer`: `$fromAddress` bound to `%app.mailer.from_address%` in `services.yaml` (plus the interface alias), recipient `Company::getEmail()` via the installment's credit request, Spanish subject and plain text body per the spec's table. A private helper builds the shared installment block (reference, `periodNumber/installmentQuantity`, `totalAmount`, due date `d/m/Y`) and the `Hola <socialReason>,` greeting; each method only adds its subject and sentence, then calls `EmailSender::execute()`. The processing failed email never includes `lastPaymentAttemptErrorMessage`.

**Callers** (each notifies right after saving the change it reports, so an email is never sent for an unsaved change):
- `InstallmentPaymentRequester` — processing (step 6).
- `InstallmentPaymentResultNotifier` (new, `src/CreditRequest/Application/UseCase/`) — `execute(InstallmentPaymentResultOutcome $outcome): void`, called by `HandleInstallmentPaymentResultMessageHandler` after the transaction committed (step 7). Maps `appliedAttemptStatus` → `notifyPaymentApproved()` / `notifyPaymentRejected()` / `notifyPaymentProcessingFailed()`, then `notifyCreditRequestPaid()` when `creditRequestPaid`; does nothing when `appliedAttemptStatus` is `null`.
- `InstallmentPaymentAttemptTimeoutMarker` — timeout (step 8).
- `InstallmentOverdueMarker` — after `saveInstallment()`, `notifyInstallmentOverdue()` for each installment it marks (the search only returns pending, non-processing installments, so every one of them changes to overdue).

**Due date reminder task**:
- `SendInstallmentDueDateRemindersMessage` + handler (passes `new \DateTimeImmutable()`), `RecurringMessage::every('1 day', …)` in `CreditRequestSchedule`.
- Use case `src/CreditRequest/Application/UseCase/InstallmentDueDateReminder.php` — `execute(\DateTimeImmutable $now): int`: `searchInstallments()` with `creditRequestStatus: Active`, `status: Pending`, `dueFrom: today + 3 days 00:00`, `dueBefore: today + 4 days 00:00`, `excludedPaymentAttemptStatus: Processing`; calls `notifyDueDateReminder()` for each and returns the count. Reminder offset as a class constant (`REMINDER_DAYS_BEFORE_DUE = 3`).

### 12. Local email testing — Mailpit

- `docker-compose.yml`: new `mailpit` service, image `axllent/mailpit` pinned to a specific version tag (e.g. `v1.27`, never `latest`), ports `8025:8025` (web UI) and `1025:1025` (SMTP). Add `mailpit` to `depends_on` of `php` and `worker` (the worker is the one delivering emails, since `SendEmailMessage` goes through `async`).
- `.env.example`: `MAILER_DSN=smtp://mailpit:1025` as the default for the Docker setup (copied to `.env` by the README's setup step). `.env.test` keeps `null://null`: tests assert on the queued `SendEmailMessage`, they never deliver.
- `README.md`:
  - environment table: `MAILER_DSN` → `smtp://mailpit:1025` (or your SMTP server); also add `PAYMENT_GATEWAY_SIMULATION_WEBHOOK_URL` and `PAYMENT_GATEWAY_WEBHOOK_SECRET` (step 6);
  - services table: `Mailpit (local email inbox) | http://localhost:8025`.
- `CLAUDE.md`: add `mailpit:8025` to the `make up` line's list of containers.
- No production impact: production sets its own `MAILER_DSN`.

## Testing

1. **Unit — `Installment`**: new `tests/Unit/CreditRequest/Domain/Model/InstallmentTest.php`:
   - `startPaymentAttempt` from no attempt / rejected / processing failed / timeout → processing with date, status unchanged (pending and overdue cases);
   - from processing → `InstallmentPaymentInProcessException`; when paid → `InstallmentAlreadyPaidException`;
   - `approvePaymentAttempt` from processing and from timeout → approved + paid; from null / approved → returns false, nothing changes;
   - `rejectPaymentAttempt` → rejected, `failPaymentAttempt` → processing failed with the error message, status unchanged in both;
   - starting a new attempt (and any other result) clears the error message;
   - `markOverdue` is a no-op while processing.
2. **Unit — `BusinessHoursCalculatorTest`**: weekday-only span; Friday 10:00 + 72 → Wednesday 10:00; start on Saturday → counts from Monday 00:00.
3. **Unit — `InstallmentPaymentAttemptTimeoutMarkerTest`**: stubbed repository; attempts older than the limit time out, newer ones are untouched.
4. **Unit — `InstallmentPaymentResultHandlerTest`**: approved pays and calls the paid checker (last installment → credit request paid); late approval after timeout still pays; rejected and processing failed only update the attempt (processing failed stores the error message); result for a paid installment writes nothing; unknown installment throws. Replaces `InstallmentToPaidUpdaterTest` (deleted).
5. **Unit — `InstallmentPaymentsDashboardSearcherTest`**: switch the mocked method to `searchInstallments`, asserting the criteria passed; processing installments are merged into the month list (sorted) and excluded from overdue.
6. **Functional — new `InstallmentPayRequestPutControllerTest`** (`PUT /api/v1/installment/{id}/pay-request`): `202`, installment has a processing attempt, `RequestInstallmentPaymentMessage` is in the `async` in-memory transport; second call → `409` with the Spanish message; another company's installment → `404`; member without `installment.pay` → `403`.
7. **Functional — new `InstallmentPaymentUpdatePutControllerTest`** (modeled on `CreditRequestApplicationUpdatePutControllerTest`): valid payload + secret → `202` and message queued, nothing applied synchronously; wrong/missing secret → `401`; invalid `status`, missing `installmentId`, or `PROCESSING_FAILED` without `errorMessage` → `4xx`, nothing queued.
8. **Functional — `MarkOverdueInstallmentsCommandTest`**: add cases — past-due pending with processing attempt is not marked; with rejected / processing failed / timeout attempt it is.
9. **Functional — `InstallmentPaymentsDashboardGetControllerTest` / `InstallmentPendingToPaySearchGetControllerTest`**: processing installment (overdue, and due in another month) listed under `monthPayments` and counted in its total; calendar lists processing installments and both responses expose `lastPaymentAttemptStatus` / `lastPaymentAttemptAt`.
10. **Integration — `DoctrineCreditRequestRepositoryTest`**: port the existing `findActiveCreditInstallments` tests to `searchInstallments()`, and add: each criterion filters on its own (company, credit request status, status, due range, attempt status); `excludedPaymentAttemptStatus` keeps installments with a `null` attempt; empty criteria returns all installments, ordered by due date.
11. **Unit — `EmailSenderTest`**: mocked `MailerInterface` receives an `Email` with the given from, to, subject and text.
12. **Unit — `InstallmentPaymentMailerTest`** (like `CreditRequestProposalExpirationMailerTest`): one or two representative events — recipient is the company email, sender is the no-reply address, Spanish subject, body contains reference, `3/12`, amount and due date; the processing failed email does not contain the stored error message.
13. **Unit — notifier calls**: with a mocked `InstallmentPaymentNotifierInterface`, extend the use case tests above — requester notifies processing; result handler returns the right outcome (applied status, `creditRequestPaid` on the last installment, `null` status for ignored results) and never calls the notifier; new `InstallmentPaymentResultNotifierTest` maps each outcome to the right notifier calls and makes none for an ignored result; `HandleInstallmentPaymentResultMessageHandler` test: when `transactional()` throws, the notifier is not called; timeout marker notifies timeout; new `InstallmentOverdueMarkerTest` notifies each marked installment.
14. **Unit — `InstallmentDueDateReminderTest`**: asserts the criteria passed to `searchInstallments()` (3-day window, pending, active, processing excluded) and one notification per returned installment.
15. **Functional — `InstallmentPayRequestPutControllerTest`**: also asserts a `SendEmailMessage` addressed to the company email is in the `async` in-memory transport.

## Verification

1. `make migrate` after generating and reviewing the migration.
2. `make bash`, then run the new/changed test files individually, then `make test` for regressions.
3. Manually: `PUT /api/v1/installment/{id}/pay-request` → `202` with `lastPaymentAttemptStatus: processing`; consume with `bin/console messenger:consume async -vv` and check the outgoing request (URL, body, `Idempotency-Key`); `PUT /api/v1/installment-payment-update` with the secret and `APPROVED` / `REJECTED` / `PROCESSING_FAILED`, consume again, and check the installment and credit request; check the dashboard and calendar responses.
4. Emails: with `MAILER_DSN=smtp://mailpit:1025` in `.env` and the `worker` container running, go through the flow above and open http://localhost:8025 to check each email's sender (no-reply), recipient (company email), Spanish subject and body. Same for the timeout, overdue and reminder emails after triggering their tasks.
5. Timeout: set an attempt's `last_payment_attempt_at` back over 72 business hours and run `bin/console messenger:consume scheduler_credit_request --limit=1` (or call the use case) to see it become `processing_timeout`.
