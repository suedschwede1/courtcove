# Design assurance

`docs/DESIGN.md` defines the target. Enforcement has three independent layers:
source checks, rendered regression tests, and a visual/accessibility review. A passing
source check does not prove layout, contrast, sensible copy, or an accurate mockup match.

## Migration status

| Area | Status | Evidence |
|---|---|---|
| `resources/views`, `resources/css`, module views | Migrated (restyle) | Strict source checks: no legacy exceptions allowed (`ui-design.migrated`) |
| `components/ui/*`, gallery `/_ui` | Shared component foundation | Strict source checks, desktop/phone/dialog references |
| `public/css/booking.css` | Legacy sheet, still loaded | Its findings are inventoried in `ui-design-baseline.json` and may only shrink |
| Visual references | Pending | Reviewed PNGs of the restyled UI still have to be captured (see below) |

When a legacy area is migrated, remove its legacy styles, run
`php artisan lint:ui --prune-baseline`, and review its screenshots against the mockup.

## Daily checks

On Windows run PHP and Composer through WSL, from the project directory:

```bash
wsl composer check:design
npm run build
wsl php artisan view:cache
wsl php artisan view:clear
```

`check:design` runs the source check plus the design enforcement tests
(`phpunit.design.xml`). The source check is also part of `composer lint`. The GitHub
workflow `Design checks` runs for pull requests and main/master pushes. After pushing
the workflow, configure its `Design contract` job as a required check in the repository
branch rules; a local file cannot enable server-side rules.

The linter (`app/Services/UiRules.php`) rejects literal colors outside the token sheet,
stock Tailwind palette colors, undersized controls/type, foreign fonts, inline styling,
character icons and emoji. Email templates keep their separate inline-CSS contract.
These are source heuristics, not a CSS cascade or DOM/accessibility validator.

Allowed colour forms:

- Views: token classes (`bg-clay`, `text-ink-2`) or `var(--color-*)`, e.g.
  `bg-[var(--color-clay)]`. Literal colours are findings wherever they appear in a view
  (class lists incl. Tailwind arbitrary values, `style` attributes, `<style>` blocks,
  `:style` / `x-bind:style` bindings): hex (`#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`,
  `CLR-01`), Tailwind palette classes (`CLR-02`) and colour functions with literal
  channels such as `rgb()`, `rgba()`, `hsl()`, `hsla()`, `hwb()`, `lab()`, `lch()`,
  `oklab()`, `oklch()` (`CLR-03`).
- CSS: `var(--color-*)`, and colour functions whose arguments reference a token, e.g.
  `rgb(var(--color-x-rgb) / 0.5)` or `color-mix(in srgb, var(--color-surface) 80%, transparent)`.
  Literal hex values (`CSS-01`) and literal colour functions (`CSS-02`) are only allowed
  in `resources/css/app.css`: token hex values anywhere there, colour functions only as
  custom property declarations (`--shadow-dialog: …`, `--ds-backdrop: …`).
The pre-commit hook (enable with `git config core.hooksPath .githooks`) also runs the
source check for staged UI paths. It checks the working tree for fast local feedback;
CI checks the actual commit, so partial staging cannot bypass the remote check.

### Legacy inventory

`ui-design-baseline.json` records legacy findings by file, rule, normalized source line
and occurrence count. Line shifts alone do not invalidate an exception. New source,
additional occurrences and any violation in a migrated path fail with a nonzero exit.

- `php artisan lint:ui --summary`: blocking and total counts.
- `php artisan lint:ui --strict`: report all legacy debt as errors.
- `php artisan lint:ui --rule=CLR`: inspect one rule category.
- `php artisan lint:ui --prune-baseline`: remove fixed debt; never accepts new findings.
- `--write-baseline` is only for initial setup and refuses to overwrite an inventory.

Baseline additions are code-review decisions and require an explanation. Never delete
and recreate the file to turn a failing change green.

## Built assets

The server has no Node.js, so `public/build` is committed. The CI job rebuilds the
assets and fails when the committed tree differs from a fresh build.

## Screenshot review

See `tests/Browser/README.md` for setup and commands. The reference set covers the
calendar, booking dialog, login, account and admin at defined widths, plus the shared
controls in the `/_ui` gallery on desktop/phone and its phone dialog. These are viewport
screenshots; also inspect below the fold when changing long forms/tables.

To intentionally create/update references, run with `UPDATE_DUSK_SNAPSHOTS=1` locally,
inspect every resulting PNG and commit the relevant references with the UI change.
CI refuses snapshot replacement and fails on missing references; locally a missing
reference skips the test with a message. The `Visual references` workflow job is
manual (`workflow_dispatch`) until the restyled references are committed.

## Acceptance checklist for each changed page

- Compare to the target mockup at phone and desktop widths; check long/empty content.
- Check normal, disabled, error, success and empty states relevant to the change.
- Verify Barlow, token colors, spacing/radii, court aliases and a single primary action.
- Touch targets are at least 44px; phone primary controls are 52px; phone inputs use 17px.
- Every field has a label; errors are associated; status has text as well as color.
- Keyboard focus is visible; dialogs retain focus, close with Escape and return focus.
- No horizontal overflow, covered action, clipped label or unintended page jump.
- Check reduced motion and text contrast (4.5:1, or 3:1 for large text).
- Attach before/after screenshots and record any intentional visual-reference change.
