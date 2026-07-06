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
