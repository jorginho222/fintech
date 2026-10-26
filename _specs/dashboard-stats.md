# Spec for dashboard-stats

branch: claude/feature/dashboard-stats

## Summary
Add backend endpoints that serve the figures shown on the application dashboard, with one endpoint per dashboard section so each section can be guarded by the permission of the resource it summarizes. This first iteration delivers only the "Credit request applications" section: for the authenticated company, its endpoint returns the total number of credit request applications together with a breakdown by status (approved, rejected and pending evaluation), counted over all time. Further dashboard sections will be added later as their own endpoints in the module they belong to.

## Functional Requirements
- Add a credit request applications dashboard stats endpoint in the CreditRequest module that returns the section's figures for the authenticated company, resolving the company from the authenticated context like the existing credit request application endpoints.
- The endpoint covers only the credit request applications section; it does not return figures for other dashboard sections.
- The endpoint returns four counts:
  - total number of credit request applications
  - number of applications with status approved
  - number of applications with status rejected
  - number of applications with status evaluation pending
- The counts map directly onto the existing credit request application statuses (evaluation pending, approved, rejected).
- Counts cover all time: every application of the company is counted regardless of when it was created. The endpoint accepts no date range or other filters.
- Only applications belonging to the authenticated company are counted.
- Every count is always present in the response, with zero when the company has no applications in that status.
- The total equals the sum of the three status counts.
- The counts are computed by the backend (aggregated at the data layer), not by returning the full application list for the frontend to count.
- Access to the endpoint is guarded by the existing credit request application list permission. No new permission is added.
- Any error message returned to the client is a user-facing message in neutral Spanish, per project conventions.

## Possible Edge Cases
- Company has no credit request applications: all four counts are zero, not an error.
- Company has applications in only one status: the other status counts are zero.
- Another company has applications: they are never included in the authenticated company's counts.
- A member user without the credit request application list permission calls the endpoint: access is denied, while other dashboard sections they're allowed to see remain unaffected since each section has its own endpoint.
- An unauthenticated request is rejected.
- A new application status is added in the future: the total should still reflect every application, and the breakdown would need to be extended explicitly (tests should catch any mismatch between total and breakdown).

## Acceptance Criteria
- An endpoint exists that returns, for the authenticated company, the total count of credit request applications and the approved, rejected and evaluation-pending counts, covering all time.
- The endpoint returns only the credit request applications section.
- All four counts are returned, including zeros.
- The total equals the sum of the approved, rejected and evaluation-pending counts.
- Applications from other companies are excluded.
- Users without the credit request application list permission are denied access; unauthenticated requests are rejected.

## Open questions
- Which permission should guard the endpoint: reuse the existing credit request application list permission, or add a new dedicated dashboard permission? Reuse the existing credit request application list permission.
- Should the counts cover all time, or should the endpoint accept an optional date range? For now, cover all time.
- Should the dashboard be served by a single aggregated endpoint covering all sections, or one endpoint per section? One endpoint per section, so each section is guarded by the permission of the resource it summarizes.

## Testing Guidelines
Create a test file(s) in the ./tests folder for new feature, and create meaningful test for the following cases, without going too heavy:
- Given applications in each status for the authenticated company, the endpoint returns the correct total and per-status counts.
- Given no applications, all counts are zero.
- Given applications belonging to another company, they are excluded from the counts.
- Given a member user without the credit request application list permission, the endpoint denies access.
- Repository-level test that the aggregated counts per status are computed correctly against the database.
