# Browser tests (Laravel Dusk)

`CalendarTest`, `LoginTest` and `AdminTest` exercise real flows in headless Chromium.
`VisualRegressionTest` (calendar, booking modal, login, account, admin) and
`DesignVisualRegressionTest` (shared controls in the `/_ui` gallery: token colours, 52px
phone targets, no horizontal overflow, dialog Escape/focus return) compare screenshots
with committed, reviewed baselines.

## Environment

- Chromium **and a chromedriver with the same major version** (e.g. snap: `/snap/bin/chromium`
  + `/snap/bin/chromium.chromedriver`). The chromedriver bundled with `laravel/dusk` is
  often the wrong version; start the matching one on port 9515 yourself
  (`chromium.chromedriver --port=9515`) before running the tests. With the snap build,
  point `TMPDIR` at `~/snap/chromium/common/tmp` if the session cannot be created.
- `DUSK_CHROME_BINARY` (see `phpunit.dusk.xml`) must point at the Chromium binary.
- For an externally managed driver set `DUSK_DRIVER_URL` and `DUSK_SKIP_CHROMEDRIVER=1`
  so Dusk does not start the bundled driver.
- The PHP **`gd` extension** (`php -m | grep gd`) — only needed for the pixel comparison
  in `VisualRegressionTest`. Without it those tests are skipped, not failed.
- A SQLite file for the Dusk database (`database/dusk.sqlite`, git-ignored) and the app
  served with the Dusk env on the port from `phpunit.dusk.xml`:

Copy `.env.dusk.example` to `.env.dusk.local` (git-ignored) and start the app with the Dusk environment:

```bash
touch database/dusk.sqlite
wsl php artisan serve --env=dusk.local --host=127.0.0.1 --port=8088
```

The suite runs directly via PHPUnit so `.env` is not swapped by `artisan dusk`:

```bash
wsl php vendor/bin/phpunit -c phpunit.dusk.xml                       # all browser tests
wsl php vendor/bin/phpunit -c phpunit.dusk.xml --filter CalendarTest # one class
```

## Visual baselines

Baselines live in `tests/Browser/baselines/` (list in `baselines/README.md`); fresh actual
screenshots are written to `tests/Browser/screenshots/`. They are **not** generated
automatically. While a baseline is missing (or `gd` is not available) the corresponding
visual test is **skipped** locally with a message; in CI (`CI=true`) it **fails**, and CI
never replaces references. The comparison waits for web fonts and images first.

Create or intentionally refresh baselines (needs a stable, reviewed UI state):

```bash
UPDATE_DUSK_SNAPSHOTS=1 wsl php vendor/bin/phpunit -c phpunit.dusk.xml --filter VisualRegressionTest
```

Then **approve** them: open the PNGs in `tests/Browser/baselines/`, check they show what
you expect (3 courts, the seeded reservations, no error pages), and commit them together
with the change that caused the visual difference. Compare against the baselines with:

```bash
wsl composer dusk:visual
```

Baselines are pixel-sensitive to the browser version and fonts; regenerate them in the
same environment you compare in.

The calendar snapshots use the fixed date 2026-07-01 (past reservations render fine);
the booking-modal test uses a fixed far-future date (2030-07-01) because free slots in
the past are not bookable and clicking them never opens the dialog.
