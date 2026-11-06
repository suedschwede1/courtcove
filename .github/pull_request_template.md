## Change

Describe the user-visible behavior and the affected screens.

## Validation

- [ ] `composer check:design` passes (PHP/Composer via WSL on Windows).
- [ ] UI changes use the tokens and `x-ui.*` components in `docs/DESIGN.md`.
- [ ] Desktop and phone screenshots are attached for changed screens, including error/empty states.
- [ ] Touch targets, keyboard focus, Escape behavior, labels and overflow were checked.
- [ ] Visual references were compared; changed references were inspected rather than blindly regenerated.
- [ ] `ui-design-baseline.json` only shrinks (`php artisan lint:ui --prune-baseline`); no new legacy exceptions.

For non-UI changes, explain which visual checks do not apply.
