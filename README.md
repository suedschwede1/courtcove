# Tennis Booking System

Laravel-based court booking system for a tennis club.
Replaces the old Zend/Laminas system (`booking`).

## Stack

| Component | Version |
|---|---|
| PHP | 8.3 |
| Laravel | 13.x |
| Database | MySQL (`booking_local`) |
| Frontend | Tailwind CSS, Alpine.js, Vite |
| Deployment | Shared hosting hosting.example.test, FTP |

## Features

### Booking calendar (public + account area)

- Calendar view of all courts (squares), day/week navigation, login-gated booking
- Booking modes: **Singles** (2 players, teammate via user search) and **Doubles**
  (4 players, teammates as free text)
- Repeat/subscription bookings (single occurrence vs. whole series editable separately)
- Peak-time player limits, configurable per time window (`PeakLimitService`)
- Daily booking limit per user per court, with a short-booking exception for slots
  starting within 30 minutes
- Club events shown on the calendar (admin-managed, own color/marker)
- Account area: own bookings list, edit profile, change password
- Booking/cancellation confirmation emails

### Admin area (booking)

- User management (create/edit, bulk actions, password reset, `admin`/`assist`/`enabled`
  status, per-`assist`-user `allow.*` permission flags)
- Club event management
- Booking management: list, create/edit/cancel/delete on behalf of users, statistics
- Configuration: general options, behavior settings, squares (courts), test mail sending
- Database page: migration status/repair via `DatabaseSchemaChecker` (no SSH/cron
  needed on hosting.example.test), on-demand DB backup/restore/download

### Members module (club administration)

- Member management: CRUD (no delete — departure via `left_at`), inline edit-in-place
  fields, status tracking (aktiv/ruhend/ausgetreten), CSV import, export
- Fiscal years and fee categories with per-year rates
- Dues (Beiträge): generation per fiscal year, payment tracking, bulk-mark-paid with a
  configurable "bezahlt bis" year, inline editing
- Club roles (Funktionen): named roles (e.g. board members), each taggable with
  multiple mail categories to target them from the mail composer
- Mail: bulk email composer with saved templates, live preview, and dynamic audiences
  (by club role, mail category, or other member filters), per-member send history
- DSGVO (GDPR) settings page
- Dashboard: member/dues KPIs, side-by-side charts, breakdown by fee category

### JSON API & MCP server

- Versioned JSON API (`/api/v1/...`) for bookings, events, members, and dues
  (create/edit only), authenticated with Laravel Sanctum personal access tokens
- Tokens are issued/revoked exclusively by an admin via `/admin/api-tokens` — there is
  no public login endpoint; external integrations get a token handed to them
- The same module also exposes an MCP (Model Context Protocol) server over HTTP at
  `/api/v1/mcp`, so LLM clients can call the same four resources as tools, using the
  same bearer token

See [docs/DESIGN.md](docs/DESIGN.md) for the UI/design system and
[CHANGELOG.md](CHANGELOG.md) for a dated history of changes.

## Security

**No MFA.** Deliberate decision — this is a booking system, not a system with sensitive
financial/personal data behind the login, and login needs to be fast (members book
courts on their phone between games). MFA would add friction disproportionate to the
risk. Compensating controls instead:

- **Rate limiting** (`App\Http\Controllers\Auth\LoginController`): login attempts are
  throttled on three independent keys — `email+ip` (5 attempts), `ip` (25 attempts), and
  `account` (8 attempts) — each triggering a 10-minute lockout via Laravel's
  `RateLimiter`. Every attempt (success/failure/throttled) is logged with IP and user
  agent; a login from a previously-unseen IP for a known user is logged at `notice`.
- **Geo-blocking**: logins can be blocked by country via `BOOKING_BLOCKED_LOGIN_COUNTRIES`
  (comma-separated ISO country codes) in `.env`, resolved via `stevebauman/location`
  (IP geolocation). Empty by default (no blocking) — set per deployment if needed.
- **Risk-based email verification**: a login that looks risky — unusual country vs. the
  user's 30-day history, 2+ recent failed attempts, or impossible travel within 24h —
  requires a 6-digit email code to complete, instead of logging in immediately. Normal
  logins are unaffected; this only kicks in as an actual second factor when something
  looks wrong. Backed by a 30-day `login_history` table (`App\Models\LoginHistory`,
  `App\Services\LoginRiskEvaluator`). Country-based checks fail open when the MaxMind
  GeoLite2 database file is missing (untracked, per-deployment download). Admins can
  view a user's login history (including which flags fired) read-only from their edit
  page.

## Local development

```bash
# Dependencies
composer install
npm install

# Configure .env (DB: booking_local)
cp .env.example .env
php artisan key:generate

# Dev server
php artisan serve       # http://localhost:8001
npm run dev             # Vite HMR

# Tests (via WSL)
php artisan test
```

> **Important:** always run `php`/`composer` via WSL, never directly in PowerShell.

## Deployment (hosting.example.test)

1. Run `npm run build` locally
2. Commit `public/build/`
3. Transfer changed files to the server via FTP

No Node.js on the server — the Vite build must be built and committed locally.

## Layout reference

The stable UI layout is marked with the Git tag **`mobile-view-stable`**.
See [docs/DESIGN.md](docs/DESIGN.md) for details on the design system, and
[CHANGELOG.md](CHANGELOG.md) for a dated summary of notable changes.

```bash
# Reset to the stable layout state (individual files)
git checkout mobile-view-stable -- public/css/booking.css
git checkout mobile-view-stable -- resources/views/components/layout/header.blade.php
```

## Key files

| File | Purpose |
|---|---|
| `public/css/booking.css` | Main CSS incl. mobile-responsive styles |
| `resources/css/calendar-grid.css` | Calendar grid layout |
| `resources/views/components/layout/header.blade.php` | App header (desktop + mobile) |
| `resources/views/components/calendar/grid.blade.php` | Calendar grid |
| `resources/views/components/calendar/modals.blade.php` | Booking modals (Alpine.js) |
| `resources/views/admin/` | Admin area (booking, squares, users, options) |
| `app/Http/Controllers/BookingController.php` | Booking logic (user) |
| `app/Http/Controllers/Admin/BookingController.php` | Booking logic (admin) |
| `app/Services/BookingService.php` | Booking creation/cancellation + confirmation mails |
| `app/Services/ReservationService.php` | Date-range/overlap queries against `bs_reservations` |
| `app/Services/PeakLimitService.php` | Peak-time limiting |
| `app/Services/SquareValidator.php` | Booking authorization/business-rule checks |
| `app/Services/DatabaseSchemaChecker.php` | Schema drift check + repair (no SSH/cron on hosting.example.test) |
| `app/Services/UiLinter.php` + `UiRules.php` | Dev-time Blade linter enforcing allowed colors/classes |

## JSON API & MCP server (Modules/Api)

`Modules/Api` is a self-contained module built with `nwidart/laravel-modules`. It provides a
versioned REST API and an MCP server for external integrations — deliberately without a
public login endpoint; tokens are issued by an admin instead.

> **Why two different "Modules" directories?** `app/Modules/<Name>` (e.g. `app/Modules/Members`)
> and top-level `Modules/<Name>` (e.g. `Modules/Api`) look similar but are unrelated systems
> solving different problems:
>
> - **`app/Modules/*`** is a small homegrown convention (see
>   `app/Console/Commands/MakeModuleCommand.php`, `php artisan make:module`) for
>   self-contained **web-admin UI sections** — own layout, sidebar entry, Blade views,
>   `can()`-gated routes. Used by the Members module.
> - **`Modules/*`** comes from the `nwidart/laravel-modules` package (`php artisan
>   module:make`) and is for **stateless, non-UI feature modules** — no layout, no views,
>   just routes/controllers/services. Used for the API/MCP layer, because it doesn't fit
>   the admin-UI shape `app/Modules/*` is built for.
>
> When adding a new self-contained feature, pick based on whether it needs an admin UI
> (`app/Modules/*`) or not (`Modules/*`) — don't add a third convention.

**Auth**: Laravel Sanctum personal access tokens, issued/revoked at `/admin/api-tokens`
(`App\Http\Controllers\Admin\ApiTokenController`, gated by the `admin.api-tokens`
privilege). Clients send `Authorization: Bearer <token>`.

**REST endpoints** (`/api/v1/...`, create+edit only — no list/show/delete over the API):

| Resource | Endpoints | Privilege |
|---|---|---|
| Booking | `POST/PUT bookings[/{id}]` | `admin.booking` |
| Event | `POST/PUT events[/{id}]` | `admin.event` |
| Member | `POST/PUT members[/{id}]` | `admin.members` |
| Due (Beiträge) | `POST/PUT dues[/{id}]` | `admin.members` |
| — | `GET ping` | any authenticated token |

These reuse the exact same business logic as the admin panel — `App\Services\BookingService`
(conflict checks, repeat/series expansion, owner resolution) and `App\Services\EventService`
(date combination, meta sync) are shared between `app/Http/Controllers/Admin/*` and
`Modules/Api`, not duplicated.

**MCP server**: `POST /api/v1/mcp` (Streamable HTTP transport, via `laravel/mcp`) exposes the
same four resources as MCP tools (`Modules/Api/app/Mcp/Tools`, registered in
`Modules/Api/app/Mcp/Servers/BookingServer.php`). Each tool independently checks the
token user's `admin.*` privilege and returns an MCP error result if missing — the route
itself only requires a valid Sanctum token, since different tools need different privileges.

## Members module

`app/Modules/Members` is a self-contained module (own `MembersServiceProvider`, routes,
migrations, views namespace `members::`, translations namespace `members::admin`) for
club/member administration, separate from the public booking calendar:

| Controller | Purpose |
|---|---|
| `MemberController` | Member CRUD (no delete), inline field editing, CSV import |
| `MemberDueController` | Generate/track yearly dues (Beiträge), bulk-mark-paid |
| `FeeCategoryController` | Fee categories with per-fiscal-year rates |
| `FiscalYearController` | Fiscal year management |
| `ClubRoleController` | Club roles/functions (Funktionen), each taggable with multiple mail categories |
| `MailController` | Bulk email composer with templates and dynamic audiences (role/category-based) |
| `DsgvoSettingsController` | GDPR-related settings |
| `DashboardController` | Member/dues statistics and KPI dashboard |
| `PaymentController`, `MemberExportController` | Payment recording, exports |

Routes are protected by `can:admin.members` middleware. See
[app/Modules/Members/routes.php](app/Modules/Members/routes.php).

### Database schema checker

Shared hosting has no SSH/cron access to run `artisan migrate:status`. Instead,
`App\Services\DatabaseSchemaChecker` keeps a manifest of expected tables/columns
(legacy `bs_*` plus module tables) and is used by an admin health-check page to detect
and repair schema drift. **Whenever you add a table or column via a migration, register
it in the checker's manifest too** — otherwise the drift report will flag it as
untracked (a dedicated test guards against this, see
`tests/Unit/Services/DatabaseSchemaCheckerTest.php`).

## Translations

The application is set up for multiple languages.

- German texts live under `lang/de/`
- English texts live under `lang/en/`
- The file `lang/{locale}/booking.php` is now just a loader
- The actual texts are split by topic under `lang/{locale}/booking/`

Current split:

- `public.php` for public UI texts, navigation, auth, registration, calendar and modals
- `account.php` for the account area
- `admin.php` for the admin UI incl. peak-limit configuration
- `repeat.php` for repeat options
- `mail.php` for email texts
- `validation.php` for validation errors
- `messages.php` for flash and status messages

Important:

- Keep using `__('booking...')` in code
- Always add new texts to `lang/de/booking/...` and `lang/en/booking/...` first
- Do not introduce new hardcoded UI texts in Blade files

## Permission model

No roles table. Controlled via `bs_users.status`:

| Status | Permissions |
|---|---|
| `admin` | Everything |
| `assist` | According to `bs_users_meta` `allow.*` flags |
| `enabled` | Regular bookings |

## Booking modes

- **Singles** (2 players): select teammate via user search (required field)
- **Doubles** (4 players): enter teammates as free text (required field)
