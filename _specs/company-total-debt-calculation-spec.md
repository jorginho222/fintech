# Spec for company-total-debt-calculation

branch: claude/feature/company-total-debt-calculation

## Summary
Add a backend capability that calculates the authenticated company's total outstanding debt from today through the end of a caller-specified limit month/year, and expose it via an endpoint the existing "Total Debt" frontend view can call. The frontend already sends only a limit month and year; the backend is responsible for resolving the date range and performing the full calculation (summing owed installment amounts, including overdue ones) and returning a single total.

## Functional Requirements
- Add an endpoint that accepts a limit month and limit year as input, scoped to the authenticated company (consistent with how other installment-related endpoints resolve the company from the authenticated context).
- Given the limit month/year, the backend resolves the calculation range as: from today's date (inclusive) through the last day of the limit month (inclusive).
- The backend calculates the total debt as the sum of amounts owed for installments whose due date falls within the resolved range, including installments that are already overdue as of today, and pending installments not yet due but falling within the range.
- Paid installments are excluded from the total.
- The total must only include installments belonging to credit requests owned by the authenticated company.
- The response returns a single aggregate total amount (no per-month or per-credit-request breakdown), along with enough context (e.g. the resolved range or the limit month/year echoed back) for the caller to know what the figure represents.
- The calculation must correctly handle a limit month/year arbitrarily far in the future without failing or producing an incorrect/truncated result.
- The calculation must correctly handle the case where the limit month/year is the current month, counting only from today through the end of that month.
- Input validation should reject a limit month/year that is invalid (e.g. month outside 1-12, missing values), following the validation pattern already used by similar installment search endpoints. No upper bound restriction is required on how far in the future the limit month/year can be.
- Input validation should also reject a limit month/year that is earlier than the current month/year (e.g. if today is September 2026, August 2026 is rejected but September 2026 or later is accepted), since the range is always computed forward from today. This must be a proper validation error, not a silently empty/zero result.

## Possible Edge Cases
- Limit month/year equals the current month: only installments due from today through month-end should count, not the whole month.
- No installments (pending or overdue) fall within the resolved range: total should resolve to zero, not an error.
- Company has no credit requests/installments at all: total resolves to zero.
- Limit month/year far in the future (e.g. several years out): calculation still resolves correctly.
- Installments overdue as of today (due date before today) are still included, since "today" is only the lower bound of the range and does not exclude existing overdue amounts.
- Limit month/year is in the past relative to today (e.g. today is September 2026 and the request specifies August 2026): even though the frontend already prevents selecting such a month, the backend must independently reject the request with a validation error rather than returning an empty/zero or otherwise misleading total.
- A company attempting to query another company's debt should not be able to access that data; the calculation must always be scoped to the authenticated company.

## Acceptance Criteria
- An endpoint exists that, given a limit month and year, returns the authenticated company's total outstanding debt from today through the end of that month.
- The returned total includes overdue and pending installments due within the range and excludes paid installments.
- The total is zero (not an error) when no qualifying installments exist.
- The calculation is correctly scoped to the authenticated company only.
- The calculation resolves correctly for the current month as the limit, and for limit months/years arbitrarily far in the future.
- Invalid month/year input is rejected with a clear validation error.
- A limit month/year earlier than the current month/year is rejected with a clear validation error, not treated as an empty/zero result.

## Open questions
- Should the endpoint reuse/extend the existing pending-to-pay installment search capability (which currently scopes to a single calendar month), or should it be a distinct calculation path built for a "today through month-end" range? It should be a distinct calculation path
- Does "total debt" need to account for partial payments or penalty/interest adjustments on an installment's owed amount, or is it strictly the sum of each qualifying installment's current total amount as stored? For now, compute the installment total amount (in all cases)
- Should the response echo back the resolved date range (today's date and month-end) in addition to the total, to make the figure's meaning explicit to the frontend? Only the total

## Testing Guidelines
Create a test file(s) in the ./tests folder for new feature, and create meaningful test for the following cases, without going too heavy:
- Given pending and overdue installments due within today-to-month-end range, the total sums their amounts correctly and excludes paid installments and installments outside the range.
- Given a limit month/year equal to the current month, only installments from today onward (not earlier in the month) are included.
- Given no qualifying installments, the total resolves to zero rather than an error.
- Given installments belonging to a different company, those amounts are excluded from the authenticated company's total.
- Given invalid month/year input, the endpoint returns a validation error.
- Given a limit month/year earlier than the current month/year, the endpoint returns a validation error rather than a zero total.
