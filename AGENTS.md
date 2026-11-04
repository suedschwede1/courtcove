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
git config core.hooksPath .githooks   # enables the CHANGELOG pre-commit check below
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
without touching tracked files (important since the Provider deploy ships `git archive HEAD`
over SSH, which never contains them — place them on the server by hand; the deploy wipe
keeps them; see Deployment below). Each has a tracked `*.example.php` template:

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

## Deployment (Provider)

Production is the Provider cPanel host (SSH alias `provider`, app in `~/apps/bookingnew`,
served at `/booking`, so never hard-code root-absolute URLs in views/JS). Deploy from WSL
with `scripts/deploy-provider.sh` (`--dry-run` first; never run it without the user asking).
It ships `git archive HEAD` over ssh and never overwrites the server's `.env` or `storage/`.
Without `--first-deploy` it refuses (before `artisan down`/extract) if
`php artisan migrate:status` fails on the server or there is no artisan yet; with
`--first-deploy` it deploys code + composer install + optimize:clear but runs no `migrate`
and no `artisan up`, and prints the manual steps (`migrate:install`,
`db:baseline-legacy --dry-run`, `db:baseline-legacy`, `migrate --force`), which are never
part of the script. `--skip-build` skips `npm run build` (run it from Windows; WSL Node
is too old) but still requires a committed `public/build/manifest.json` and a clean tree.
Tournament pages are the legacy ZF2 app served on the **same host** (no `turnier.` subdomain):
`public/.htaccess` rewrites an allowlist (`tournament|mixed-tournament|pyramide|draw|service`,
`user/login|logout|password[-reset]`) to `public/zf2-bridge.php`, which runs ZF2's `index.php`
from its own install (`~/apps/zf2`; override with env `ZF2_PUBLIC` or an untracked
`<app>/zf2.path` file, e.g. staging). The deploy script symlinks ZF2's static dirs into
`public/`. Never add paths to that allowlist casually (it exposes ZF2) and never copy the old
install's one-off `public/*.php` scripts into the ZF2 copy. `TOURNAMENT_BASE_URL` stays empty
(same host). No Node.js on the server: `public/build/` **is committed to git**. See README
"Deployment (Provider)" and `docs/superpowers/specs/2026-11-04-provider-laravel-cutover-design.md`.
The old hosting.example.test FTP flow is historical only.

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
- Every feature/behavior-relevant commit needs a [CHANGELOG.md](CHANGELOG.md) entry
  (one line, dated, why/effect not diff — see file header for the exact convention). A
  `pre-commit` hook (`.githooks/pre-commit`, enabled via `git config core.hooksPath
  .githooks`, see Setup above) blocks commits that touch non-doc/non-vendor files
  without also staging a CHANGELOG.md change; bypass only for genuinely
  changelog-irrelevant commits (formatting, test-only, docs-only) with
  `git commit --no-verify`.
