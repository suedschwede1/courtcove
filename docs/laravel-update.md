# Laravel update guide

## Overview

This app runs on **Laravel 13** with **PHP 8.3**. Updates follow this process:

1. Backup & branch
2. Composer update
3. Check & fix breaking changes
4. Tests
5. Deployment

---

## Before the update

### 1. Create a branch

```bash
git checkout -b update/laravel-XX
```

### 2. Save the current state

```powershell
# Commit all changes
git add -A
git commit -m "chore: before Laravel update"
```

### 3. Read the upgrade guide

Read the official upgrade guide before every major update:
- Laravel 13 → 14: https://laravel.com/docs/14.x/upgrade
- Laravel 14 → 15: https://laravel.com/docs/15.x/upgrade

---

## Minor update (e.g. 13.8 → 13.12)

Minor updates are generally safe and don't break the public API.

```powershell
# Adjust composer.json if needed
# "laravel/framework": "^13.8"  →  stays, ^ allows minor updates automatically

# Run the update
wsl composer update laravel/framework

# Tests
wsl php artisan test
```

---

## Major update (e.g. Laravel 13 → 14)

### Step 1: Check the PHP version

Laravel 14 requires at least PHP 8.3 — already satisfied.
New major versions may require higher PHP versions — always check the upgrade guide.

### Step 2: Adjust `composer.json`

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^14.0"
    }
}
```

Also adjust dependencies that require specific Laravel versions (e.g. `laravel/tinker`, `larastan/larastan`).

### Step 3: Run the update

```powershell
wsl composer update
```

Resolve conflicts individually if needed:

```powershell
# Update a single package
wsl composer require laravel/framework:^14.0

# Update everything at once, with dependencies
wsl composer update --with-all-dependencies
```

### Step 4: Check configuration files

Laravel sometimes publishes new config defaults. Diff against the official skeleton:

```powershell
# Which config files changed?
# Manually: https://github.com/laravel/laravel/compare/v13.0.0...v14.0.0
```

Important files that change frequently:
- `config/app.php`
- `bootstrap/app.php`
- `app/Http/Kernel.php` (if present)

### Step 5: Fix breaking changes

Common breaking changes and where they show up:

| Area | What to check |
|---------|-----------|
| Middleware | `bootstrap/app.php` — middleware registration |
| Eloquent | Changed model methods or cast behavior |
| Validation | New or changed validation rules |
| Collections | Changed method signatures |
| Routing | Changed route definitions |

### Step 6: Static analysis

```powershell
wsl vendor/bin/phpstan analyse
```

Fix errors before running tests.

### Step 7: Tests

```powershell
wsl php artisan test
```

All tests must pass (except known pre-existing failures).

### Step 8: Start the app and check manually

```powershell
npm run dev
```

Check critical pages in the browser:
- [ ] Login
- [ ] Calendar view
- [ ] Open booking modal
- [ ] Create a booking (singles + doubles)
- [ ] Admin area

---

## After the update

### Rebuild the frontend

```powershell
npm run build
git add public/build
git commit -m "build: rebuild frontend after Laravel update"
```

### Merge & deployment

```bash
git checkout master
git merge update/laravel-XX
```

Then a normal deployment via FTP to hosting.example.test.

---

## Rollback

If something doesn't work:

```bash
# Discard the branch
git checkout master
git branch -D update/laravel-XX

# Or: reset Composer to the old version
wsl composer require laravel/framework:^13.0
wsl composer update laravel/framework
```

---

## Known limitations

- **No `php artisan migrate`** on the production DB — the app uses the legacy schema `booking_local`
- **Deployment is manual via FTP** — no `git pull` on the server (hosting.example.test shared hosting)
- **PHP via WSL** — all `php`/`composer` commands run via WSL, never directly in PowerShell
