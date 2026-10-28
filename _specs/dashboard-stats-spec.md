# Spec for dashboard-stats

branch: claude/feature/dashboard-stats

## Summary
Add backend endpoints that serve the figures shown on the application dashboard, with one endpoint per dashboard section so each section can be guarded by the permission of the resource it summarizes. Further sections are added as their own endpoints in the module they belong to. This spec covers two sections, both for the authenticated company:

- **Credit request applications** (implemented): the total number of credit request applications together with a breakdown by status (approved, rejected and pending evaluation), counted over all time.
- **Créditos en curso** (credit requests in progress): figures about the company's active credit requests — how many there are ("Total creditos") and what percentage of their installments has been paid ("Porcentaje pagado"). Only credit requests in the active status count, the same ones listed on the "Créditos en curso" screen, so the dashboard and that list are always consistent.

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

## Open questions
- Which permission should guard the credit request applications endpoint: reuse the existing credit request application list permission, or add a new dedicated dashboard permission? Reuse the existing credit request application list permission.
- Should the counts cover all time, or should the endpoint accept an optional date range? For now, cover all time.
- Should the dashboard be served by a single aggregated endpoint covering all sections, or one endpoint per section? One endpoint per section, so each section is guarded by the permission of the resource it summarizes.
- How should "Porcentaje pagado" be calculated across the active credits: pooling all installments, or averaging each credit's percentage? Pool all installments.
- What should "Porcentaje pagado" be when the company has no active credits? Null, so the frontend shows "-" next to "Total creditos: 0".
- How precise should the percentage be? Two decimals.

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
