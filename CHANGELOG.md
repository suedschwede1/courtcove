# Changelog

This project does not use semantic version numbers (it is deployed continuously via FTP
to a single shared-hosting instance, not released as versioned packages). Instead, this
file groups notable user-facing and structural changes by date. The only tagged
reference point is `mobile-view-stable` (see [README.md](README.md#layout-reference)).

When adding an entry: describe the *why*/user-facing effect in one line, not the diff —
`git log` already has that. Add new entries at the top, under today's date.

## 2026-11-05
- Admin pages use the new shell; on phones the admin menu slides in behind a menu button.
- Admin sidebar in the new dark style with groups (Betrieb, Mitglieder, Einstellungen) and a clay marker for the current page.
- Admin navigation is built from one grouped list; tournament entries appear only once their pages exist.

- Legacy element rules use zero-specificity guards so existing buttons, links and inputs keep their original cascade.
- Admin and Members pages show their prefix in the browser tab again even when a view sets its own title.
- Legacy element rules (links, text inputs, touch-action) no longer restyle the new ds components, which now carry their own base font size.
- Phones get a full-screen menu behind one button instead of the "..." actions list; no bottom bar.
- Header switch no longer animates its knob.
- New dark header with main menu, admin mode, language and account; date navigation moves to a bar below until the calendar toolbar arrives.
- Navigation items are built once for the header and the phone menu; logo falls back to the club initials.
- Tab counts stay readable on the active phone tab.
- One shared tab bar for module sub-pages; chips on phones.
- Shared table component: header row, highlighted rows and card layout on phones.
- Shared form fields with visible labels, help and error text linked for screen readers.
- Shared card, key figure and notice components of the new design.
- Status badges use one shared colour map (good, neutral, attention, problem, you).
- Shared button components with 46/52 px touch targets.

## 2026-11-04

- Shared SVG icon set for the new design (no icon font, no emoji).
- English members area no longer falls back to German for the short booking-system label.
- The UI linter now checks the new design rules (tokens, 46/52 px controls, no inline styles, emoji or character icons) across all views, module views and CSS.
- All pages share one page frame (fonts, styles, head) instead of five copies.
- Site-wide switch to the new palette and fonts on existing pages, with a visible keyboard focus outline everywhere.
- Fonts are served from the app itself (Barlow); no request to an external font service.
- Design tokens of the new look (sand, clay, court, Barlow scale) defined in one place; dark values prepared, not switched on.
- Fix HTTP 500 when `public/` is served through a symlink (Apache + PHP-FPM keeps `__DIR__` unresolved): `public/index.php` and `public/zf2-bridge.php` now derive the application root from `realpath(__DIR__)`, so vendor, bootstrap, maintenance file, `zf2.path` and the default ZF2 dir are found at the real location.
- Emoji stripping no longer touches password/token fields (an emoji password was silently altered) and skips the utf8mb4-only API member/due endpoints.
- Emoji and other 4-byte characters are stripped from request input (web and api) before they reach the utf8mb3 legacy tables, which previously failed with an HTTP 500; the Members module (utf8mb4) is exempt and keeps emoji.
- Bridge guard hardening: the direct-request check now uses the raw request URI (query cut off, decoded until stable, slashes collapsed; no `parse_url`), so `//zf2-bridge.php/x`, `///zf2-bridge.php/x` and encoded forms answer 404, and `public/.htaccess` additionally 404s any raw request line containing `zf2-bridge`.
- Tournament bridge hardening: direct requests to `zf2-bridge.php` (including `/zf2-bridge.php/x` PATH_INFO forms) answer 404; the default ZF2 dir (`../zf2/public`) applies only to an app directory named `bookingnew` (staging needs `zf2.path`/`ZF2_PUBLIC`, else 503); `zf2.path` must be absolute; allowlisted paths with a trailing slash are 301-redirected to the slashless URL; the deploy script follows the same default/trim rules.
- Docs: the cutover design, runbook, README and AGENTS now describe the same-host tournament model (ZF2 install in `~/apps/zf2`, `zf2.path`, asset linking, routing test table) instead of the `turnier.` subdomain.
- Deploy script links the legacy ZF2 static assets (css, js, imgs, vendor, ...) into `public/` after extracting (ZF2 dir from `zf2.path` or `../zf2/public`; skipped with a note when missing), so the same-host tournament pages are styled.
- Tournament buttons now link to the same host (`/pyramide`, `/mixed-tournament`, `/tournament`, honoring the `/booking` base path); `TOURNAMENT_BASE_URL` is empty by default and only needed for a separate tournament host. The `/user/login|logout|password[-reset]` redirects are gone because ZF2 serves those paths.
- Tournament pages stay on the same host: `public/.htaccess` routes `/tournament`, `/mixed-tournament`, `/pyramide`, `/draw`, `/service` and the ZF2 login/logout/password pages through the new `public/zf2-bridge.php` to the legacy ZF2 app (located via `ZF2_PUBLIC`, an untracked `zf2.path` file, or `../zf2/public`); everything else stays with Laravel.
- Deploy script: re-deploys no longer fail on stale generated `bootstrap/cache` files (now untracked and deleted before any artisan call), `.env.dusk*` are no longer shipped, the wipe keeps `lang/de/booking/quote_groups.php`, and new `--first-deploy` / `--skip-build` flags make the documented first-deploy order possible (without `--first-deploy` the script refuses when the server DB has no migrations table).
- Legacy ZF2 URL redirects are now temporary (302) instead of 301, so a rollback to the old system is not poisoned by cached permanent redirects, and `/user/password`, `/user/password-reset`, `/user/registration` and `/user/activation[/...]` are covered too.
- Added a guarded `sessions` table migration so `SESSION_DRIVER=database` (the production setting) works after `migrate`; the composite meta-table index migration is now re-runnable after a partial failure.
- Fixed a 500 on login with "remember me" for normal members: `bs_users` has no `remember_token` column, so the User model no longer persists one.
- Fixed the booking modals, player autocomplete and member/dues inline editing so their requests honor the `/booking` URL base path (they used root-absolute `/bookings/...` and `/members/...` URLs and would have hit the wrong host path in production).
- Restored `public/.htaccess` (front-controller rewrite, Authorization passthrough) that the Laravel scaffold had dropped; without it every route except `/booking/` returned 404 on Apache.
- The deploy script no longer deletes `*.sqlite*` files, refuses a server `.env` with `DB_CONNECTION=sqlite`, and aborts before extracting if cleaning the old files fails.
- Hardened the (local, untracked) deploy script: refuses non-`master` branches without `--allow-branch`, asks for confirmation, requires the server `.env`, and runs the deploy in maintenance mode (stale files removed, caches cleared, site left down if a step fails).
- Added temporary (302) redirects for the old ZF2 URLs (`/user/login`, `/user/logout`, `/user/bookings`, `/user/settings`, `/square/...`, `/backend/...`) and a tar-over-ssh deploy script (kept locally) that keeps the server `.env` and `storage/`; README/AGENTS deployment docs rewritten accordingly.
- Added `db:baseline-legacy [--dry-run]`, which records the `create_bs_*` migrations as applied for legacy tables that already exist, so `migrate --force` can run against the production database created by the old system without recreating tables.
- Highlighted club championship bookings (note contains "Vereinsmeisterschaft") in orange in the calendar, on desktop and mobile, as the legacy system did.
- Added tournament entry buttons (current championship, challenge pyramid, mixed/fun tournament) to the logged-in header, linking to the tournament pages that stay on the legacy system (`TOURNAMENT_BASE_URL`, defaulting to the legacy host when unset or empty).
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
  the risk-based email verification plans document (kept locally, not in the repository).
  Updated to also persist which risk flags fired (for the display-only admin risk
  score). Not implemented yet.

## 2026-07-05

- Wrote a design spec for risk-based email verification on login (unusual country,
  repeated failed attempts, impossible travel) — see
  the risk-based email verification specs document (kept locally, not in the repository).
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
