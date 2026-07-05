# Changelog

This project does not use semantic version numbers (it is deployed continuously via FTP
to a single hosting.example.test hosting instance, not released as versioned packages). Instead, this
file groups notable user-facing and structural changes by date. The only tagged
reference point is `mobile-view-stable` (see [README.md](README.md#layout-reference)).

When adding an entry: describe the *why*/user-facing effect in one line, not the diff —
`git log` already has that. Add new entries at the top, under today's date.

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
