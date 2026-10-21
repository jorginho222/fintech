# Spec for user-permissions-management

branch: claude/feature/user-permissions-management

## Summary
Today a company logs in as one identity, the company itself, using its CUIT and password, and gets full access to every company endpoint. This feature brings in two kinds of company users:

- **Admin user**: the company's own account. It logs in with the company **CUIT** and password, always has every permission, and is the only user who can manage other users and their permissions.
- **Normal user**: a person the admin creates. It logs in with their own **CUIL** (11 digits, same format as a CUIT) and password. It works inside the same company dashboard and sees the same company data, but can only reach the resources it has been granted.

Normal users are never deleted. The admin can only deactivate them and activate them again. A company can have at most **10 normal users**, and each person (CUIL) belongs to only one company. The admin sets each normal user's first password, and the user must change it the first time they log in.

Out of scope for this feature, and planned for later: normal-user profile data, recording which user performed each action, the admin changing their own password, and company-profile endpoints.

Permission catalogue, grouped by resource, with the endpoint each one controls:

- **Credit request application**
  - List credit request applications (`GET /credit-request-application/search`)
  - Create a new credit request application (`POST /credit-request/apply`)
  - Confirm a credit request proposal (`PUT /credit-request/{id}/confirm`)
- **Credit requests**
  - View a specific credit request (`GET /credit-request/{id}`)
  - List credit requests (`GET /credit-request/search`)
  - Pay an installment (`PUT /installment/{id}/pay`)
- **Payment calendar**
  - View installments pending payment (`GET /installment/pending-to-pay/search`)
- **Total debt**
  - View the company's total debt (`GET /company-total-debt`)

## Functional Requirements

**Identity and login**
- Both kinds of user log in through the same login endpoint, sending an 11-digit `taxIdentifier` (a CUIT for the admin, a CUIL for normal users) and a password. `taxIdentifier` replaces the current `cuit` login field. The response tells the caller which kind of user logged in.
- The existing company login (CUIT + password) keeps working unchanged for current companies, which become admins.
- Refresh and logout work the same way for both kinds of user.
- One tax identifier maps to exactly one login across the whole system. Creating a normal user whose CUIL matches any existing login, whether a company CUIT or another user's CUIL, is rejected. This matters because a sole proprietor's CUIT is the same number as their CUIL.
- CUIL validation follows the same rules as the CUIT validation that already exists (exactly 11 digits).
- A person (CUIL) belongs to exactly one company. This follows from tax identifiers being unique across the system, so login needs no company selector.
- Once authenticated, a normal user acts on behalf of their company. Every data-scoping rule that works by company today (credit requests, applications, installments, total debt) resolves to the user's company.

**Entity and storage strategy (recommended)**
- Add a single **user identity** concept in the Company module. It holds the tax identifier (`taxIdentifier`: CUIT or CUIL), used as the login identifier, the hashed password, the active flag, a "must change password" flag (always false for the admin), the owning company and the user type (admin or normal). It is stored in one table with a unique tax identifier.
- Model admin and normal users as two types of that identity, told apart by a type discriminator in the same table (Doctrine single-table inheritance, mapped in XML). Only the normal type carries a CUIL, a display name and a permission set. Only the admin type is linked to the company as its owner.
- The login and password move off the `Company` entity onto the admin user. `Company` keeps its CUIT as business data. Each company has exactly one admin user, whose tax identifier is the company CUIT.
- A migration creates the user table and one admin user per existing company, copying the company's CUIT and hashed password, so existing logins keep working. After that, the password column is dropped from the company table.
- Store a normal user's permissions as a list of values from a fixed permission enum in the domain. The default is one column on the user row. A separate user/permission table is the alternative if permissions ever need to be queried across users.
- The security layer keeps one user provider that loads any user by tax identifier. Because the user is reloaded from the database on every request, permission changes and deactivation apply right away, with no change needed to how tokens are issued.
- Alternatives considered and rejected:
  - Keeping credentials on `Company` and adding a separate table for normal users. This needs two lookups on login, and the database cannot enforce one identifier per login across both tables.
  - Using one class with a role flag. This makes it easy for an admin to end up with a permission list, or a normal user with a CUIT.

**User management (admin only)**
- The admin can:
  - Create a normal user, giving a CUIL, display name, initial password and an initial set of permissions (the set can be empty). New users are active and are flagged as having to change their password.
  - List the company's normal users, with each user's permissions and active status.
  - View a single normal user.
  - Update a normal user's details (display name).
  - Replace a normal user's permission set.
  - Deactivate a normal user, and activate them again.
- No endpoint deletes a user, and user records are never physically removed.
- A company can have at most 10 **active** normal users. Creating a user, or activating a deactivated one, is rejected with a clear error if the company already has 10 active normal users. Deactivated users don't count toward the limit, so the admin can free a slot by deactivating someone. The admin doesn't count either.
- The admin cannot set, reset or change a normal user's password after creating the user.
- A deactivated user cannot log in, cannot refresh tokens, and their existing access token stops working on the next request. Activating the user again restores access with the permissions they had before.
- Only the admin can call user-management endpoints. Normal users never can, whatever permissions they hold.
- The admin can only see and manage users of their own company.
- The admin cannot be deactivated, demoted or given a permission list through these endpoints.

**First-login password change**
- When a normal user flagged "must change password" logs in, they still get tokens. The login response tells the frontend that a password change is required.
- While the flag is set, the user can only change their password, log out and read their current-user info. Every other endpoint is rejected with an error that clearly says a password change is required.
- The password-change endpoint asks for the current password and a new password. The new password must differ from the current one and meet the password policy.
- **Password policy** (applies to the admin-set initial password, the new password, and the password of newly registered companies; existing company passwords are not re-checked): at least 8 characters, with at least one uppercase letter, one number and one symbol. A password that breaks the policy is rejected with an error listing the unmet rules.
- A successful change clears the flag, so the user gets their normal access on the next request. No new login is needed.
- In this feature, only normal users with the flag set can use the password-change endpoint. A general "change my password" for any user, including the admin, is planned for later.

**Permissions**
- Permissions are a fixed catalogue defined by the system, not created by admins. The catalogue can be read through an endpoint, grouped by resource, so the frontend can build the permission picker.
- Every endpoint in the catalogue is protected by its matching permission. The admin passes every check. A normal user without the permission gets 403, not an empty result.
- Permission changes take effect on the user's next request.
- The login response, or a "current user" endpoint, returns the user type and the permissions the caller holds, so the dashboard can hide sections the user cannot reach.
- The credit-bureau webhook and public company registration are unaffected. Registering a company also creates its admin user.
- The domain model stays framework-free, following the existing pattern: the security adapter lives in Infrastructure, and the entities are mapped with XML.

## Possible Edge Cases
- The admin creates a normal user whose CUIL equals an existing company's CUIT, including their own company's CUIT, or another user's CUIL. The request is rejected with a validation/conflict error.
- A new company registers with a CUIT that is already used as a normal user's CUIL. The registration is rejected for the same reason.
- The CUIL has the wrong number of digits or contains non-digits.
- The admin assigns a permission identifier that is not in the catalogue.
- The admin tries to view, update, deactivate or activate a user from another company. This must behave as "not found", without revealing that the user exists.
- The admin targets their own account with deactivate or permission changes.
- Deactivating an already deactivated user, or activating an already active one, is a no-op, not an error.
- A normal user with a valid access token is deactivated or loses a permission mid-session. The next request is rejected for the affected resource.
- A deactivated user tries to log in or refresh a token.
- A normal user with no permissions logs in successfully, but every protected resource returns 403.
- A normal user tries to reach the user-management endpoints.
- A user with "pay an installment" but no "view credit request" pays an installment of their own company. This is allowed, because the permissions are independent.
- The admin creates an 11th active normal user, or activates a deactivated user while 10 are already active. The request is rejected.
- A user who must change their password calls a protected endpoint (e.g. total debt) before changing it. The request is rejected, even if they hold the permission.
- The initial or new password breaks the policy, e.g. 8+ characters but no symbol, or no uppercase letter. It is rejected and the error lists the unmet rules.
- The new password is the same as the initial one, or the current password given is wrong. The change is rejected and the flag stays set.
- A user who has already changed their password calls the first-login change endpoint again. The request is rejected.
- A normal user forgets their password. The admin can't reset it and there is no self-service recovery yet. This is a known gap until the profile/recovery feature ships.
- Existing companies are migrated. Every company ends up with exactly one admin user, and the existing CUIT + password still logs in.

## Acceptance Criteria
- An existing company can still log in with CUIT + password as admin and reach every endpoint it could reach before.
- The admin can create a normal user with a CUIL, password and chosen permissions, and that user can log in with CUIL + password.
- A tax identifier can only belong to one user in the whole system, and the database enforces it.
- A normal user can reach exactly the catalogue endpoints they have permission for, and gets 403 on the rest.
- Data returned to a normal user is always scoped to their own company.
- The admin can list, view, update, change permissions of, deactivate and activate normal users of their own company only.
- There is no way to delete a user.
- A company cannot have more than 10 active normal users.
- A newly created normal user must change their password before reaching any other resource. After the change, they have normal access based on their permissions.
- The admin has no way to change a normal user's password.
- A deactivated user cannot log in or refresh, and their existing access token stops working. Activating them again restores access.
- Removing a permission takes effect on the affected user's next request.
- Normal users get 403 on every user-management endpoint.
- The permission catalogue is available to the frontend, grouped by resource.
- The frontend can tell the user type and permissions of the logged-in caller.
- The migration creates one admin user per existing company and removes the credentials from the company table, with no loss of login access.

## Open questions
- None blocking. Deferred to later features:
  - Revisiting how the 10-user limit is counted. This feature counts active normal users only.
  - Password recovery for normal users who forget their password.

## Testing Guidelines
Create a test file(s) in the ./tests folder for new feature, and create meaningful test for the following cases, without going too heavy:
- Unit: a normal user's permission checks (has / does not have), rejection of unknown permissions, CUIL format validation, and the password policy (accepts a valid password; rejects passwords that are too short, or have no uppercase letter, no number or no symbol).
- Unit: the admin passes every permission check, and deactivate/activate toggles the active state (calling it twice is harmless).
- Unit: the company rejects adding or activating a normal user beyond 10 active users. Changing the password clears the "must change password" flag and rejects reusing the same password.
- Integration: persisting and reloading admin and normal users from the shared table (the discriminator resolves the right type), finding normal users by company, and the unique tax identifier constraint across admin CUIT and normal-user CUIL.
- Functional: the admin creates a normal user, and the new user logs in with CUIL + password and gets tokens.
- Functional: creating a normal user whose CUIL equals an existing CUIT is rejected.
- Functional: a user with only "view total debt" gets 200 on total debt and 403 on, for example, the credit request list and installment pay.
- Functional: a normal user gets 403 on the user-management endpoints.
- Functional: the admin of company A cannot see or modify a user of company B (404).
- Functional: after deactivation, the user's old token is rejected and login fails. After activation, login works again.
- Functional: a newly created user is rejected on protected endpoints until they change their password, the change works with the correct current password, and access is granted afterwards.
- Functional: the 11th active normal user can't be created, and deactivating one frees a slot.
- Functional: the existing company login (CUIT) still has full access, so existing functional tests keep passing.
