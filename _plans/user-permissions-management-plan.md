# Plan: User Permissions Management

Spec: `_specs/user-permissions-management.md`
Branch: `claude/feature/user-permissions-management` (already created)

## Context

Right now the only identity in the system is the `Company`: it logs in with its CUIT, `Company` holds the password hash, and `CompanyUser` (the security adapter) gives every logged-in company `ROLE_COMPANY`, which reaches every endpoint. The spec adds two kinds of company user:
- **Admin user**: the company's account, which logs in with the company CUIT.
- **Normal user** (called a *member* in the code): created by the admin, logs in with a CUIL, has a fixed-catalogue permission set, must change the admin-set password on first login, can be deactivated or activated but never deleted. A company can have at most 10 active members.

What I found in the current code that shapes the design:

- **`companyId` in the JWT.** The access token carries a `companyId` claim, and `JwtAuthenticatedCompanyIdProvider` reads it. All company-scoped use cases (9 classes) get their company through `AuthenticatedCompanyIdProviderInterface`. If members' tokens also carry their company's `companyId`, **company scoping needs no change at all** in the CreditRequest module.
- **The user is reloaded on every request.** Lexik's JWT authenticator calls the user provider (`loadUserByIdentifier` with the token's `username` claim, which is the user's `taxIdentifier`) for every request. If the provider loads from the new users table and rejects inactive users, permission changes and deactivation take effect on the next request with no token changes. This is what the spec asks for.
- **Refresh tokens are tied to the company.** `CompanyUserTokenRefresher` reloads the *company* from the refresh token's `companyId`. That has to become the *user* (a new `userId` claim), so a deactivated member can't refresh.
- **The password policy only partly exists.** `CompanyRegistrationDto` already enforces 8+ characters, an uppercase letter and a symbol, but **not a number**. The full policy (adds a number) will be one reusable constraint, used by the new DTOs **and** by company registration. It only affects newly registered companies: existing companies keep their current passwords, because the policy is checked only when a password is set. Every fixture password in the existing tests (`Secret123!`) already meets it.
- **17 places build a `Company`.** `new Company(...)` with a password hash as its 6th argument appears in 1 src file and 16 test files. Moving the password off `Company` means dropping that argument everywhere. It's a mechanical change.
- **Error handling needs no new wiring.** `ApiExceptionSubscriber` already maps `DomainHttpException` to its status code and `ValidationFailedException` to 422.
- **No authorization exists today.** There are no voters and no `IsGranted`, just one `access_control` rule for `ROLE_COMPANY`.

## Decisions taken in this plan (flag if you disagree)

1. **The login request key `cuit` is renamed to `taxIdentifier`**, which holds a CUIT (admin) or a CUIL (member). The validation error key becomes `taxIdentifier` too. The frontend switches to the new key and labels the field "CUIT/CUIL". Company **registration** keeps its `cuit` key, because there it is always the company's CUIT.
2. **The login response keeps the `company` block and adds a `user` block** (`id`, `type`, `displayName`, `mustChangePassword`, `permissions`). This is additive and doesn't break the frontend.
3. **Permissions are checked per endpoint with a Symfony voter** and `#[IsGranted(...)]` on each catalogue controller. Admin-only endpoints are protected by an `access_control` rule on their path prefix.
4. **The first-login password change is enforced by one request listener** that blocks every endpoint except an allowlist while the flag is set. That's simpler than checking the flag in every controller.
5. **Company registration uses the same password policy as normal users** (8+ characters, an uppercase letter, a number, a symbol). It applies to newly registered companies only; existing passwords aren't re-checked.
6. **Accepted risk on the 10-user limit:** it's checked in the use case (count before insert), with no lock. Only the admin can create or activate users, so concurrent requests going over the limit is unlikely. We can add a row lock later if needed.
7. **Refresh tokens issued before deploy will be rejected** (they have no `userId` claim), so everyone logs in once after the release. Access tokens keep working until they expire, because their `username` claim (the CUIT) still resolves to the migrated admin.

## Approach

### 1. Domain — `src/Company/Domain/Model/`

- **`Permission`** (new string-backed enum): the 8 catalogue entries, with stable values that are never renamed (see the catalogue-change rule discussed in the spec review):
  - `credit_request_application.list`
  - `credit_request_application.create`
  - `credit_request.confirm`
  - `credit_request.view`
  - `credit_request.list`
  - `installment.pay`
  - `payment_calendar.view`
  - `total_debt.view`

  It also has a `resource(): string` method that returns the group key (`credit_request_application`, `credit_requests`, `payment_calendar`, `total_debt`) so the catalogue endpoint can group entries, and a `values()` helper like `TaxStatus::values()`.
- **`User`** (new abstract class, the root of single-table inheritance):
  - Fields: `id`, `company` (`Company`), `taxIdentifier` (11 digits, validated in the constructor like `Company` does for its CUIT), `hashedPassword`, `active`.
  - Methods: getters, plus abstract `isAdmin()`, `hasPermission(Permission)`, `permissions(): Permission[]` and `mustChangePassword()`.
- **`AdminUser extends User`:**
  - Created by a named constructor from a `Company` and a password hash. Its `taxIdentifier` is the company CUIT.
  - `hasPermission()` always returns true, `permissions()` returns `Permission::cases()`, `mustChangePassword()` returns false, and it is always active.
- **`MemberUser extends User`:**
  - Adds `displayName`, a list of permission values (stored as strings, exposed as `Permission[]`), and a `mustChangePassword` flag (true on creation).
  - Domain behaviour: `rename()`, `replacePermissions(Permission[])` (removes duplicates), `deactivate()` / `activate()` (calling either twice is harmless), and `changeInitialPassword(string $newHash)`, which clears the flag and throws if the flag is already cleared.
  - Holds the constant `MAX_ACTIVE_PER_COMPANY = 10`.
- **`Company`:** remove `hashedPassword` (constructor argument, property, getter). Everything else stays the same.
- **New exceptions** in `src/Company/Domain/Exception/`, all extending `DomainHttpException`:
  - `UserNotFoundException` (404)
  - `UserTaxIdentifierAlreadyTakenException` (409)
  - `ActiveUserLimitReachedException` (409)
  - `PasswordChangeRequiredException` (403, with a stable error message the frontend can detect)
  - `PasswordChangeNotAllowedException` (409, when the flag is already cleared)
  - `NewPasswordMustDifferException` (422)

### 2. Repository — `src/Company/Domain/Repository/UserRepositoryInterface.php` (new)

- `save(User)`
- `findById(string): ?User`
- `findByTaxIdentifier(string): ?User`: used by login and the security provider. It also covers the "taken anywhere" check, since admins and members share the table.
- `findMemberByIdAndCompany(string $id, string $companyId): ?MemberUser`: only matches members, so the admin can't target their own admin row and other companies' users come back as "not found".
- `findMembersByCompany(string $companyId): MemberUser[]`
- `countActiveMembersByCompany(string $companyId): int`

Implementation: `src/Company/Infrastructure/Persistence/Repository/DoctrineUserRepository.php`, bound in `config/services.yaml` under the repository bindings section.

### 3. ORM mapping — `src/Company/Infrastructure/Persistence/Doctrine/Mapping/`

- **`User.orm.xml`:**
  - Table `users`, `inheritance-type="SINGLE_TABLE"`, discriminator column `type` with the map `admin` → `AdminUser` and `member` → `MemberUser`.
  - Common fields: `tax_identifier` (length 11, with unique constraint `uniq_users_tax_identifier`), `password`, `active`.
  - A `many-to-one` to `Company` (`company_id`, not null).
  - A partial unique index on `company_id` with `where: type = 'admin'`, so there is one admin per company.
- **`AdminUser.orm.xml`:** no extra fields.
- **`MemberUser.orm.xml`:**
  - `display_name` (string)
  - `permissions` (`json`)
  - `must_change_password` (boolean)

  These are nullable or defaulted at column level, because admin rows leave them empty.
- **`Company.orm.xml`:** remove the `hashedPassword` field.
- No `doctrine.yaml` change is needed, because the existing `Company` mapping entry already covers the folder and namespace.

### 4. Migration — `migrations/VersionYYYYMMDDHHMMSS.php`

Generate with `doctrine:migrations:diff`, then **edit by hand** so the data move happens between the schema steps:
1. Create the `users` table, its unique constraint, its partial unique index and the foreign key to `companies`.
2. `INSERT INTO users (id, company_id, tax_identifier, password, active, type, ...) SELECT gen_random_uuid()::text, id, cuit, password, true, 'admin', ... FROM companies`. That creates one admin per existing company with the same CUIT and hash. Postgres 16 has `gen_random_uuid()` built in.
3. Drop `companies.password`.

`down()` does the reverse: add `companies.password` back, copy the hash back from admin rows, then drop `users`.

### 5. Security — `src/Company/Infrastructure/Security/`

- **`CompanyUser`** (adapter): build it from a `User` instead of a `Company`.
  - Holds `userId`, `companyId`, `taxIdentifier`, the hash, the `isAdmin` flag, the `mustChangePassword` flag and the permission values.
  - `getUserIdentifier()` returns `taxIdentifier`.
  - `getRoles()` returns `ROLE_COMPANY`, plus `ROLE_COMPANY_ADMIN` for admins.
  - Exposes `hasPermission(string)` and `mustChangePassword()` for the voter and the listener.

  The class name stays, so `security.yaml` password hashers and `SymfonyPasswordHasher` don't change.
- **`CompanyUserProvider`:** loads with `UserRepositoryInterface::findByTaxIdentifier`. It throws `UserNotFoundException` when the user is missing **or inactive**, so a deactivated member's existing access token gets a 401 on the next request.
- **`LexikAuthTokenGenerator`** and **`AuthTokenGeneratorInterface`:** generate tokens for a `User` instead of a `Company`. The payload keeps `companyId` (taken from the user's company) and adds `userId`.
- **`RefreshTokenClaims`** and **`LexikRefreshTokenReader`:** add a required `userId` claim. A token without it is rejected (Decision 7).
- **`PermissionVoter`** (new):
  - Handles attributes that are `Permission` values.
  - Grants if the token user is a `CompanyUser` with `hasPermission()`, which is always true for admins.
  - Lives in Infrastructure/Security and is autoconfigured as a voter.
- **`PasswordChangeRequiredListener`** (new):
  - Runs on `kernel.request`, after the firewall (priority below 8).
  - If the authenticated `CompanyUser` must change their password and the route isn't in the allowlist (`me`, `me_password_change`, `company_user_logout`), it throws `PasswordChangeRequiredException`, which is rendered as 403.
  - It ignores requests without a `CompanyUser`, such as the webhook and public routes.

### 6. Application — `src/Company/Application/`

**Validation:**
- `Validation/StrongPassword.php`: a new Symfony `Compound` constraint with the spec's policy (8+ characters, one uppercase letter, one number, one symbol), each rule with its own message so the 422 lists the unmet rules.

**DTOs** (self-validating from `Request` + `ValidatorInterface`, same style as `CompanyUserLoginDto`):
- `MemberUserCreateDto`: `id` (UUID v4, caller-generated, as in registration), `taxIdentifier` (the member's CUIL, `/^\d{11}$/`), `displayName` (not blank), `password` (`StrongPassword`), `permissions` (a list where each item is a `Choice` of `Permission::values()`).
- `MemberUserUpdateDto`: `displayName`.
- `MemberUserPermissionsUpdateDto`: `permissions`.
- `PasswordChangeDto`: `currentPassword` (not blank) and `newPassword` (`StrongPassword`).

**Use cases:**
- **`MemberUserCreator`:**
  - Rejects with `UserTaxIdentifierAlreadyTakenException` if `findByTaxIdentifier` finds the CUIL.
  - Rejects with `ActiveUserLimitReachedException` if the company already has 10 active members.
  - Otherwise hashes the password, builds a `MemberUser` and saves it.
- **`MemberUserSearcher`, `MemberUserFinder`:** list members, or find one by id. The finder throws `UserNotFoundException` when there's no match for the authenticated company.
- **`MemberUserUpdater`, `MemberUserPermissionsUpdater`:** find, apply the change, save.
- **`MemberUserDeactivator`, `MemberUserActivator`:** find, toggle, save. The activator checks the limit first, but only when the user is currently inactive.
- **`PasswordChanger`:**
  1. Loads the current member.
  2. Verifies `currentPassword` and throws `InvalidCredentialsException` on mismatch.
  3. Rejects a new password equal to the current one.
  4. Calls `changeInitialPassword()`.

  Admins get `PasswordChangeNotAllowedException`, because the admin self-change is a later feature.
- **`CurrentUserProvider`** (for `/me`):
  - Returns the `User` behind the token.
  - Gets the user id through a new small domain interface, `AuthenticatedUserIdProviderInterface`, in `Company/Domain/Service`.
  - Its Infrastructure implementation reads the `userId` claim, mirroring `JwtAuthenticatedCompanyIdProvider`.

**Existing DTOs changed:**
- **`CompanyUserLoginDto`:** rename the `cuit` property and JSON key to `taxIdentifier` (same `/^\d{11}$/` rule), per Decision 1.
- **`CompanyRegistrationDto`:** replace its inline password `Length` and `Regex` rules with `StrongPassword`, which adds the number rule (Decision 5).

**Existing use cases changed:**
- **`CompanyUserAuthenticator`:** reads `$input->taxIdentifier` and looks up with `findByTaxIdentifier`, verifies against `User::getHashedPassword()`, and rejects inactive users with the same `InvalidCredentialsException`, so there's no account enumeration. It returns the user and the company.
- **`CompanyUserLoginResultDto`:** add `User $user`.
- **`CompanyUserTokenRefresher`:** loads the user by the `userId` claim and rejects a missing or inactive user with `InvalidRefreshTokenException`.
- **`CompanyRegistrator`:**
  - Also rejects a CUIT already used as any user's tax identifier, using the existing `CompanyCuitAlreadyRegisteredException`.
  - Creates the `AdminUser` along with the `Company`, holding the hashed password.
  - Wraps both saves in `TransactionManagerInterface`.

### 7. UI — `src/Company/UI/Api/`

New controllers (single `__invoke`, attribute routes, picked up by the existing `config/routes.yaml` Company entry), following the existing `/credit-request/...` path style:

| Route | Method | Name | Controller | Access |
|---|---|---|---|---|
| `/user` | POST | `user_create` | `MemberUserCreatePostController` | admin |
| `/user/search` | GET | `user_search` (priority 1) | `MemberUserSearchGetController` | admin |
| `/user/permission-catalogue` | GET | `user_permission_catalogue` (priority 1) | `MemberUserPermissionCatalogueGetController` | admin |
| `/user/{id}` | GET | `user_find` | `MemberUserFindGetController` | admin |
| `/user/{id}` | PUT | `user_update` | `MemberUserUpdatePutController` | admin |
| `/user/{id}/permissions` | PUT | `user_permissions_update` | `MemberUserPermissionsUpdatePutController` | admin |
| `/user/{id}/deactivate` | PUT | `user_deactivate` | `MemberUserDeactivatePutController` | admin |
| `/user/{id}/activate` | PUT | `user_activate` | `MemberUserActivatePutController` | admin |
| `/me` | GET | `me` | `CurrentUserGetController` | any logged-in user |
| `/me/password` | PUT | `me_password_change` | `PasswordChangePutController` | any logged-in user (the use case rejects admins) |

- `Serializer/MemberUserSerializer.php` (new): `id`, `type`, `taxIdentifier`, `displayName`, `active`, `mustChangePassword`, `permissions`.
- `CompanyUserAuthResultSerializer`: add the `user` block (Decision 2).
- No delete route anywhere.

### 8. Protecting the catalogue endpoints — `src/CreditRequest/UI/Api/Controller/`

Add `#[IsGranted(Permission::X->value)]` to each controller:

| Controller | Permission |
|---|---|
| `CreditRequestApplicationSearchGetController` | `credit_request_application.list` |
| `CreditRequestApplicationCreatePostController` | `credit_request_application.create` |
| `CreditRequestConfirmPutController` | `credit_request.confirm` |
| `CreditRequestFindGetController` | `credit_request.view` |
| `CreditRequestSearchGetController` | `credit_request.list` |
| `InstallmentPayPutController` | `installment.pay` |
| `InstallmentPendingToPaySearchGetController` | `payment_calendar.view` |
| `TotalDebtGetController` | `total_debt.view` |

`CreditRequestApplicationUpdatePutController` (the bureau webhook) is **not** touched.

### 9. Config

- **`config/packages/security.yaml`:** add `- { path: '^%app.api_prefix%/user(/|$)', roles: ROLE_COMPANY_ADMIN }` **before** the generic `ROLE_COMPANY` rule, so `/me` stays reachable for members. Optionally add `role_hierarchy: { ROLE_COMPANY_ADMIN: ROLE_COMPANY }`.
- **`config/services.yaml`:**
  - Alias `UserRepositoryInterface` → `DoctrineUserRepository`.
  - Alias `AuthenticatedUserIdProviderInterface` → its JWT implementation.
  - The voter and listener are autoconfigured. If the listener uses `#[AsEventListener]`, it needs no tag.

### 10. Mechanical updates

- Remove the 6th (password) argument from `new Company(...)` in `CompanyRegistrator` and the 16 test files listed in Context.
- `tests/Functional/UI/CompanyRegistrationPostControllerTest.php:63` reads `Company::getHashedPassword()`. Change it to read the admin user's hash.
- Update `CompanyUserAuthenticatorTest` and `CompanyUserTokenRefresherTest` to mock `UserRepositoryInterface`. `CompanyUserAuthenticatorTest`'s request helper (line ~107) sends `taxIdentifier` instead of `cuit`.
- Rename the login key from `cuit` to `taxIdentifier`:
  - `tests/Functional/ApiAuthenticationTrait.php:45`: the `loginTokens()` request body. Its parameter can stay named `$cuit` or become `$taxIdentifier`. Callers pass `$company['cuit']` unchanged, because registration payloads keep `cuit`.
  - `tests/Functional/UI/CompanyUserLoginPostControllerTest.php`: the request bodies at lines 20, 55, 72, 87 and 101. The validation test at line 90 asserts the `taxIdentifier` error key. Line 43 still asserts `body.company.cuit`, which is unchanged.
- Registration fixtures in the CreditRequest functional tests keep their `cuit` key. No change.
- Rename the existing login/logout classes from `CompanyLogin…`/`CompanyLogout…` to `CompanyUserLogin…`/`CompanyUserLogout…`. These are file renames plus updating every reference:

  | Current | New |
  |---|---|
  | `Application/DTO/CompanyLoginDto` | `CompanyUserLoginDto` |
  | `Application/DTO/CompanyLoginResultDto` | `CompanyUserLoginResultDto` (also used by `CompanyUserTokenRefresher` and `CompanyUserAuthResultSerializer`) |
  | `Application/DTO/CompanyLogoutDto` | `CompanyUserLogoutDto` |
  | `Application/UseCase/CompanyLogout` | `CompanyUserLogout` |
  | `UI/Api/Controller/CompanyLoginPostController` | `CompanyUserLoginPostController` |
  | `UI/Api/Controller/CompanyLogoutPostController` | `CompanyUserLogoutPostController` |
  | `tests/Functional/UI/CompanyLoginPostControllerTest` | `CompanyUserLoginPostControllerTest` |
  | `tests/Functional/UI/CompanyLogoutPostControllerTest` | `CompanyUserLogoutPostControllerTest` |
  | `tests/Unit/Company/Application/UseCase/CompanyLogoutTest` | `CompanyUserLogoutTest` |

  | `Application/UseCase/CompanyAuthenticator` | `CompanyUserAuthenticator` |
  | `Application/UseCase/CompanyTokenRefresher` | `CompanyUserTokenRefresher` |
  | `UI/Api/Serializer/CompanyAuthResultSerializer` | `CompanyUserAuthResultSerializer` |
  | `tests/Unit/Company/Application/UseCase/CompanyAuthenticatorTest` | `CompanyUserAuthenticatorTest` |
  | `tests/Unit/Company/Application/UseCase/CompanyTokenRefresherTest` | `CompanyUserTokenRefresherTest` |
  | `UI/Api/Controller/CompanyTokenRefreshPostController` | `CompanyUserTokenRefreshPostController` |
  | `tests/Functional/UI/CompanyTokenRefreshPostControllerTest` | `CompanyUserTokenRefreshPostControllerTest` |

- Rename the login/logout/refresh **route names** (the Symfony `name:` in `#[Route]`). The **paths stay the same**:

  | Path | Current route name | New route name |
  |---|---|---|
  | `POST /login` | `company_login` | `company_user_login` |
  | `POST /logout` | `company_logout` | `company_user_logout` |
  | `POST /refresh` | `company_token_refresh` | `company_user_token_refresh` |

  No code or test refers to these names today (checked with grep), so the only other place to update is the password-change listener's allowlist (step 5). Route names are internal to the backend. The frontend calls paths, so this rename doesn't change any frontend call. It's recorded in "API contract changes for the frontend" below for completeness.

### 11. Frontend — `/home/ivanur/projects/fintech-front/` (separate follow-up, not part of this backend branch)

This should get its own spec and plan in the front repo, based on the contract below.

#### API contract changes for the frontend

Paths are relative to `/api/v1`.

**Changed endpoints**
- **`POST /login`:**
  - The route name is now `company_user_login` (was `company_login`). The path doesn't change.
  - Request body: `{ "taxIdentifier": "<11 digits>", "password": "..." }`. The key `cuit` is no longer accepted and gives 422 on `taxIdentifier`. Label the field "CUIT/CUIL".
  - Response: `accessToken`, `refreshToken` and `company` are unchanged. A new `user` block is added: `{ id, type: "admin"|"member", taxIdentifier, displayName|null, active, mustChangePassword, permissions: string[] }`.
- **`POST /logout`:** the route name is now `company_user_logout` (was `company_logout`). The path, request and response don't change.
- **`POST /refresh`:**
  - The route name is now `company_user_token_refresh` (was `company_token_refresh`). The path doesn't change.
  - The response gets the same new `user` block.
  - Refresh tokens issued before this release are rejected (401/invalid). The user has to log in again once.
- **`POST /company_registration`:** the password now also requires a number. The full rule is 8+ characters, one uppercase letter, one number and one symbol. Registration still uses the `cuit` key.

**New endpoints**
- **Any logged-in user:**
  - `GET /me`: returns the current `user` block.
  - `PUT /me/password`: body `{ currentPassword, newPassword }`.
- **Admin only:**
  - `GET /user/search`
  - `GET /user/{id}`
  - `POST /user`: body `{ id, taxIdentifier, displayName, password, permissions[] }`.
  - `PUT /user/{id}`: body `{ displayName }`.
  - `PUT /user/{id}/permissions`: body `{ permissions[] }`.
  - `PUT /user/{id}/deactivate`
  - `PUT /user/{id}/activate`
  - `GET /user/permission-catalogue`: the catalogue, grouped by resource.

**New behaviours to handle**
- **Password change required:** while `mustChangePassword` is true, every endpoint except `/me`, `/me/password` and `/logout` returns 403 with `{"error": "Debe cambiar su contraseña para continuar."}`. Show a forced password-change screen, then continue. Detect this case from `user.mustChangePassword` (login/refresh/`/me`), not by matching the message text.
- **Permission denied:** a member calling an endpoint they don't have permission for (or any `/user...` route) gets 403 with `{"error": "No tiene permiso para acceder a este recurso."}`. Hide dashboard sections based on `user.permissions`. The admin has all of them.
- **Deactivated user:** the next request gets 401, and login and refresh fail. Treat this like a session expiry.
- **User-management errors:**
  - 409 `{"error": "Ya existe un usuario con ese CUIT/CUIL."}` when the tax identifier is already taken.
  - 409 `{"error": "La empresa ya alcanzó el máximo de usuarios activos."}` when the limit of 10 active users is reached.
  - 404 `{"error": "Usuario no encontrado."}` for an unknown member or one from another company.
  - 422 for password-policy violations (one message per unmet rule).
- **Password-change errors (`PUT /me/password`):**
  - 422 `{"error": "La contraseña actual es incorrecta."}` for a wrong `currentPassword`. It is deliberately **not** 401, so the client's "401 → refresh/logout" handling doesn't fire.
  - 422 `{"error": "La nueva contraseña debe ser distinta de la actual."}` when the new password equals the current one.
  - 422 `errors.newPassword` for policy violations.
  - 409 `{"error": "Este usuario no tiene permitido cambiar la contraseña."}` when the user isn't allowed to change it: the password was already changed, or the caller is the admin.
- **Catalogue shape (`GET /user/permission-catalogue`):** `[{ "resource": "credit_request_application", "permissions": ["credit_request_application.list", ...] }, ...]`. The resource keys are `credit_request_application`, `credit_requests`, `payment_calendar` and `total_debt`.

#### Frontend work items
- Login: send `taxIdentifier` instead of `cuit` (`company-login-schema.ts`, `CompanyCredentials` in `company/types/company.ts`), and label the field "CUIT/CUIL".
- Registration form/schema: require a number in the password.
- Store `user` from the login/refresh response in `auth-store.ts`.
- Build the forced password-change screen.
- Gate dashboard sections on permissions.
- Build the admin user-management screens.
- Rename the login/auth names from `Company…` to `CompanyUser…`, matching the backend renames, and update every import and usage:

  | Current | New | Used in |
  |---|---|---|
  | `CompanyCredentials` (type, `company/types/company.ts`) | `CompanyUserCredentials` | `company/api/company-auth-api.ts` |
  | `CompanyAuthResult` (type, `company/types/company.ts`) | `CompanyUserAuthResult` | `company/api/company-auth-api.ts`, `shared/api/api-client.ts` (refresh flow) |
  | `company/schemas/company-login-schema.ts` | `company/schemas/company-user-login-schema.ts` | `company/components/company-login-form.tsx` |
  | `companyLoginSchema`, `CompanyLoginInput`, `CompanyLoginOutput` (in that schema) | `companyUserLoginSchema`, `CompanyUserLoginInput`, `CompanyUserLoginOutput` | the login form |
  | `company/components/company-login-form.tsx` | `company/components/company-user-login-form.tsx` | `App.tsx` |
  | `CompanyLoginForm` (component) | `CompanyUserLoginForm` | `App.tsx` |
  | `loginCompany()`, `logoutCompany()` (`company/api/company-auth-api.ts`) | `loginCompanyUser()`, `logoutCompanyUser()` | their callers |

  Not renamed:
  - `Company`, `CompanyRegistration*` and `registerCompany()`, because registration is still a company action.
  - The `company/` folder and `company-auth-api.ts`.
  - The paths in `NO_REFRESH_PATHS` and the API calls (`/login`, `/logout`, `/refresh`), because backend paths don't change.

## Testing

**Unit (`tests/Unit/Company/`):**
- `Domain/Model/MemberUserTest`:
  - `hasPermission()` true and false.
  - `replacePermissions()` removes duplicates.
  - deactivate/activate work and are harmless when repeated.
  - `changeInitialPassword()` clears the flag and throws when called a second time.
  - A bad CUIL is rejected.
- `Domain/Model/AdminUserTest`: has every permission and never needs a password change.
- `Application/UseCase/MemberUserCreatorTest`: tax identifier already taken gives 409, the 10-user limit gives 409, and the happy path saves a member flagged to change the password.
- `Application/UseCase/MemberUserActivatorTest`: hits the limit at 10, and activating an already-active user doesn't check the limit.
- `Application/UseCase/PasswordChangerTest`: wrong current password, same password, admin rejected, and the happy path.
- `Application/Validation/StrongPasswordTest`: accepts a valid password, and rejects one that is short, has no uppercase letter, no number, or no symbol.
- Update `CompanyUserAuthenticatorTest` (inactive user rejected) and `CompanyUserTokenRefresherTest` (inactive or missing user rejected).

**Integration (`tests/Integration/Company/DoctrineUserRepositoryTest`):** this is the first integration test in the repo, so a `KernelTestCase` with DAMA rollback.
- Save and reload an admin and a member; the discriminator resolves the right class and the permissions round-trip.
- The unique `tax_identifier` constraint applies across the admin CUIT and a member CUIL.
- `findMemberByIdAndCompany` doesn't return other companies' members or the admin.
- `countActiveMembersByCompany` ignores inactive members.

**Functional (`tests/Functional/UI/`):** extend `ApiAuthenticationTrait` with a `createMemberAndLogin(client, adminToken, permissions)` helper that creates the member, logs in, changes the password and logs in again.
- `MemberUserCreatePostControllerTest`:
  - Create a user, and that user logs in with CUIL + password.
  - A CUIL equal to an existing CUIT gives 409.
  - A weak password gives 422 listing the rules.
  - The 11th active user gives 409.
- `MemberUserManagementTest`: list, find, update, permissions update, and deactivate/activate.
  - Company A's admin gets 404 on company B's user.
  - A member gets 403 on every `/user...` route.
- `PermissionEnforcementTest`: a member with only `total_debt.view` gets 200 on total debt, and 403 on credit request search and installment pay.
- `PasswordChangeFlowTest`:
  - Before the change, total debt gives 403 with the password-change error and `/me` gives 200.
  - After the change, access matches the user's permissions.
- `MemberUserDeactivationTest`: after deactivation, the old access token gets 401 and login and refresh both fail. After activation, login works again.
- `CompanyRegistrationPostControllerTest`: add a case where a password with no number (e.g. `Secret!!x`) gets 422 on `password`. Existing weak-password cases still fail as before.
- `CompanyUserLoginPostControllerTest`: a member logs in with their CUIL through `taxIdentifier`. A request that still sends the old `cuit` key gets 422 on `taxIdentifier`.
- **Existing suite:** `CompanyUserLoginPostControllerTest`, `CompanyUserTokenRefreshPostControllerTest` and all CreditRequest functional tests must still pass unchanged (the admin has full access). Add an assertion for the new `user` block in the login test.

## Implementation notes (differences from the plan above)

- **One serializer for both user types:** `CompanyUserSerializer` instead of `MemberUserSerializer`. The same `user` shape is returned by login/refresh, `/me` and member management, and it also serializes the admin, so a `MemberUser…` name would be misleading.
- **Wrong current password on `PUT /me/password`:** `InvalidCurrentPasswordException` (422) instead of `InvalidCredentialsException` (401). The frontend treats 401 as an expired session and would log the user out.
- **User id for `/me`:** `SecurityAuthenticatedUserIdProvider` reads the user loaded by the security layer, not a `userId` JWT claim. This also works for access tokens issued before the release.
- **403 responses:** `ApiExceptionSubscriber` now maps `AccessDeniedHttpException` (from `#[IsGranted]` and `access_control`) to `{"error": "No tiene permiso para acceder a este recurso."}`, matching the API's other errors. Before, it was Symfony's problem-details body, which in dev includes the exception class.
- **One-admin-per-company index:** the predicate is written as Postgres normalizes it (`((type)::text = 'admin'::text)`). Otherwise `doctrine:schema:validate` keeps reporting a diff.
- **Migration:** `migrations/Version20261022020205.php`.
- **Spanish error messages:** the exceptions added by this feature (and the 403 "access denied" body) carry their `error` message in Spanish, so the frontend can show it as is. Pre-existing exceptions (`Invalid credentials.`, `Company not found.`, etc.) and validation messages (including `StrongPassword`) are still in English and will be translated in a later change.

## Verification

1. `make bash`, then `bin/console doctrine:migrations:diff`. Hand-edit the data-move step as in step 4, then run `make migrate` against a DB that already has companies, and check that each company has one `admin` row and that `companies.password` is gone.
2. `bin/console doctrine:schema:validate`: mapping and DB are in sync.
3. `bin/phpunit --testsuite Unit`, then `--testsuite Integration`, then `--testsuite Functional`. Everything passes, with no regressions in existing tests.
4. Manual check through `http-client.env.json` / a REST client:
   1. An existing company logs in with its CUIT.
   2. It creates a member.
   3. The member logs in with the CUIL and gets 403 everywhere except `/me` and `/me/password`.
   4. The member changes the password and reaches only the granted endpoints.
   5. The admin deactivates the member, and the member's next request gets 401.
