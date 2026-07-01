# Design Documentation

**Layout reference:** Git tag `mobile-view-stable`

**Development language:** English. All code, comments, commit messages and internal docs are written in English — only the `lang/` translation files contain German (and English) UI text.

## Colors

| Variable | Value | Usage |
|---|---|---|
| Primary | `#bf4316` | Buttons, accents, active navigation |
| Primary dark | `#9e3412` | Hover state for primary |
| Background | `#eae8e2` | App background |
| Card | `#ffffff` | Header card, modals |
| Border | `#d8d2c8` | Card borders |
| Text | `#151515` | Main text |
| Text secondary | `#6a6e73` | Labels, icons |
| Text placeholder | `#b8b8b8` | Input placeholders |

## Header

**File:** `resources/views/components/layout/header.blade.php`
**CSS:** `public/css/booking.css` from line 1350

### Desktop (> 900px)

```
┌─────────────────────────────────────────────────────┐
│ [Logo] Example Club    < 30.06.2026 >    [Nav links] │
└─────────────────────────────────────────────────────┘
```

- Background: `#eae8e2` with a white card (`rounded-[6px]`, `shadow`)
- Logo: configurable via `booking.logo_path`
- Title: 18px, bold, `var(--font-display)`
- Nav links: `h-8`, `border`, `rounded-[6px]`, hover `#bf4316`

### Mobile (≤ 900px)

```
┌──────────────────────────────────┐
│ Example Club          [👤] [...] │
│ < Dienstag 30.06.2026 >          │
└──────────────────────────────────┘
```

- Grid layout: 2 columns (title | icons), date nav in row 2
- Logo: max 44px
- Title: 15px
- Auth icons: login/logout as an SVG icon button
- Admin menu: `...` button opens a dropdown (`is-open`)

## Calendar grid

**File:** `resources/views/components/calendar/grid.blade.php`
**CSS:** `resources/css/calendar-grid.css`

### Cell colors

| Class | Color | Meaning |
|---|---|---|
| `cc-free` | `#EEE` grey | Free slot |
| `cc-own` | `#8BB243` green | Own booking |
| `cc-single-future` | `#2596be` blue | Someone else's booking (future) |
| `cc-single` | `#808D96` grey-blue | Past booking |
| `cc-blocked` | — | Blocked slot |

## Booking modals

**File:** `resources/views/components/calendar/modals.blade.php`

### New booking (regular user)

- Fields: court (readonly), booked for (readonly), date, time, number of players, teammates
- **Singles (2):** teammate field with user autocomplete (`/bookings/players?q=`), required field
- **Doubles (4):** teammate field as free text (e.g. "Müller, Huber, Schmidt"), required field
- Buttons: only **Save** (no Cancel — close via ✕ or Escape)

### Edit booking

- Fields: court, date, time (all readonly), number of players, teammates
- Same autocomplete logic as new booking
- Buttons: **Save** + **Cancel**

### Cancellation (own booking)

- Display: booking details, court, date, time
- Buttons: edit booking, cancel booking (red), cancel

## Form components

```css
.ui-input   /* Standard text field: border, rounded-[6px], h-9, px-3 */
.ui-select  /* Dropdown: same base as ui-input */
.ui-label   /* Label: text-[13px], font-medium */
.ui-btn     /* Button base */
.ui-btn-primary   /* Red (#bf4316), white text */
.ui-btn-ghost     /* Transparent, border */
```

## Peak-time feature

Admin can define peak times with a player limit.
**Service:** `app/Services/PeakLimitService.php`
**Admin:** settings under Admin → Configuration

## Texts and localization

The UI texts are split by topic per language and no longer collected in a single large file.

- Loader: `lang/{locale}/booking.php`
- Partial files: `lang/{locale}/booking/*.php`
- Active languages: `de`, `en`

The existing translation keys deliberately stay stable, for example:

- `booking.nav.login`
- `booking.account.my_bookings`
- `booking.admin.peak_limit.title`

For new UI elements:

- No hardcoded texts in views or components
- Always reuse existing key areas
- Admin texts under `booking/admin.php`, public texts under `booking/public.php`

## Fonts

`var(--font-display)` for headings/titles (configured in `app.css`/Tailwind).
