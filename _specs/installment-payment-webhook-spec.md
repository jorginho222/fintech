# Spec for installment-payment-webhook

branch: claude/feature/installment-payment-webhook

## Summary
Today, clicking "Pagar" on an installment pays it right away: the installment is marked as paid and, when it was the last unpaid one, its credit request is marked as paid. This feature replaces that synchronous payment with an asynchronous flow through an external payment webhook service, following the same pattern already used for credit request applications (request queued → external service called → service calls back an app endpoint → result handled asynchronously).

The installment status (pending, paid, overdue) stays as it is. The outcome of payments is tracked separately, in a new **last payment attempt** on the installment: an attempt status (processing, approved, rejected, processing failed, processing timeout) plus the date it reached that status. This lets the UI show both facts at once, e.g. an overdue installment whose last payment was rejected.

New flow:

1. The user clicks "Pagar". The installment's last payment attempt becomes **processing** ("Pago en proceso de confirmación") and a message is queued. The response returns immediately. The installment status does not change.
2. The queued message is consumed asynchronously and sends the payment request to the external payment webhook service, using the installment identifier as idempotency key.
3. The service randomly decides whether the payment is approved or rejected (or reports that it could not process it), and calls a new app endpoint (PUT) with the decision.
4. The endpoint validates the payload and queues the result for asynchronous handling.
5. When handled:
   - **Approved**: the attempt becomes approved, the installment is marked as paid, and the credit request is marked as paid if all its installments are paid (same behavior as today's payment).
   - **Rejected**: the attempt becomes rejected ("Pago rechazado"). The installment keeps its status (pending or overdue), and the user can try to pay it again.
   - **Processing failed**: the service could not process the payment. The attempt becomes processing failed ("No se pudo procesar el pago") and is handled like a rejection: the installment keeps its status and the user can try again.
6. A scheduled task marks as **processing timeout** every attempt still processing after 72 business hours, so the UI can tell the user and let them retry. A result that arrives late, after the timeout, is still applied.

Installments with a payment processing are listed in a separate "Pagos en proceso" list in the dashboard, with its own total, so the frontend can show that the payment is awaiting confirmation. They are not listed in the payments calendar, nor counted in its total. They are still counted in the total debt, because they stay unpaid until approved.

The company is notified by email, asynchronously, at every step of the payment flow (payment submitted, approved, rejected, processing failed, timed out), when an installment becomes overdue, when a credit request is fully paid, and a few days before an installment's due date.

## Functional Requirements

### Installment status (unchanged)
- The installment status enum keeps exactly its current values: pending, paid and overdue. No status is added.
- A payment rejection, processing failure or processing timeout never changes the installment status: an installment that was pending stays pending, one that was overdue stays overdue.
- The installment only becomes paid when a payment is approved.

### Last payment attempt (new)
- Add a new enum, `InstallmentPaymentAttemptStatus`, with the values:
  - **processing** — a payment was submitted and awaits the webhook service's decision.
  - **approved** — the payment was approved; the installment is paid.
  - **rejected** — the payment was rejected by the service.
  - **processing_failed** — the service reported that it could not process the payment. Handled like rejected; shown to the user so they can retry.
  - **processing_timeout** — no decision arrived within 72 business hours. Used to tell the user in the UI that the payment could not be confirmed and can be retried.
- Add to the installment a last payment attempt made of:
  - the attempt status (the enum above),
  - the date the attempt reached that status (when the payment was submitted for processing; when the result or the timeout was recorded for the others),
  - the error message sent by the service, only when the attempt is processing_failed; null for every other attempt status, and cleared when a new attempt starts or another result arrives.
- All three are null when no payment has ever been attempted on the installment, including every existing installment (no data backfill).
- The error message is stored for support and troubleshooting only; it is not returned by the API, because it comes from the external service as is (not a user-facing Spanish message). The UI shows the generic "No se pudo procesar el pago" label.
- Only the last attempt is kept; a new attempt replaces the previous one. A payment history is out of scope.
- Attempt status transitions:
  - none, rejected, processing_failed or processing_timeout → processing (user pays)
  - processing or processing_timeout → approved (approved result, including a late one)
  - processing or processing_timeout → rejected (rejected result, including a late one)
  - processing or processing_timeout → processing_failed (processing failed result, including a late one)
  - processing → processing_timeout (scheduled task, after 72 business hours)
- The installment data returned by the API (credit request detail, pay response, dashboard "Pagos" section and payments calendar) includes the last payment attempt status and date, null when there is none.
- The database changes (new columns) are included through a reviewed migration.

### Paying an installment (existing pay endpoint)
- The existing pay endpoint is renamed to reflect that it now requests a payment instead of paying: its route becomes `/installment/{id}/pay-request` (was `/installment/{id}/pay`), and the frontend calls the new route. It keeps its HTTP method (PUT), permission and company ownership check (an installment of another company is reported as not found).
- Instead of paying the installment, it:
  - sets the last payment attempt to processing, dated now,
  - queues an asynchronous message to request the payment from the webhook service,
  - responds with HTTP 202 Accepted and the credit request, in which the installment shows the processing attempt.
- Paying an installment whose last payment attempt is processing is refused with a new domain exception and a user-facing message in neutral Spanish (e.g. "El pago de esta cuota está siendo procesado. Espere la confirmación antes de volver a intentarlo.").
- Paying an already paid installment keeps raising the existing "already paid" exception.
- Paying a pending or overdue installment is allowed when there is no previous attempt, or the last attempt was rejected, failed or timed out (retry).
- The credit request is not marked as paid at this step; that only happens when the payment is approved.

### Sending the payment request (async)
- A new message, routed to the existing async transport, carries the installment identifier.
- Its handler loads the installment and sends the payment request to the external payment webhook service through a client behind a domain interface (same split as the credit request application webhook client: interface in Domain, HTTP implementation in Infrastructure).
- The request includes at least the installment identifier, the amount to pay (the installment's total amount, which already includes any late-payment penalty) and the company's CUIT.
- The installment identifier is the payment's idempotency key: every attempt for the same installment is sent with the same key, so if an earlier attempt was approved late and the user pays again, the service does not charge the installment twice.
- The service URL is configured through an environment variable, like the existing credit request application webhook URL.
- If the installment no longer exists or its last attempt is no longer processing when the message is consumed, nothing is sent.

### Receiving the payment result (new endpoint)
- Add a new PUT endpoint in the CreditRequest module for the webhook service to report the payment result, registered like the existing credit request application update endpoint (no user authentication, same exposure as that endpoint).
- The payload contains the installment identifier and the decision, one of `APPROVED`, `REJECTED` or `PROCESSING_FAILED` (same vocabulary as the credit request application webhook, plus the processing failure). With `PROCESSING_FAILED`, the payload also carries the service's error message, which is required for that decision and ignored for the others.
- The payload is validated eagerly so a malformed request gets a 4xx response instead of being queued; a valid request is answered with HTTP 202 Accepted and queued for asynchronous handling in a new message, routed to the async transport.
- The result handler:
  - on approved: sets the attempt to approved, pays the installment and runs the existing credit request paid check, in one transaction (same outcome as today's synchronous payment);
  - on rejected: sets the attempt to rejected; the installment status is left unchanged;
  - on processing failed: sets the attempt to processing_failed and stores the error message; the installment status is left unchanged.
- A result is applied when the installment's last attempt is processing or processing_timeout, so a late result after the timeout still takes effect (a late approval still pays the installment).
- A result for an installment that is already paid, or has no attempt, is ignored without changing data (e.g. a duplicated callback).
- A result for an unknown installment fails like the credit request application result does for an unknown application (the message ends in the failed transport).

### Processing timeout (scheduled task)
- Add a recurring task to the CreditRequest module's schedule, following the existing scheduled tasks (recurring message + message handler + use case). It runs every hour so attempts do not stay processing much longer than the limit.
- It sets to processing_timeout, dated now, every last payment attempt still processing whose processing date is more than 72 business hours ago.
- Business hours are counted on business days only (Monday to Friday, all 24 hours); hours on Saturdays and Sundays do not count. Public holidays are not considered. For example, an attempt submitted on Friday at 10:00 times out after Wednesday at 10:00.
- The installment status is not changed; the user can pay again.

### Overdue handling
- The overdue marker skips installments whose last payment attempt is processing: the payment was submitted, so no late-payment penalty is applied while waiting for the decision.
- A pending installment past its due date is marked overdue, with its penalty, when it has no attempt or its last attempt is rejected, processing_failed or processing_timeout. It keeps its last attempt, so the UI can show it as overdue with a rejected (or failed, or timed out) payment.

### Dashboard "Pagos" section
- **Pagos del mes** lists pending installments due in the current month (as today), except those whose last attempt is processing.
- **Pagos vencidos** lists overdue installments (as today), except those whose last attempt is processing.
- **Pagos en proceso** (new list, `processingPayments` in the response, same shape as the other two) lists every installment of an active credit request whose last payment attempt is processing, whatever its due date or status.
- Each installment still appears in at most one list; paid installments are never listed.
- Each list's total amount is the sum of all its listed payments; processing payments are only counted in the "Pagos en proceso" total.
- Each payment includes its last payment attempt (status and date), so the frontend can show it as "Pago en proceso de confirmación", "Pago rechazado", "No se pudo procesar el pago" (processing failed) or "No se pudo confirmar el pago" (processing timeout).

### Payments calendar
- The calendar keeps listing pending and overdue installments with the same due-date rules as today, now including their last payment attempt.
- Installments whose last payment attempt is processing are not listed, whatever their status or due date, and are not counted in the calendar's total amount. They return to the calendar if the payment is rejected, fails or times out.
- Each installment includes its last payment attempt, so the frontend can flag it the same way as in the dashboard.

### Total debt
- The total debt is unchanged: installments whose last payment attempt is processing are still counted, because they stay unpaid until a payment is approved.

### Email notifications
- Emails are sent to the company's email address (the one already used for the credit request proposal expiration email). Users have no email address of their own today, so the "users with the installment pay permission" recipients are left for a future iteration (see Open questions).
- Sender: the existing no-reply address used by the proposal expiration email.
- Emails are sent asynchronously through the existing async transport: the action that triggers an email never waits for it, and an email that fails to send is retried and ends in the failed transport like other async messages, without undoing or blocking the payment flow.
- Add a common, reusable email sending use case that receives the sender, the recipient, the subject and the plain text body, so every notification (and future ones in any module) is sent the same way.
- Subjects and bodies are in neutral Spanish (formal "usted", no voseo), plain text.
- Every installment email identifies the installment: credit request reference, installment number over the credit's installment count (e.g. "3/12"), amount and due date.
- Events that send an email (one email per event and installment):

  | Event | When | Subject (indicative) | Body highlights |
  |---|---|---|---|
  | Payment submitted | The user clicks "Pagar" and the attempt becomes processing | Pago en proceso | The payment is being processed; they will be notified of the result. |
  | Payment approved | An approved result is applied (including a late one after a timeout) | Pago aprobado | The installment is paid. |
  | Payment rejected | A rejected result is applied | Pago rechazado | The payment was rejected; they can try again. |
  | Payment processing failed | A processing failed result is applied | No se pudo procesar el pago | The payment could not be processed; they can try again. The service's error message is not included. |
  | Payment timed out | The scheduled task marks the attempt processing_timeout | No se pudo confirmar el pago | No confirmation was received in 72 business hours; they can try again, and will not be charged twice. |
  | Installment overdue | The overdue marker marks the installment overdue | Cuota vencida | The installment is overdue and accrues late-payment interest. |
  | Credit request paid | An approved payment settles the last unpaid installment | Crédito pagado en su totalidad | The credit request is fully paid. Sent in addition to the "Pago aprobado" email. |
  | Due date reminder | 3 calendar days before the due date | Recordatorio de vencimiento | The installment is due on its due date. |

- Results that are ignored (duplicated callback, installment already paid) send no email.
- A payment result email is queued only after the result has been saved (its database transaction committed). If saving fails, no email is queued, and the result is retried like any failed async message.
- Due date reminder:
  - A new daily scheduled task sends the reminder for pending installments of active credit requests due exactly 3 calendar days from the day it runs.
  - Installments with a processing payment are skipped (the payment was already submitted).
  - Each installment gets one reminder, because the task runs once a day and only matches one due day.
- The overdue marking email is new for the existing overdue marker; it is sent for every installment it marks overdue.

### General
- New error messages are user-facing, in neutral Spanish (formal "usted", no voseo), per project conventions.
- No new permission is added; paying keeps the existing installment pay permission.

## Possible Edge Cases
- User double-clicks "Pagar": the second request finds the attempt processing and is refused with the new exception; only one payment request is queued.
- Webhook service calls back twice for the same installment, or calls back after the installment was paid: the second result is ignored.
- Webhook service approves after the attempt timed out: the installment is paid and the attempt becomes approved.
- Attempt timed out, user retries, and the original attempt was in fact approved: the same idempotency key (installment identifier) prevents a second charge; the approved result pays the installment once.
- Webhook callback with an invalid decision value or a missing field (including a `PROCESSING_FAILED` decision without error message): rejected with 4xx before queuing. Unknown installment identifier: fails in the async handler.
- Payment of the last unpaid installment is approved: the credit request becomes paid at that moment, not when the user clicked "Pagar".
- Payment of the last unpaid installment is rejected: the credit request stays active.
- Overdue installment paid and rejected: it stays overdue, with its last attempt rejected; both are shown in the UI.
- Service reports processing failed: the attempt becomes processing_failed with its error message, the installment keeps its status, and the user can retry with the same idempotency key. The retry clears the stored error message.
- Pending installment whose due date passes while the payment is processing: it is not marked overdue; if the payment is later rejected, fails or times out, the next overdue run marks it overdue.
- Attempt submitted on a Friday or during a weekend: weekend hours are not counted, so it does not time out before the middle of the next week.
- The webhook service is unreachable when the request message is consumed: the message is retried and ends in the failed transport like other async messages; the attempt stays processing until the scheduled task marks it processing_timeout.
- Existing installments (no attempt yet): attempt status and date are null, and they behave as today until paid.
- Approved payment of the last unpaid installment: two emails are sent, "Pago aprobado" and "Crédito pagado en su totalidad".
- Late approval after a timeout: the company first got "No se pudo confirmar el pago" and then gets "Pago aprobado".
- The mail server is down: the email messages are retried and end in the failed transport; payments, results and scheduled tasks are not affected.
- Installment paid before its reminder day: no reminder is sent (only pending installments are reminded).
- Installment with a rejected, failed or timed out attempt on its reminder day: the reminder is sent (it is still unpaid).

## Acceptance Criteria
- The installment status enum still has only pending, paid and overdue.
- A new `InstallmentPaymentAttemptStatus` enum exists with processing, approved, rejected, processing_failed and processing_timeout, and installments have a nullable last payment attempt status and date.
- Clicking "Pagar" on a pending or overdue installment with no attempt, or with a rejected, failed or timed out attempt, returns 202, the attempt is processing with the current date, the installment status is unchanged, and a payment request message is queued.
- Clicking "Pagar" on an installment with a processing attempt returns an error with the new Spanish message; on a paid installment returns the existing "already paid" error.
- The payment request message sends the installment id (as idempotency key), amount and CUIT to the configured webhook URL.
- PUTting an approved result for a processing or timed out attempt marks the attempt approved and the installment paid and, if it was the last unpaid installment, marks the credit request paid.
- PUTting a rejected result for a processing or timed out attempt marks the attempt rejected and leaves the installment status unchanged; the installment can be paid again.
- PUTting a processing failed result for a processing or timed out attempt marks the attempt processing_failed, stores the service's error message and leaves the installment status unchanged; the installment can be paid again.
- Results for paid installments or installments without an attempt change nothing.
- Malformed result payloads get a 4xx response and are not queued.
- The scheduled task marks processing attempts older than 72 business hours as processing_timeout and leaves newer ones untouched.
- The overdue marker skips installments with a processing attempt and marks past-due pending installments with no attempt, a rejected, a failed or a timed out attempt as overdue.
- The dashboard lists installments with a processing attempt only in `processingPayments`, and counts them only in its total amount; "Pagos del mes" and "Pagos vencidos" exclude them from their lists and totals.
- The payments calendar excludes installments with a processing attempt from its list and total amount; the total debt still counts them.
- Every installment listed in the dashboard and the payments calendar includes its last payment attempt.
- Each event in the email notifications table queues one email in Spanish to the company's email address, from the no-reply address, identifying the installment (or the credit request for the fully paid email); ignored results queue none.
- Emails are queued on the async transport, not sent during the request or task that triggers them.
- Payment result emails are queued only once the result's transaction has committed; a failed commit queues no email.
- The daily reminder task emails pending installments due in 3 calendar days, skipping those with a processing payment.

## Open questions
- **Per-user recipients (future):** notifications should eventually go to every company user with the installment pay permission. That needs an email address on users (members today only have a display name and tax identifier), which is out of scope here.

## Testing Guidelines
Create a test file(s) in the ./tests folder for new feature, and create meaningful test for the following cases, without going too heavy:
- Unit: installment payment attempt transitions — paying with no attempt, a rejected, a failed or a timed out attempt sets it to processing without changing the installment status; paying with a processing attempt raises the new exception; paying a paid installment raises the existing exception; approving pays the installment; rejecting or failing leaves the status unchanged; results apply to processing and timed out attempts only.
- Unit: 72 business hours calculation — attempts within the limit are kept, older ones time out, weekends are not counted (e.g. Friday submission).
- Unit: overdue marker skips installments with a processing attempt and marks past-due ones with a rejected, failed or timed out attempt overdue.
- Unit: payment result handler — approved pays the installment and marks the credit request paid when it was the last one; a late approval after timeout still pays; rejected and processing failed only update the attempt.
- Functional: pay request endpoint returns 202 with the processing attempt and queues the payment request message; a second pay attempt returns the Spanish error.
- Functional: payment result endpoint returns 202 for a valid payload and queues the result message; returns 4xx for malformed payloads.
- Functional/Integration: dashboard "Pagos" endpoint lists installments with a processing attempt only in `processingPayments` and its total; payments calendar excludes them from its list and total; total debt still counts them; listed installments include their last payment attempt.
- Unit: email sending use case builds the email with the given sender, recipient, subject and text.
- Unit: each payment flow use case (pay request, result handler, timeout task, overdue marker) notifies the right event, and an ignored result notifies nothing; payment result emails are not queued when the transaction fails; an approved last installment also notifies the credit request as paid.
- Unit: due date reminder task selects pending installments due in 3 days and skips processing ones.
- Functional: paying an installment queues the "Pago en proceso" email to the company's address on the async transport.
