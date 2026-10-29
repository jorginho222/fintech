# Spec for dashboard-stats

branch: claude/feature/dashboard-stats

## Summary
Add backend endpoints that serve the figures shown on the application dashboard, with one endpoint per dashboard section so each section can be guarded by the permission of the resource it summarizes. Further sections are added as their own endpoints in the module they belong to. This spec covers three sections, all for the authenticated company:

- **Credit request applications** (implemented): the total number of credit request applications together with a breakdown by status (approved, rejected and pending evaluation), counted over all time.
- **Créditos en curso** (credit requests in progress): figures about the company's active credit requests — how many there are ("Total creditos") and what percentage of their installments has been paid ("Porcentaje pagado"). Only credit requests in the active status count, the same ones listed on the "Créditos en curso" screen, so the dashboard and that list are always consistent.
- **Pagos** (payments): two lists of installments to pay, in the same format as the payments calendar. "Pagos del mes" lists the installments still pending that are due in the current month; "Pagos vencidos" lists the overdue installments, whatever month they were due in. Each list shows the total amount of its payments, and each payment shows its amount, its due date, its position in the credit ("1/12") and the credit reference, with a link to pay it.

## Functional Requirements

### Common to every section
- Each section has its own endpoint, in the module it summarizes, returning only that section's figures.
- The company is resolved from the authenticated context, like the existing endpoints of each module; only the authenticated company's data is counted.
- Figures are computed by the backend (aggregated at the data layer), not by returning full lists for the frontend to count.
- Any error message returned to the client is a user-facing message in neutral Spanish, per project conventions.

### Credit request applications section
- Add a credit request applications dashboard stats endpoint in the CreditRequest module.
- The endpoint returns four counts:
  - total number of credit request applications
  - number of applications with status approved
  - number of applications with status rejected
  - number of applications with status evaluation pending
- The counts map directly onto the existing credit request application statuses (evaluation pending, approved, rejected).
- Counts cover all time: every application of the company is counted regardless of when it was created. The endpoint accepts no date range or other filters.
- Every count is always present in the response, with zero when the company has no applications in that status.
- The total equals the sum of the three status counts.
- Access is guarded by the existing credit request application list permission. No new permission is added.

### Créditos en curso section
- Add a credit requests in progress dashboard stats endpoint in the CreditRequest module.
- Only credit requests in the active status are considered. Proposals, expired proposals and fully paid credit requests are excluded.
- The endpoint returns two figures:
  - **Total creditos**: the number of active credit requests.
  - **Porcentaje pagado**: the percentage of paid installments over the total number of installments, pooling the installments of all active credit requests together (paid installments of all active credits ÷ all installments of those credits × 100). A credit with more installments therefore weighs more than one with fewer.
- Only installments in the paid status count as paid; pending and overdue installments count towards the total but not as paid.
- The percentage is rounded to two decimals (e.g. 37.5, 33.33).
- When the company has no active credit requests, the total is zero and the percentage is null (not zero), so the frontend can show "-" instead of a misleading 0%.
- Both figures cover all active credit requests, regardless of when they were created. The endpoint accepts no date range or other filters.
- Access is guarded by the existing credit request list permission (the same one that guards the "Créditos en curso" list). No new permission is added.
- The credit request search used by the "Créditos en curso" screen must only return active credit requests, so the list matches the stats. The existing search already filters by the active status; this requirement keeps it that way and no change is expected.

### Pagos section
- Add a payments dashboard endpoint in the CreditRequest module that returns both lists of the section in one response:
  - **Pagos del mes**: installments in the pending status whose due date falls in the current calendar month (from the first to the last day of the month, inclusive).
  - **Pagos vencidos**: installments in the overdue status, whatever their due date.
- Each installment appears in at most one list, decided by its status: an installment due earlier this month that is now overdue is listed only under "Pagos vencidos"; paid installments are never listed.
- Only installments of active credit requests are listed, consistent with the "Créditos en curso" section and the payments calendar.
- Each payment in either list includes:
  - the amount to pay (the installment's total amount, which already includes any late-payment penalty)
  - the due date
  - the installment number and the credit's installment count, so the frontend can show "1/12"
  - the credit reference
  - the identifiers the frontend needs to link to the pay screen (the installment and its credit request)
- The payment format matches the one already used by the payments calendar; it only adds the credit's installment count.
- Both lists are ordered by due date, oldest first.
- Each list includes the total amount of its payments (the sum of their amounts), in the same format as the payments calendar's monthly total.
- An empty list (with a total of zero) is returned, not an error, when there are no payments of that kind.
- Access is guarded by the existing payment calendar view permission. No new permission is added.
- Frontend: the link to pay each payment is disabled when the user lacks the permissions the pay screen requires (installment pay and credit request view, as on the payments calendar). The backend already rejects payments from users without the installment pay permission.

## Possible Edge Cases

### Credit request applications section
- Company has no credit request applications: all four counts are zero, not an error.
- Company has applications in only one status: the other status counts are zero.
- Another company has applications: they are never included in the authenticated company's counts.
- A member user without the credit request application list permission calls the endpoint: access is denied, while other dashboard sections they're allowed to see remain unaffected since each section has its own endpoint.
- An unauthenticated request is rejected.
- A new application status is added in the future: the total should still reflect every application, and the breakdown would need to be extended explicitly (tests should catch any mismatch between total and breakdown).

### Créditos en curso section
- Company has no active credit requests (none at all, or only proposals, expired proposals or paid ones): total is zero and percentage is null.
- Active credit requests with no paid installments yet: percentage is 0.
- Every installment of the active credit requests is paid (e.g. the last payment was just made and the credit hasn't been marked as paid yet): percentage is 100.
- Installments of non-active credit requests (e.g. a fully paid credit request) never count, neither as paid nor towards the total.
- Overdue installments count towards the total but not as paid.
- A percentage that isn't exact (e.g. 1 paid out of 3) is rounded to two decimals (33.33).
- Another company's credit requests and installments are never included.
- A member user without the credit request list permission calls the endpoint: access is denied, without affecting other dashboard sections.
- An unauthenticated request is rejected.

### Pagos section
- Company has no installments to pay: both lists are empty and both totals are zero.
- An installment due earlier this month that is now overdue appears only in "Pagos vencidos".
- An installment overdue from a previous month appears in "Pagos vencidos".
- A pending installment due next month (or later) appears in neither list.
- A pending installment due this month whose due date has passed but that hasn't been marked overdue yet is still pending, so it stays in "Pagos del mes" until it's marked overdue.
- Paid installments never appear.
- Installments of credit requests that aren't active never appear.
- Another company's installments never appear.
- A member user without the payment calendar view permission calls the endpoint: access is denied, without affecting other dashboard sections.
- A member user with the payment calendar view permission but without the installment pay permission sees both lists, with the pay links disabled.
- An unauthenticated request is rejected.

## Acceptance Criteria

### Credit request applications section
- An endpoint exists that returns, for the authenticated company, the total count of credit request applications and the approved, rejected and evaluation-pending counts, covering all time.
- The endpoint returns only the credit request applications section.
- All four counts are returned, including zeros.
- The total equals the sum of the approved, rejected and evaluation-pending counts.
- Applications from other companies are excluded.
- Users without the credit request application list permission are denied access; unauthenticated requests are rejected.

### Créditos en curso section
- An endpoint exists that returns, for the authenticated company, the number of active credit requests and the percentage of their installments that are paid.
- Only active credit requests and their installments are considered.
- The percentage pools the installments of all active credit requests and is rounded to two decimals.
- With no active credit requests, the total is zero and the percentage is null.
- Credit requests from other companies are excluded.
- Users without the credit request list permission are denied access; unauthenticated requests are rejected.
- The "Créditos en curso" search still returns only active credit requests.

### Pagos section
- An endpoint exists that returns, for the authenticated company, the pending installments due in the current month and the overdue installments, as two lists.
- No installment appears in both lists.
- Each payment includes its amount, due date, installment number, the credit's installment count, the credit reference and the identifiers needed to link to the pay screen.
- Both lists are ordered by due date, oldest first, and each includes the total amount of its payments.
- Only installments of the company's active credit requests are listed.
- Users without the payment calendar view permission are denied access; unauthenticated requests are rejected.
- In the frontend, the pay link is disabled for users without the permissions required by the pay screen.

## Open questions
- Which permission should guard the credit request applications endpoint: reuse the existing credit request application list permission, or add a new dedicated dashboard permission? Reuse the existing credit request application list permission.
- Should the counts cover all time, or should the endpoint accept an optional date range? For now, cover all time.
- Should the dashboard be served by a single aggregated endpoint covering all sections, or one endpoint per section? One endpoint per section, so each section is guarded by the permission of the resource it summarizes.
- How should "Porcentaje pagado" be calculated across the active credits: pooling all installments, or averaging each credit's percentage? Pool all installments.
- What should "Porcentaje pagado" be when the company has no active credits? Null, so the frontend shows "-" next to "Total creditos: 0".
- How precise should the percentage be? Two decimals.

- Should an installment due earlier this month that is now overdue appear in "Pagos del mes", "Pagos vencidos" or both? Only in "Pagos vencidos"; "Pagos del mes" lists only pending installments.
- Should the payments section reuse the payments calendar endpoint, or get its own endpoint? Its own endpoint, guarded by the payment calendar view permission.
- Should each payments list show a total amount? Yes, each list includes the total of its payments; each payment also shows its due date.

## Testing Guidelines
Create a test file(s) in the ./tests folder for new feature, and create meaningful test for the following cases, without going too heavy:

### Credit request applications section
- Given applications in each status for the authenticated company, the endpoint returns the correct total and per-status counts.
- Given no applications, all counts are zero.
- Given applications belonging to another company, they are excluded from the counts.
- Given a member user without the credit request application list permission, the endpoint denies access.
- Repository-level test that the aggregated counts per status are computed correctly against the database.
- Guard test that goes through every credit request application status and fails if one is added without extending the breakdown.

### Créditos en curso section
- Given active credit requests with a mix of paid, pending and overdue installments, the endpoint returns the correct total and pooled percentage.
- Given credit requests in other statuses (proposal, expired proposal, paid), they and their installments are excluded.
- Given no active credit requests, the total is zero and the percentage is null.
- Given a non-exact percentage (e.g. 1 of 3 installments paid), it is rounded to two decimals.
- Given credit requests belonging to another company, they are excluded.
- Given a member user without the credit request list permission, the endpoint denies access.
- Repository-level test that the active credit count and installment counts are computed correctly against the database.

### Pagos section
- Given pending and overdue installments in and out of the current month, "Pagos del mes" contains only the pending ones due this month and "Pagos vencidos" contains every overdue one, each ordered by due date.
- Given an installment due earlier this month that is now overdue, it appears only in "Pagos vencidos".
- Given paid installments and installments of credit requests that aren't active, they appear in neither list.
- Given no installments to pay, both lists are empty and both totals are zero.
- Each list's total is the sum of its payments' amounts.
- Given installments belonging to another company, they are excluded.
- Each payment includes the amount, due date, installment number, the credit's installment count, the credit reference and the credit request identifier.
- Given a member user without the payment calendar view permission, the endpoint denies access.
- Repository-level test that the installment search filters by status, due date range, active credit requests and company, ordered by due date.
