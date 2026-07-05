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
