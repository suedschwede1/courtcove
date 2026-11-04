# Changelog

This project does not use semantic version numbers (it is deployed continuously via FTP
to a single hosting.example.test hosting instance, not released as versioned packages). Instead, this
file groups notable user-facing and structural changes by date. The only tagged
reference point is `mobile-view-stable` (see [README.md](README.md#layout-reference)).

When adding an entry: describe the *why*/user-facing effect in one line, not the diff —
`git log` already has that. Add new entries at the top, under today's date.

## 2026-11-04

- Added tournament entry buttons (current championship, challenge pyramid, mixed/fun tournament) to the logged-in header, linking to the tournament pages that stay on the legacy system (`TOURNAMENT_BASE_URL`).
- Added tournament privileges (admin.tournament/mixed/pyramid/draw) to the user admin so the legacy tournament modules' access flags can be granted and kept when saving a user.

## 2026-07-12

- Added `members:import-nuliga-license`, a command that backfills the `nuliga_id` on
  existing members from the official nuLiga membership export (matched by name +
  birthdate), treating the export as authoritative and overwriting conflicting existing
  values. Supports `--dry-run` to preview changes without saving.

## 2026-07-07

- Hardened the API/MCP event endpoints so square-specific events now reject unknown court IDs instead of accepting invalid `bs_squares.sid` values.

## 2026-07-06

- Documented in the README why `app/Modules/*` (homegrown, web-admin UI sections) and
  `Modules/*` (nwidart/laravel-modules, stateless feature modules like the API/MCP layer)
  are two separate, unrelated conventions — the near-identical naming kept causing
  confusion about which one to use for a new self-contained feature.
- Added an MCP (Model Context Protocol) server at `POST /api/v1/mcp` (via `laravel/mcp`),
  exposing the same booking/event/member/dues create+edit actions as MCP tools for LLM
  clients, authenticated with the same Sanctum tokens as the REST API. Each tool checks
  the token user's `admin.*` privilege itself before acting.
- Added create/edit JSON API endpoints for bookings, events, members, and dues under
  `/api/v1/...`, reusing the same validation and business logic as the admin panel (the
  repeat/conflict-check logic moved from `Admin\BookingController` into `BookingService`,
  and the date-combination/meta-sync logic from `Admin\EventController` into a new
  `EventService`, so both the admin panel and the API share one implementation).
- Removed the `/api/v1/auth/logout` endpoint (and its now-empty `AuthController`) —
  tokens are only ever revoked by an admin via `/admin/api-tokens`, not by the client.
- Added a versioned JSON API (`/api/v1/...`, self-contained module under `Modules/Api` via
  nwidart/laravel-modules) authenticated with Laravel Sanctum personal access tokens. Tokens
  are issued/revoked only through a new admin-only "API-Tokens" page (`/admin/api-tokens`,
  new `admin.api-tokens` privilege) for a chosen member — there is no public login endpoint,
  so external integrations get a token handed to them by an admin.
- Fixed a JS syntax error on the member detail page (`window.x = window.x || async (...) => {}`,
  invalid without parens around the arrow function) that silently broke every inline save
  on the page — Beiträge status/Bezahlt-bis/Teilbetrag/Kommentar edits and member field edits
  all failed with no visible error.
- Login-Historie: added stat tiles (successful/failed/throttled, today, this month)
  and a per-country breakdown to the admin page — all respect the currently applied
  filters (user/status/country/date range).
- Fixed risky login history handling so a suspicious login only becomes a trusted
  successful login after the email code is verified — the login_history row is now
  written after code entry succeeds, carrying the ip/country/risk flags captured at
  password-check time, instead of being recorded as `success` before verification.
- Added a risk-score column to the admin Benutzer (users) list so admins can spot
  risky accounts while browsing/filtering, without opening each user individually.
- Fixed the overdue member-dues filters so they include rows explicitly marked
  as overdue, not only rows computed overdue from a past due date.
- Added a standalone "Login-Historie" admin page (own sidebar entry) listing login
  events across all users, searchable/filterable by user, status, country, and date
  range — previously only viewable one user at a time from their edit page. Also
  surfaced the risk score directly on the user edit page instead of only on the
  per-user history page.
- Fixed the admin database-drift check reporting false positives on
  bs_squares.priority, member_changes.admin_user_id, and
  club_role_mail_category — the checker's manifest had drifted from what the
  migrations actually create.
- Fixed: adding a Beitrag from a member's detail page redirected to the general
  dues list instead of back to the member, making the save look like it had
  failed.
- Password reset emails now use the configured club/service name in the subject,
  matching the name shown in the booking UI.
- Documented the new risk-based email verification in the README's Security section
  (was only in CHANGELOG/code, undersold the current auth posture).
- Login now requires a 6-digit email code when it looks risky (unusual country,
  2+ recent failed attempts, or impossible travel within 24h) — normal logins are
  unaffected. Backed by a new 30-day `login_history` table. Country-based checks
  fail open when the MaxMind GeoLite2 file is missing. Admins can view a user's
  login history read-only from their edit page, including a display-only risk
  score (30-day sum of fired triggers) and which flags fired per login.
- Password reset: rate-limited the reset-password page (`GET /password/reset/{token}`,
  `throttle:20,1`) to match the throttling already on the other "forgot password" routes,
  and added tests confirming a reset token can't be reused after a successful reset and
  that an expired token is rejected.
- Password reset: added the "set new password" flow — `GET/POST /password/reset`
  (Task 5 of "forgot password" feature). A valid, unexpired token from the emailed
  link lets a user set a new password and logs them in; an invalid token or unknown
  email both show the same generic error, to avoid account enumeration. Replaces the
  temporary placeholder route added in Task 4.
- Password reset: added the "request a reset link" flow — `GET/POST /password/forgot`
  (Task 4 of "forgot password" feature) — users can request a reset email; the response
  is identical whether the email is unknown or the account is disabled, to avoid account
  enumeration. `GET /password/reset/{token}` is a temporary placeholder route (404) so
  the link generated here resolves a route name; Task 5 replaces it with the real
  set-new-password page.
- Password reset: added translation keys for forgot password and password reset pages
  (Task 3 of "forgot password" feature) — adds `password_reset` language group with
  all user-facing text for the two new pages.
- Password reset: added `PasswordReset` mailable and email template (Task 2 of
  "forgot password" feature) — sends users a 60-minute-valid reset link.
- Rate-limited `POST /register` (5/min per IP) — it had no throttling at all, unlike
  `/login` which already has a custom per-email/IP/account limiter.
- Extended the risk-based email verification spec with a display-only, per-user
  "risk score" on the admin login-history page — a 30-day sum of which triggers fired,
  no behavior change.
- Wrote the implementation plan for risk-based email verification — see
  [docs/superpowers/plans/2026-07-05-risk-based-email-verification.md](docs/superpowers/plans/2026-07-05-risk-based-email-verification.md).
  Updated to also persist which risk flags fired (for the display-only admin risk
  score). Not implemented yet.

## 2026-07-05

- Wrote a design spec for risk-based email verification on login (unusual country,
  repeated failed attempts, impossible travel) — see
  [docs/superpowers/specs/2026-07-05-risk-based-email-verification-design.md](docs/superpowers/specs/2026-07-05-risk-based-email-verification-design.md).
  Country-based checks fail open when the MaxMind GeoLite2 file is missing. Not
  implemented yet.
- Documented the security posture in the README: no MFA (deliberate — login must stay
  fast for a booking system), compensated by existing login rate limiting and
  country-based geo-blocking (`BOOKING_BLOCKED_LOGIN_COUNTRIES`).
- A `pre-commit` hook (`.githooks/pre-commit`) now blocks commits that touch
  code/behavior-relevant files without a matching CHANGELOG.md entry, so this file stays
  complete. Enable it once per clone: `git config core.hooksPath .githooks`.
- Club roles can now be tagged with multiple mail categories (many-to-many), replacing
  the earlier one-to-one `mail_category_id` link.
- `DatabaseSchemaChecker` manifest extended to cover all current tables/columns, plus a
  regression test that fails when a migration adds a table/column the checker doesn't
  know about yet.

## 2026-07-08 – 2026-07-10

- Members: added a free-text Notiz field, visible only on the edit/detail page.
- Beiträge (dues): the "bezahlt bis" fiscal year used by bulk-mark-paid is now
  configurable instead of hardcoded.
- Dashboard: charts arranged side by side; added a Mitglieder-pro-Beitragskategorie
  breakdown.
- Mail: added per-member send history, dynamic (role/category-based) audiences, and a
  GDPR settings page.

## Earlier

- Introduced the Members module (`app/Modules/Members`): member CRUD, fiscal years, fee
  categories with per-year rates, dues generation/tracking, club roles (Funktionen), and
  a bulk email composer with templates.
- Established the shared design system (see [docs/DESIGN.md](docs/DESIGN.md)) and
  tagged the stable calendar/header layout as `mobile-view-stable`.

For full detail, use `git log` — this file only tracks what's worth knowing at a glance.
