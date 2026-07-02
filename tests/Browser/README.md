# Browser visual regression tests

These Dusk tests protect the calendar UI from accidental layout regressions.

Start the app with the Dusk environment first:

```bash
wsl php artisan serve --env=dusk.local --host=127.0.0.1 --port=8088
```

The browser suite runs directly via PHPUnit so `.env` is not swapped by `artisan dusk`.

Create or intentionally refresh baselines:

```bash
UPDATE_DUSK_SNAPSHOTS=1 wsl php vendor/bin/phpunit -c phpunit.dusk.xml --filter VisualRegressionTest
```

Check current UI against committed baselines:

```bash
wsl composer dusk:visual
```

Baselines live in `tests/Browser/baselines`; fresh actual screenshots are written to `tests/Browser/screenshots`.
