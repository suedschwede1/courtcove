# Changelog

This project does not use semantic version numbers (it is deployed continuously via FTP
to a single hosting.example.test hosting instance, not released as versioned packages). Instead, this
file groups notable user-facing and structural changes by date. The only tagged
reference point is `mobile-view-stable` (see [README.md](README.md#layout-reference)).

When adding an entry: describe the *why*/user-facing effect in one line, not the diff —
`git log` already has that. Add new entries at the top, under today's date.

## 2026-07-06

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
