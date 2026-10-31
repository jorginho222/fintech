# Spec for installment-payment-webhook

branch: claude/feature/installment-payment-webhook

## Summary
Today, clicking "Pagar" on an installment pays it right away: the installment is marked as paid and, when it was the last unpaid one, its credit request is marked as paid. This feature replaces that synchronous payment with an asynchronous flow through an external payment webhook service, following the same pattern already used for credit request applications (request queued → external service called → service calls back an app endpoint → result handled asynchronously).

New flow:

1. The user clicks "Pagar". The installment moves to a new **processing** status ("Pago en proceso de confirmación") and a message is queued. The response returns immediately.
2. The queued message is consumed asynchronously and sends the payment request to the external payment webhook service.
3. The service randomly decides whether the payment is approved or rejected, and calls a new app endpoint (POST) with the decision.
4. The endpoint validates the payload and queues the result for asynchronous handling.
5. When handled:
   - **Approved**: the installment is marked as paid, and the credit request is marked as paid if all its installments are paid (same behavior as today's payment).
   - **Rejected**: the installment moves to a new **rejected** status ("Pago rechazado"). The user can try to pay it again.

Installments in processing are still shown in the dashboard "Pagos" section and in the payments calendar, flagged so the frontend can show that the payment is awaiting confirmation. Rejected installments are also shown, flagged as rejected.

## Functional Requirements

### Installment statuses
- Add two installment statuses:
  - **processing** — a payment was submitted and is awaiting the webhook service's decision. The name `pending` is already used for "not paid yet"; `processing` is chosen because it is the usual name for an in-flight payment and is clearly distinct from `pending`.
  - **rejected** — the last payment attempt was rejected by the service; the installment is still unpaid and can be paid again.
- Existing statuses (pending, paid, overdue) keep their meaning.
- Status transitions:
  - pending, overdue or rejected → processing (user pays)
  - processing → paid (approved)
  - processing → rejected (rejected)
  - rejected → overdue (overdue marker, when its due date has passed — see "Overdue handling")
- Any database change needed to store the new status values is included through a reviewed migration.

### Paying an installment (existing pay endpoint)
- The existing pay endpoint keeps its route, permission and company ownership check (an installment of another company is reported as not found).
- Instead of paying the installment, it:
  - moves the installment to processing,
  - queues an asynchronous message to request the payment from the webhook service,
  - responds with HTTP 202 Accepted and the credit request, in which the installment now shows the processing status.
- Paying an installment in processing is refused with a new domain exception and a user-facing message in neutral Spanish (e.g. "El pago de esta cuota está siendo procesado. Espere la confirmación antes de volver a intentarlo.").
- Paying an already paid installment keeps raising the existing "already paid" exception.
- Paying a pending, overdue or rejected installment is allowed (rejected = retry).
- The credit request is not marked as paid at this step; that only happens when the payment is approved.

### Sending the payment request (async)
- A new message, routed to the existing async transport, carries the installment identifier.
- Its handler loads the installment and sends the payment request to the external payment webhook service through a client behind a domain interface (same split as the credit request application webhook client: interface in Domain, HTTP implementation in Infrastructure).
- The request includes at least the installment identifier, the amount to pay (the installment's total amount, which already includes any late-payment penalty) and the company's CUIT.
- The service URL is configured through an environment variable, like the existing credit request application webhook URL.
- If the installment no longer exists or is no longer in processing when the message is consumed, nothing is sent.

### Receiving the payment result (new endpoint)
- Add a new POST endpoint in the CreditRequest module for the webhook service to report the payment result, registered like the existing credit request application update endpoint (no user authentication, same exposure as that endpoint).
- The payload contains the installment identifier and the decision (approved or rejected), using the same decision vocabulary as the credit request application webhook (`APPROVED` / `REJECTED`).
- The payload is validated eagerly so a malformed request gets a 4xx response instead of being queued; a valid request is answered with HTTP 202 Accepted and queued for asynchronous handling in a new message, routed to the async transport.
- The result handler:
  - on approved: pays the installment and runs the existing credit request paid check, in one transaction — same outcome as today's synchronous payment;
  - on rejected: moves the installment to rejected.
- The result is only applied to an installment in processing. A result for an installment in any other status (e.g. a duplicated or late callback) is ignored without changing data.
- A result for an unknown installment fails like the credit request application result does for an unknown application (the message ends in the failed transport).

### Overdue handling
- An installment in processing is not marked overdue by the overdue marker while its payment awaits confirmation.
- A rejected installment whose due date has passed is marked overdue by the overdue marker (with its late-payment penalty computed as for pending installments), so penalties are never skipped because of a rejected payment. As a consequence, in this iteration an overdue installment that is rejected shows as "rejected" until the next overdue run, and then shows as "overdue" (see Open questions).

### Dashboard "Pagos" section
- **Pagos del mes** lists installments due in the current month in the pending, processing or rejected status.
- **Pagos vencidos** keeps listing overdue installments, and also lists processing installments whose due date is before the current month (an overdue installment whose payment is awaiting confirmation stays in the overdue list instead of disappearing).
- Each installment still appears in at most one list; paid installments are never listed.
- Each payment includes its status so the frontend can flag it as "Pago en proceso de confirmación" or "Pago rechazado" and hide or disable the pay link while it is processing.
- Each list's total amount keeps including all listed payments, processing ones included (the money is not confirmed yet).

### Payments calendar
- The calendar keeps listing pending and overdue installments, and also lists processing and rejected installments in the requested period, applying the same due-date rules as today.
- Each installment includes its status, so the frontend can flag processing and rejected payments the same way as in the dashboard.

### General
- New error messages are user-facing, in neutral Spanish (formal "usted", no voseo), per project conventions.
- No new permission is added; paying keeps the existing installment pay permission.

## Possible Edge Cases
- User double-clicks "Pagar": the second request finds the installment in processing and is refused with the new exception; only one payment request is queued.
- Webhook service calls back twice for the same installment, or calls back after the installment was already paid: the second result is ignored.
- Webhook callback with an unknown installment identifier, an invalid decision value or a missing field: rejected with 4xx before queuing (malformed) or failed in the async handler (unknown installment).
- Payment of the last unpaid installment is approved: the credit request becomes paid at that moment, not when the user clicked "Pagar".
- Payment of the last unpaid installment is rejected: the credit request stays active.
- Overdue installment paid and rejected: it is rejected until the next overdue marker run, then overdue again with its penalty.
- Pending installment whose due date passes while the payment is processing: it is not marked overdue; if the payment is later rejected, the next overdue run marks it overdue.
- The webhook service is unreachable when the request message is consumed: the message is retried and ends in the failed transport like other async messages; the installment stays in processing (see Open questions).
- Installment of a credit request that is not active: the existing pay rules apply unchanged.

## Acceptance Criteria
- Clicking "Pagar" on a pending, overdue or rejected installment returns 202, the installment is in processing, and a payment request message is queued.
- Clicking "Pagar" on a processing installment returns an error with the new Spanish message; on a paid installment returns the existing "already paid" error.
- The payment request message sends the installment id, amount and CUIT to the configured webhook URL.
- POSTing an approved result for a processing installment marks it paid and, if it was the last unpaid installment, marks the credit request paid.
- POSTing a rejected result for a processing installment marks it rejected, and it can be paid again.
- Results for installments not in processing change nothing.
- Malformed result payloads get a 4xx response and are not queued.
- The overdue marker skips processing installments and marks past-due rejected installments as overdue.
- The dashboard "Pagos" section and the payments calendar include processing and rejected installments as described, each with its status.

## Open questions
- **Expired + rejected (to solve in a future iteration):** an installment that is both past due and had its payment rejected should show both facts in the UI. Options to evaluate: keep a single status and add a separate attribute recording the last payment attempt result (e.g. last payment rejected, with date), or model the payment attempts as their own records linked to the installment (which would also give a payment history). In this iteration the status alone is used, so the "rejected" mark is lost when the overdue marker moves the installment back to overdue.
- **Stuck processing payments:** if the webhook service never calls back (or the request message ends in the failed transport), the installment stays in processing and cannot be paid again. Should there be a timeout that moves it back to its previous status (or to rejected), or a manual way to reset it?
- Should processing installments be excluded from the "Pagos del mes" / calendar total amounts instead of included?

## Testing Guidelines
Create a test file(s) in the ./tests folder for new feature, and create meaningful test for the following cases, without going too heavy:
- Unit: installment status transitions — paying a pending, overdue or rejected installment moves it to processing; paying a processing installment raises the new exception; paying a paid one raises the existing exception; approving/rejecting only applies to processing installments.
- Unit: overdue marker skips processing installments and marks past-due rejected installments overdue.
- Unit: payment result handler — approved pays the installment and marks the credit request paid when it was the last one; rejected marks the installment rejected; results for non-processing installments change nothing.
- Functional: pay endpoint returns 202 with the installment in processing and queues the payment request message; a second pay attempt returns the Spanish error.
- Functional: payment result endpoint returns 202 for a valid payload and queues the result message; returns 4xx for malformed payloads.
- Functional/Integration: dashboard "Pagos" endpoint and payments calendar include processing and rejected installments with their status, in the right list.
