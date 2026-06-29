# Browser visual regression tests

These Dusk tests protect the calendar UI from accidental layout regressions.

Run the app with the Dusk environment first:

```bash
php artisan serve --env=dusk.local --host=127.0.0.1 --port=8088
```

Create or intentionally refresh baselines:

```bash
UPDATE_DUSK_SNAPSHOTS=1 php artisan dusk --filter VisualRegressionTest
```

Check current UI against committed baselines:

```bash
php artisan dusk --filter VisualRegressionTest
```

Baselines live in `tests/Browser/baselines`; fresh actual screenshots are written to `tests/Browser/screenshots`.