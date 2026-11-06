# Design Documentation

**Design system:** "Example Club" (Claude design system artifact, private to the owner). This file is the in-repo copy of its rules.
**Mockups:** design canvas "Example Club – Platzbuchung Redesign" (calendar, booking, account, admin, tournaments; desktop and phone). Screenshots and sources: [`design/README.md`](design/README.md).
**Status:** target design. The current Blade views and `resources/css/app.css` still use the previous look (Red Hat fonts, `#bf4316`, PatternFly blue). Migrate view by view; new or touched views follow this document.

**Development language:** English. All code, comments, commit messages and internal docs are written in English. Only the `lang/` translation files contain German (and English) UI text.

## Principles

1. **Phone first.** Most members book at the court or on the go. Every screen works with a thumb: tap targets at least 44px, primary actions on phones 52px high and at the bottom of the screen. This now applies to admin views as well.
2. **Status is visible without reading.** Free, booked, own booking, event, past: each state has its own surface, not only its own text.
3. **One primary action per screen.** Only that action uses `clay`. Everything else is an outlined white button or a text link.
4. **Real court names.** The court alias comes first (Center Court, Court A, Court B), the number small underneath ("Platz 1").
5. **Flat and quiet.** Flat colors, no gradients. Shadows only on dialogs and bottom sheets. No emoji. Motion limited to `transition-colors` (0.1–0.15s) and the modal fade.

## UI copy (German)

- Address members informally with "du": "Dein nächstes Spiel", "Du stehst auf Platz 9".
- Buttons say exactly what happens: "Verbindlich buchen", "Ergebnis speichern", "Forderung zurückziehen", never "OK" or "Absenden".
- Short dates: "Do, 5. Nov · 17:00 – 18:00". Times always two-digit, ranges with an en dash.
- Eyebrow labels in capitals, one to three words: "MEINE FORDERUNG", "VERWALTUNG".
- No exclamation marks in the interface.

## Color tokens

Every color comes from a token. Light values are the default; dark values are defined for a later dark mode.

| Token | Light | Dark | Usage |
|---|---|---|---|
| `ground` | `#F5F1EA` | `#171513` | Page background (clay-court sand). Never for cards. |
| `surface` | `#FFFFFF` | `#211E1B` | Cards, day columns, dialogs, inputs |
| `surface-sunk` | `#FAF8F4` | `#1B1917` | Table headers, dialog footers |
| `sand` | `#EFE9DF` | `#2C2824` | Neutral chips/badges, court headers in the calendar |
| `line` | `#E3DCD0` | `#3A3631` | Card borders, dividers |
| `line-strong` | `#BDB2A2` | `#6A6259` | Input and select borders |
| `ink` | `#1D1B18` | `#F3EEE6` | Primary text |
| `ink-2` | `#4A443D` | `#CFC6B8` | Secondary text, meta lines |
| `ink-3` | `#6B6359` | `#A79D8F` | Small uppercase labels; only on `ground`/`surface` |
| `chrome` | `#1D1B18` | `#0F0E0D` | Header, admin sidebar, phone menu, highlighted cards |
| `chrome-ink` | `#E7E0D5` | `#E7E0D5` | Menu text on `chrome` |
| `chrome-muted` | `#CFC6B8` | `#A79D8F` | Eyebrows and subtitles on `chrome` |
| `clay` | `#B5441A` | `#E07A4F` | Club accent: primary action, active item, own booking, "du" |
| `clay-hover` | `#8F3412` | `#F0936C` | Hover/pressed of `clay`, links |
| `on-clay` | `#FFFFFF` | `#171513` | Text and icons on `clay` |
| `clay-soft` | `#FBF1EB` | `#3A2419` | Selected options, your own table row |
| `court` | `#1F5A44` | `#5FAE87` | Calendar events, "challengeable", progress |
| `on-court` | `#FFFFFF` | `#171513` | Text on `court` |
| `court-soft` | `#E3F0E8` | `#1E3329` | Badges active/played/confirmed, tournament match cell |
| `court-ink` | `#1F5A44` | `#8FD0AE` | Text on `court-soft` |
| `warn-soft` | `#FBEFD5` | `#3A2E12` | Badges no date/open/pending, notice boxes |
| `warn-ink` | `#7A4E00` | `#F0C46B` | Text on `warn-soft` |
| `danger` | `#9A2B17` | `#F08A72` | Cancel, delete, reset (text or border) |
| `danger-soft` | `#F8E1DC` | `#3D1C15` | Badges cancelled/blocked/lost |
| `cell-free-line` | `#CFC5B5` | `#4A443D` | Dashed border of free calendar cells |
| `cell-booked` | `#E6DFD3` | `#34302B` | Booked cells (other members, subscriptions) |
| `cell-past-a` / `cell-past-b` | `#EDE8DF` / `#F7F4EE` | `#1F1C19` / `#252220` | Hatching of past cells |

The PatternFly blue (`#0066cc`, `#2596be`) and the old green `#8BB243` are retired.

### Status badges

| Group | Fill / text | German labels |
|---|---|---|
| Good / done | `court-soft` / `court-ink` | Aktiv, gespielt, bestätigt, fertig, Laufend |
| Neutral | `sand` / `ink` | geplant, Termin vereinbart, pausiert, Abo |
| Attention | `warn-soft` / `warn-ink` | ohne Termin, Offen, Wartet auf Freigabe |
| Problem | `danger-soft` / `danger` | Storniert, Gesperrt, Abgelehnt, Niederlage |
| You | `clay` / `on-clay` | du |

Badge: 13px bold, `border-radius: 999px`, padding 3px 10px. Always with text, never color alone.

## Typography

Fonts from Google Fonts:
`https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500;600;700&display=swap`

- `--font-display`: `"Barlow Condensed", "Arial Narrow", sans-serif`. Titles, day names, key figures only.
- `--font-body`: `Barlow, system-ui, sans-serif`. Everything else.
- Digits in tables, times and scores: `font-variant-numeric: tabular-nums`.

| Style | Family | Size / line | Weight | Usage |
|---|---|---|---|---|
| `display-xl` | display | 40 / 40 | 700 | Page title desktop (calendar uses 32) |
| `display-l` | display | 32 / 34 | 700 | Page title phone, dialog title |
| `display-m` | display | 24 / 26 | 700 | Card title, phone header |
| `display-s` | display | 20 / 22 | 700 | Subtitle, calendar day name |
| `numeral` | display | 40 / 40 | 700 | Key figures, rankings |
| `body-l` | body | 17 / 24 | 500 | Intro text, large phone buttons |
| `body` | body | 15 / 22 | 400 | Default text, desktop inputs |
| `body-strong` | body | 15 / 22 | 700 | Names, row headings |
| `body-s` | body | 13 / 18 | 400 | Meta lines |
| `label` | body | 12 / 16 | 700, 0.1em, uppercase | Eyebrows, table headers |
| `button` | body | 15 / 20 | 700 | Button labels |

Minimum size: 11px (second line in calendar cells). Inputs on phones use 17px to prevent iOS zoom.

## Spacing, radius, sizes

| Token | Value | Usage |
|---|---|---|
| `space-1` | 4px | Gap between calendar cells |
| `space-2` | 8px | Button groups, chip rows |
| `space-3` | 12px | Small card padding, card gap on phones |
| `space-4` | 16px | Phone page gutter, card padding |
| `space-5` | 20px | Large card padding, column gap |
| `space-6` | 24px | Desktop page gutter |
| `space-8` | 32px | Admin content gutter |
| `radius-sm` | 6px | Calendar cells (desktop) |
| `radius-md` | 8px | Buttons, inputs (desktop) |
| `radius-lg` | 12px | Cards; buttons and inputs on phones |
| `radius-xl` | 18px | Top edge of bottom sheets |
| `radius-pill` | 999px | Badges, filter chips |
| `touch-min` | 44px | Minimum height of anything tappable |
| `control-h` | 46px | Inputs and buttons (desktop) |
| `control-h-mobile` | 52px | Inputs and primary buttons (phone) |
| `cell-h` | 36px | Calendar cell (desktop) |
| `cell-h-mobile` | 48px | Calendar cell (phone) |
| `shadow-dialog` | `0 20px 50px rgba(0,0,0,0.25)` | Desktop dialog |
| `shadow-sheet` | `0 -10px 30px rgba(0,0,0,0.2)` | Phone bottom sheet |

Cards: `surface`, `1px solid line`, `radius-lg`, no shadow. Lay out siblings with flex/grid `gap`, not margins.

## Layout

### Header (public and member views)

**File:** `resources/views/components/layout/header.blade.php`

- Full-width bar on `chrome`, content max 1400px, padding 14px 24px.
- Left: logo in a 44px circle on `clay` (from `booking.logo_path`), club name in `display` 24px, "PLATZBUCHUNG" eyebrow.
- Main menu: Kalender, Meine Buchungen, Vereinsmeisterschaft (only while running), Forderungspyramide, Mixed-/Fun-Turnier, Admin (permission). Items `padding: 10px 14px`, `color: chrome-ink`; active item `background: rgba(255,255,255,0.10)`, white, 600, `aria-current="page"`.
- Right: admin mode switch, language DE/EN, avatar (links to account).
- The date navigation moves out of the header into the page toolbar (see Calendar).

**Phone:** logo, section title and a 44px ☰ button. The button opens a full-screen menu on `chrome` (all menu items with icons, admin mode, language, log out). **No bottom navigation bar.**

### Module tabs

Tournament modules and admin sub-areas use tabs below the page title: `padding: 12px 16px`, 3px bottom border, active tab `border-color: clay`, `ink`, 700. They scroll horizontally on narrow screens, never wrap. On phones they become a row of pill chips.

### Admin

**File:** `resources/views/components/layout/admin-sidebar.blade.php`

- Sidebar on `chrome`, `flex: 1 1 240px`; logo + "VERWALTUNG" eyebrow at the top.
- Groups with `label` headings in `#A79D8F`: **Betrieb** (Übersicht, Buchungen, Veranstaltungen, Statistik), **Mitglieder** (Mitglieder, Anmeldeverlauf), **Turniere** (Forderungspyramide, Meisterschaft, Auslosungen, Mixed-Turniere), **Einstellungen** (Plätze, Texte, Verhalten, Datenbank, API-Tokens, Testmail).
- Items 40px high, `radius-md`; active item filled `clay` with `on-clay` text (replaces the old left-border marker).
- Content `flex: 999 1 560px`, padding 28px 32px. The row uses `flex-wrap: wrap`, so on phones the sidebar becomes a slide-in menu behind a ☰ button.
- The inline `onmouseover` styles in the current sidebar are replaced by classes.

## Calendar

**Files:** `resources/views/calendar/index.blade.php`, `resources/views/components/calendar/grid.blade.php`, `resources/css/calendar-grid.css`, colors in `config/calendar.php`

**Goal:** the full 08:00–19:00 grid (12 rows) is visible without vertical scrolling.

### Toolbar
- Date as `h1` (`display`, 32px), e.g. "Mittwoch, 4. November 2026". No eyebrow above it.
- Right: previous day (44px icon button), "Heute", date field, next day; then text buttons "Infos" and "Hinweise".
- Legend row below: Frei, Meine Buchung, Belegt, Veranstaltung, Turnierspiel, Vergangen (13px, swatch 18×14).

### Desktop grid
- Time column 60px: hour in 15px bold tabular, "bis 09" 11px `ink-3`, right-aligned, 36px high.
- One card per day (`surface`, `line`, `radius-lg`, padding 8px), three days side by side, gap 14px; wrapper `overflow-x: auto` with `min-width: 980px`.
- Day header 36px: day name `display` 22px + date 13px `ink-3` on one line; "HEUTE" pill (`#F8E4D9` / `#8F3412`) on today.
- Court header 34px per court on `ground`: alias 14px bold, "Platz 1" 11px `ink-3` beneath.
- Rows: 3 cells, gap 4px, cell height 36px.

### Cell states

| State | Surface | Text | Element |
|---|---|---|---|
| Free | `surface`, `1px dashed cell-free-line` | none | `<a href>` with `aria-label` "Court A, 17:00 Uhr buchen" |
| My booking (`cc-own`) | `clay` | `on-clay` | link to own booking / cancel dialog |
| Booked / subscription (`cc-single-future`) | `cell-booked` | `ink` | span (link for admins) |
| Event | `court`, joined across hours | `on-court` | first hour 40px with `margin-bottom: -4px`, following hours plain `court` |
| Tournament match (`cc-turnier`) | `court-soft` + `box-shadow: inset 0 0 0 1px court` | `court-ink` | span |
| Past (`slot-cell--past`) | `repeating-linear-gradient(135deg, cell-past-a 0 4px, cell-past-b 4px 8px)` | none | span |

Cell text is **centered horizontally and vertically**: padding 4px 10px, line 1 name 13px bold, line 2 detail 11px, `line-height: 1.1`, single lines with ellipsis. Use `box-shadow` instead of `border` for outlines so text stays aligned across states.

### Phone
- One day; header with ‹ day › buttons (44px) and "4. November 2026 · Heute".
- Sticky court header row (alias + number), time column 52px, cells 48px.
- Tapping a free cell opens the booking bottom sheet.

## Booking

**File:** `resources/views/components/calendar/modals.blade.php`

### Desktop dialog
- Width ~500px, `surface`, `radius-lg` 14px, `shadow-dialog`, backdrop `rgba(29,27,24,0.55)`.
- Header: eyebrow "NEUE BUCHUNG", title "Court A buchen" (`display` 32px), close button 44px.
- Summary tiles on `ground`: Datum, Zeit, Platz.
- Spielart as option tiles: Einzel (2 Spieler) / Doppel (4 Spieler). Selected: `2px solid clay` on `clay-soft`.
- Teammate field with member autocomplete (`/bookings/players?q=`); doubles show three fields.
- Checkbox "Bestätigung per E-Mail an mich senden".
- Footer on `surface-sunk`: "Abbrechen" (secondary) and "Verbindlich buchen" (primary).

### Phone bottom sheet
- Anchored to the bottom, `radius-xl` top corners, grab handle 44×5px, `shadow-sheet`.
- Same content plus time options (1 or 2 hours) as tiles and "Zuletzt gespielt mit" chips.
- Full-width primary button 56px: "Verbindlich buchen · 17:00 – 18:00", "Abbrechen" text button beneath.
- Success state in the same sheet: check icon on `court-soft`, "Platz gebucht", buttons "Zum Kalender" and "Meine Buchungen ansehen".

### Cancel own booking
Booking details, then "Buchung bearbeiten" (secondary), "Stornieren" (danger) and "Abbrechen".

## Components

### Buttons

| Variant | Background | Text | Border | Usage |
|---|---|---|---|---|
| Primary | `clay` | `on-clay` | none | Book, save, confirm |
| Secondary | `surface` | `ink` | `1px solid line` | Cancel, filter, side actions |
| Dark | `chrome` | `#fff` | none | Form actions in admin cards |
| Danger | `surface` | `danger` | `1px solid danger-soft` | Cancel booking, delete, withdraw |
| Text | transparent | `ink-2` | none | Infos, Hinweise, Abbrechen in sheets |

Height `control-h` (46px), `radius-md`, `button` style. On phones `control-h-mobile`, `radius-lg`, full width. A link styled as a button is an `<a>` with the same classes, never a button inside a link.

### Inputs and selects
- Label above the field, 14px bold, linked via `for`/`id`; help text 13px `ink-3`.
- Height 46px (phone 52px), `1px solid line-strong`, `radius-md`, padding 0 12px, `body` 15px.
- Focus: `outline: 2px solid ink; outline-offset: 1px`.
- Two to five choices are option tiles, not a dropdown.
- Score inputs: `inputmode="numeric"`, centered, 20px bold.
- Errors: text 13px `danger` under the field, border `danger`. The old `.error-message` block (`#a84433`) becomes a notice box on `danger-soft` with `danger` text.

### Tables
- Header row on `surface-sunk`, `label` style (12px, 700, uppercase, 0.08em), `ink-3`, padding 10px 18px.
- Rows: padding 10–12px 18px, `border-top: 1px solid #F1ECE3`, 14–15px.
- Your own row: `clay-soft`; leader/qualified row: `court-soft`.
- Wide tables sit in an `overflow-x: auto` box. On phones, member and booking lists become cards.
- Pagination: 40px squares, active page filled `chrome` with white text.

### Cards and key figures
- Card: `surface`, `1px solid line`, `radius-lg`, padding 16–20px, title `display-m`.
- Key figure tile: `label` eyebrow, value in `numeral`, meta line 14px `ink-2`. The old 3px colored top border is dropped.
- Highlighted card (next match, my challenge): `chrome` background with white text.

### Match card (tournaments)
- Top row: context `label` (Gruppe B · Spiel 4) and badge.
- Players stacked, winner bold; score right-aligned in tabular figures, sets separated by spaces ("6 4").
- Meta line: date · time · court alias, or "noch kein Termin".
- Action by status: no date → "Termin eintragen" (primary), scheduled → "Ergebnis eintragen" (secondary), played → none.
- Mixed: "A & B" vs "C & D" centered with "VS"; result buttons 4:0 / 3:1 / 2:2 / 1:3 / 0:4.

### Icons
Inline SVG, 24px grid, 2px stroke, `stroke="currentColor"`, round caps. Icon-only buttons need an `aria-label`. No icon library, no emoji, no arrow characters as icons.

## Login

- Two halves (`flex-wrap`): left a `clay` panel with court lines drawn as borders (white at 35–55% opacity), logo and "Dein Platz. Deine Zeit."; right the form, max 420px.
- Fields 50px, primary button "Anmelden" in `chrome`, full width, 52px.
- "Passwort vergessen?" link next to the password label; registration hint in a card below.
- Phone: clay panel shrinks to a 230px band at the top.

## Tournament modules (ZF2, to be ported)

Forderungspyramide, Vereinsmeisterschaft, Mixed-/Fun-Turnier and Auslosung use the same tokens and components. Their own CSS prefixes (`pyr-`, `tn-`, `mx-`) map to the tokens above; their "Desktop / Handy" view switch is removed because every page is responsive.

| Old meaning | Old color | New token |
|---|---|---|
| Primary / active tab | `#2596be` | `clay` |
| Forderbar, gespielt, done | `#8BB243`, `#eff5e8` | `court` / `court-soft` |
| Ohne Termin, offen | `#fdf7e7` / `#795600` | `warn-soft` / `warn-ink` |
| Geplant | `#e7f4f9` / `#0f6f8e` | `sand` / `ink` |
| Danger | `#c0392b` | `danger` |
| Header | `#212427` | `chrome` |

## Accessibility

- Text contrast at least 4.5:1 (large numbers 3:1); `ink-3` only on `ground`/`surface`.
- Real `<button>`, `<a href>`, `<label>`, also inside calendar cells.
- State is never shown through color alone.
- Visible focus on every control: `2px solid ink`, offset 2px.
- Respect `prefers-reduced-motion` for the modal and sheet transitions.

## Implementation notes

### Tailwind tokens (`resources/css/app.css`)
Replace the `--color-accent-*` scale, the Red Hat font tokens and the `--ui-*` block with the tokens above in the `@theme` block (`--color-clay`, `--color-court`, `--font-display`, `--font-body`, …). Replace the Red Hat `<link>` in `layouts/app.blade.php` with the Barlow link. Prefer token classes (`bg-clay`, `text-ink-2`) over arbitrary values like `bg-[#bf4316]`.

### Calendar colors (`config/calendar.php`)
Map `free`, `own`, `single_future`, `series`, `past` and `event` to the cell tokens above; the CSS variables in `layouts/app.blade.php` (`--cal-*`) keep their names.

### Blade component reference

All components live under `resources/views/components/`.

| Component | Path | Purpose |
|---|---|---|
| Header | `layout/header.blade.php` | Logo, navigation, action buttons; carries class `no-print` |
| Admin sidebar | `layout/admin-sidebar.blade.php` | Dark sidebar with grouped navigation |
| Calendar grid | `calendar/grid.blade.php` | Booking table; cell clicks via Alpine `$dispatch('open-booking', {...})` |
| Calendar modals | `calendar/modals.blade.php` | All modals in the booking flow (create booking, cancel, feedback, admin iframe) |

**No Livewire.** Alpine.js is used directly on HTML elements (`x-data`, `x-show`, `x-model`, `x-transition`). Shared Blade components live under `components/ui/` (`x-ui.button`, `x-ui.field`, `x-ui.input`, `x-ui.modal`, …); the dev gallery `/_ui` shows them (local, testing and Dusk environments only). See [DESIGN-ASSURANCE.md](DESIGN-ASSURANCE.md) for the enforced checks, the legacy inventory and screenshot review.

### Popup layout pattern

Admin views that appear both as a full page (admin layout) and as an iframe popup:

```blade
@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')

@section('content')
    <div class="ui-page">...</div>
@endsection

@section('admin-content')
    <div class="ui-page">...</div>
@endsection
```

Both sections contain the same content; Laravel renders the one the active layout yields. Controller methods that answer popups use a union return type (`RedirectResponse|Response`) and return `<script>window.parent.location.reload();</script>` when `popup` is set. Forms in popups include `<input type="hidden" name="popup" value="1">`. The popup header uses `chrome` with a `display-m` white title and a 44px close button.

### Date and time fields

Order is always: date start → time start → date end → time end, in a 2-column grid (`grid grid-cols-2 gap-3`). Player fields 3 and 4 are toggled with `x-show="quantity == '4'"`.

### Members module

The Members module (`app/Modules/Members`, views under `members::`) uses the same tokens plus two Alpine patterns: inline edit-in-place in list tables (`PATCH` to `*.update-field`, brief checkmark, Escape cancels) and the mail composer with templates, dynamic audiences (`MailController::audienceDefinitions()`) and a server-rendered preview.

### Loading and feedback

No Livewire, no `wire:loading`, no axios layer. Modals fade in with `x-transition.opacity`. Session feedback appears as a modal that closes after 4 seconds. Legacy admin forms post into a hidden iframe and reload the parent. No spinners or skeletons.

### Emails and print

Emails under `resources/views/emails/` use inline CSS only (Arial, `#222`, 15px, simple tables) and no accent colors. Print exclusions use the `no-print` class only; there are no `@media print` rules.

### Texts and localization

- Loader: `lang/{locale}/booking.php`, partial files `lang/{locale}/booking/*.php`, languages `de` and `en`.
- Translation keys stay stable (e.g. `booking.nav.login`, `booking.account.my_bookings`).
- No hardcoded texts in views; admin texts in `booking/admin.php`, public texts in `booking/public.php`.

### Peak-time feature

Admins define peak times with a player limit. Service `app/Services/PeakLimitService.php`, settings under Admin → Configuration.
