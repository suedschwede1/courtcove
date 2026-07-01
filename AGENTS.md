# AGENTS.md

Instructions for AI coding agents working in this repository.

## Project

Laravel-based court booking system for a tennis club, replacing an older Zend/Laminas
system (`booking`, a separate repository). Stack: PHP 8.3, Laravel 13, MySQL, Tailwind
CSS + Alpine.js, Vite. See [README.md](README.md) for the full stack table and
[docs/DESIGN.md](docs/DESIGN.md) for the UI design system (colors, spacing, philosophy).

**Development language: English.** All code, comments, commit messages, and internal
docs are written in English — only files under `lang/` contain user-facing German/English
UI text.

## Critical: run PHP/Composer via WSL

This project is developed on Windows, but `php`/`composer`/`artisan` must always be run
**inside WSL**, never directly in PowerShell/cmd. Example:

```bash
wsl php artisan test
wsl composer lint
```

If a command needs a literal path with drive letters on the Windows side, be aware Git
Bash and WSL translate paths differently (`/c/...` vs `/mnt/c/...`) — when in doubt, `cd`
into the repo first and use relative paths.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build      # or `npm run dev` for Vite HMR during local work
```

The app expects a real MySQL database (`booking_local` locally); migrations create the
legacy-style `bs_*` tables (`bs_users`, `bs_squares`, `bs_bookings`, `bs_reservations`,
`bs_options`, …) that mirror the original system's schema.

## Commands

```bash
php artisan test              # PHPUnit test suite
composer analyse               # PHPStan (larastan), level 5, see phpstan.neon
composer lint                  # Pint (code style) + analyse
composer check                 # lint + composer audit + test
vendor/bin/pint                # auto-fix code style
```

Run `composer lint` (or at least `vendor/bin/pint` + `php artisan test`) before
considering a change done. New/changed `.blade.php` files should also be sanity-checked
with `php artisan view:cache` (catches Blade syntax errors) then `php artisan view:clear`.

## Database & permissions

- No roles table. Authorization is driven by `bs_users.status` (`admin` / `assist` /
  `enabled` / …) plus per-permission flags in `bs_users_meta` (key `allow.<privilege>` =
  `'true'`) for `assist` users — see `User::can()`, `syncPrivileges()`, `grantedPrivileges()`.
- Free-form per-user data (firstname, lastname, phone, quote_group, …) lives in
  `bs_users_meta` as key/value rows via `User::getMeta()` / `setMeta()` — prefer this over
  adding new columns/migrations for simple per-user attributes.
- Eloquent models read the real `bs_*` schema; dynamic attributes are documented via
  `@property` docblocks on the models (required for PHPStan/larastan to type-check them).

## Translations

- `__('booking....')` only — never hardcode user-facing text in Blade/PHP.
- German lives under `lang/de/booking/`, English under `lang/en/booking/`. `booking.php`
  in each locale is just a loader that merges the topic files (`admin.php`, `account.php`,
  `mail.php`, `quotes.php`, `validation.php`, `messages.php`, `public.php`, `repeat.php`).
- Add new keys to **both** `lang/de/...` and `lang/en/...` — a missing key falls back to
  German (`app.fallback_locale` is `de`), which can silently leak German text into English
  sessions if you're not careful (guard explicitly by locale when that matters, see
  `BookingController::store()` for an example).
- `app.locale`/`app.fallback_locale` default to `de`.

### Per-installation overrides (never committed)

Several optional, gitignored files let a specific club deployment customize content
without touching tracked files (important since deployments are updated via FTP, not
`git pull` — see Deployment below). Each has a tracked `*.example.php` template:

| Purpose | File (per locale where noted) | Semantics |
|---|---|---|
| Individual translation keys | `lang/{locale}/booking/local.php` | dot-notation keys, merged in |
| Calendar colors | `config/calendar.local.php` | merged over defaults |
| Base quote pool | `lang/{locale}/booking/quotes.local.php` | fully replaces if non-empty |
| Named quote pool (`:name` placeholder) | `lang/{locale}/booking/quotes_named.local.php` | fully replaces if non-empty |
| Private per-user-group quotes | `lang/de/booking/quote_groups.php` (German only) | own namespace, see `App\Services\QuoteGroups` |

When adding a new override mechanism, follow this pattern: gitignore the real file, ship
a documented `.example.php`, and fail gracefully (empty array / default) when the file is
absent — see `App\Services\QuoteGroups` for the reference implementation.

## Deployment (hosting.example.test shared hosting)

No Node.js on the server. `npm run build` must be run locally and `public/build/` **is
committed to git** (unusual, but required here). Deploys are done via FTP of changed
files, not a git-based pipeline — keep this in mind when suggesting deployment steps or
assuming `git pull` happens on the server.

## Testing conventions

- PHPUnit with attributes (`#[Test]`), not the legacy `test_` method-name convention.
- Feature tests extend `Tests\TestCase` and typically use `RefreshDatabase`.
- Prefer a real functional check (`php artisan tinker --execute="..."` or a throwaway
  script run via `php` and deleted afterward) over trusting an untested code path,
  especially for anything touching translations/locale fallback, which fail silently.
- If you find a pre-existing, unrelated failing test while working on something else,
  don't fold the fix into your change — flag it separately (or leave it for the user to
  triage) so unrelated fixes stay reviewable on their own.

## Git

- Commit messages: imperative mood, explain the *why* when it's not obvious from the
  diff (see recent `git log` for tone/format).
- Don't bundle unrelated changes into one commit — if the working tree has other
  in-progress/unrelated modifications when you're about to commit, stage and commit only
  the files that belong to your change.
