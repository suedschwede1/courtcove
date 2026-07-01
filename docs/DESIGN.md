# Design Documentation

**Layout reference:** Git tag `mobile-view-stable`

**Development language:** English. All code, comments, commit messages and internal docs are written in English — only the `lang/` translation files contain German (and English) UI text.

## Philosophy

Minimal, functional, clear. The design follows the principle: as little as possible, as much as necessary. No decoration, no effects without purpose. Every element earns its place.

No gradients or shadow gimmicks — all backgrounds are flat colors, shadows only where needed (modals, cards). No emoji — status is shown via text badges and color. Minimal animation — only `transition-colors` for hover states (0.1–0.15s), no page transitions. Minimal icons — arrow characters (◄ ►) as text for navigation, no icon library needed.

Minimum font sizes: desktop forms 13px, helper/meta text 11px (never smaller), calendar court names 10px (exception, very compact context).

## Colors

### Primary palette

| Token | Value | Usage |
|---|---|---|
| Primary | `#bf4316` | Buttons, accents, active navigation, court numbers |
| Primary hover | `#9e3412` | Hover state for primary |
| Primary surface | `#fde8e1` | Light background tint (event cells, active day) |
| Primary border | `#bf4316` @ 30% | Outline buttons, focus rings |

### Neutral palette

| Token | Value | Usage |
|---|---|---|
| Text | `#151515` | Main text, headings |
| Text secondary | `#6a6e73` | Labels, helper text, date, icons |
| Text placeholder | `#b8b8b8` | Input placeholders, "until X:XX" |
| Background | `#eae8e2` | App background |
| Background (admin) | `#fafafa` | App background, table headers |
| Card | `#ffffff` | Header card, modals, inputs |
| Border | `#d8d2c8` | Card borders |
| Border (admin) | `#e0e0e0` | Divider lines, input borders |
| Border light | `#f0f0f0` | Subtle divider lines |

### Status colors

| State | Background | Text | Usage |
|---|---|---|---|
| Active/success | `#f0faf0` | `#3e8635` | Active bookings |
| Danger | `#f9f0f0` | `#c9190b` | Cancellations, deletions |
| Info | `#f0f8ff` | `#0066cc` | Subscription bookings, info status |
| Warning | `#fff3cd` | `#f0ab00` | Warnings |

### Calendar cell colors

| Class | Color | Meaning |
|---|---|---|
| `cc-free` | `#EEE` grey / `#ffffff` | Free slot |
| `cc-own` | `#8BB243` green / `#eff6ff` | Own booking |
| `cc-single-future` | `#2596be` blue | Someone else's booking (future) |
| `cc-single` | `#808D96` grey-blue | Past booking |
| `cc-blocked` | — | Blocked slot |
| Event | `#fde8e1` bg, `#7f2010` text | Club event |
| Past | `#f4f4f4` | Not clickable |

### Admin chrome

| Element | Color |
|---|---|
| Sidebar background | `#1b1d21` |
| Active nav item | `rgba(255,255,255,0.1)` + `3px solid #bf4316` left border |
| Inactive nav text | `#a0a0a0` |
| Header background | `#ffffff` |
| Calendar background | `#eae8e2` (beige) |

## Typography

### Fonts

**Red Hat Display** — headings, numbers, court numbers
- Weights: 600 (Semibold), 700 (Bold)
- Usage: page titles, calendar day name, court numbers, modal titles, KPI values

**Red Hat Text** — everything else
- Weights: 400 (Regular), 500 (Medium), 600 (Semibold)
- Usage: labels, body text, buttons, helper text, table data

### Size scale

| Class | Size | Usage |
|---|---|---|
| Display | 22–26px, Bold | Page titles |
| Heading | 18–20px, Bold | Modal titles, section headings |
| Body | 14px, Regular | Form content, table cells |
| Label | 13px, Medium | Form labels |
| Small | 11–12px | Helper text, metadata, "until X:XX" |
| Micro | 10px | Court names under numbers |

### Section headers (form sections)

```css
font-size: 11px;
font-weight: 600;
letter-spacing: 0.08em;
text-transform: uppercase;
color: #6a6e73;
border-bottom: 1px solid #ebebeb;
padding-bottom: 8–10px;
margin-bottom: 14–16px;
```

> **Popup forms**: section headers are **not** used in popup dialogs — space is limited and context comes from the popup title. Fields follow directly with `space-y-4`/`gap-3`.

## Spacing

| Class | Value | Usage |
|---|---|---|
| xs | 4px | Internal button gaps, small icons |
| sm | 8px | Compact spacing, inline elements |
| md | 12–14px | Standard gap between form fields |
| lg | 16–20px | Section spacing, card padding |
| xl | 24px | Main content padding |
| 2xl | 32–40px | Page padding, large sections |

## Header

**File:** `resources/views/components/layout/header.blade.php`
**CSS:** `public/css/booking.css` from line 1350

### Desktop (> 900px)

```
┌─────────────────────────────────────────────────────┐
│ [Logo] Club name       < 30.06.2026 >    [Nav links] │
└─────────────────────────────────────────────────────┘
```

- Background: `#eae8e2` with a white card (`rounded-[6px]`, `shadow`)
- Logo: configurable via `booking.logo_path`
- Title: 18px, bold, `var(--font-display)`
- Nav links: `h-8`, `border`, `rounded-[6px]`, hover `#bf4316`

### Mobile (≤ 900px)

```
┌──────────────────────────────────┐
│ Club name              [👤] [...] │
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

### Time column (left)
- Width: `76px`
- Slot height: `60px`
- Time: Red Hat Display, 17px, bold, `#151515`
- "until X:XX": Red Hat Text, 11px, `#b8b8b8`
- Alternating backgrounds: `#ffffff` | `#fafafa`

### Day column headers
- Day name: Red Hat Display, 13px, bold, `#151515` (active day: `#bf4316`)
- Date: Red Hat Text, 11px, `#6a6e73` (active day: `#bf4316`, font-weight 500)
- Active day: `background: #fff8f6`

### Court numbers (under day headers)
- Number: Red Hat Display, 12px, bold, `#bf4316`
- Name: Red Hat Text, 10–11px, `#bf4316`
- Each court in its own grid column (not flex within a day)

### Booking cells
- Free: `background: #ffffff`, clickable
- Booked: `background: #eff6ff`, text centered (Red Hat Text, 12–13px bold, `#1a3a6b`)
- Event: `background: #fde8e1`, text centered (Red Hat Text, 12px bold, `#7f2010`)
- Past: `background: #f4f4f4`, not clickable
- Border style: `1px solid #e8e8e8` right + bottom

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

### Input fields

```css
height: 36px (normal) | 40px (prominent)
border: 1px solid #c7c7c7
border-radius: 6px
padding: 0 12px
font-size: 14px
font-family: Red Hat Text
background: #ffffff
```

Focus ring: `2px solid #151515` (standard) or `1px solid #d4d4d4` (subtle)

### Select fields

Same as inputs. `cursor: pointer`.

### Textarea

```css
border: 1px solid #c7c7c7
border-radius: 6px
padding: 8px 12px
font-size: 14px
resize: vertical
line-height: 1.5
```

### Buttons

| Variant | Background | Text | Border | Usage |
|---|---|---|---|---|
| Primary | `#bf4316` | `#fff` | none | Main action (save, book) |
| Outline | `#fff` | `#bf4316` | `1px solid #bf4316` | Secondary action |
| Ghost | `transparent` | `#151515` | none | Cancel, navigation |
| Danger | `#c9190b` | `#fff` | none | Delete, destructive actions |

All buttons: `height: 36px`, `border-radius: 6px`, `font-weight: 500–600`, `font-size: 13–14px`

### Cards / tiles

```css
background: #ffffff
border: 1px solid #e8e8e8 (or Tailwind border-zinc-200)
border-radius: 6px (forms/tables) | 8px (modals/dialogs)
box-shadow: 0 2px 8px rgba(0,0,0,0.10) (subtle) | 0 4px 20px rgba(0,0,0,0.15) (prominent)
overflow: hidden
```

KPI tiles (dashboard) additionally have `border-top: 3px solid [color]` (signal color per KPI).

### Tables

Header row:

```css
background: #fafafa
font-size: 11px
font-weight: 600
text-transform: uppercase
letter-spacing: 0.04em
color: #6a6e73
padding: 10px 16–20px
border-bottom: 1px solid #ebebeb
```

Data rows:

```css
padding: 11–12px 16–20px
border-bottom: 1px solid #f5f5f5
font-size: 13px
color: #151515 (primary) / #6a6e73 (secondary)
```

Hover: `background: #fafafa`

Pagination:
- Inactive page: `border-zinc-200`, `bg-white`, `text-zinc-600`
- Active page: `border-color: #bf4316`, `background: #fff3f0`, `color: #bf4316`, `font-weight: 600`

### Status badges

```css
display: inline-flex
border-radius: 4px
padding: 2px 8px
font-size: 11px
font-weight: 600
```

Color combinations from the status colors table above.

## Admin sidebar

```
Width: 200px
Background: #1b1d21 (dark)
Section label: 11px, uppercase, #6a6e73
Nav item inactive: color #a0a0a0, padding 9px 16px 9px 19px
Nav item active: color #fff, font-weight 600, background rgba(255,255,255,0.1), border-left 3px solid #bf4316
```

## Login page

**Layout**: 2-column card on grey background

```
Total: max-width 672px, grid-cols-2
Left: padding 40px 32px, flex-col, justify-center, gap 14px
Right: padding 40px 32px, flex-col, gap 16px, bg #fafafa, border-left
```

Left column:
- "Member area": 11px, uppercase, tracking-widest, `#6a6e73`
- "Sign in": 26px, bold, Red Hat Display, `#151515`
- Description: 13px, `#6a6e73`, line-height 1.65

Right column: form fields (40px height), checkbox, full-width button

## Modal header (admin popups)

```css
background: #1b1d21
height: 52px
padding: 0 24px
display: flex, align-items: center, justify-content: space-between
```

Title: 15px, bold, Red Hat Display, `#ffffff`
Close button: `×`, 20px, `#8a8d90`

## Form sections

Each section has:
1. Section header (micro uppercase, orange or grey, `border-bottom: 1px solid #ebebeb`)
2. Form fields with 14–16px gap
3. Spacing to next section: 20–24px

Field order for date/time: **Date (start) → Time (start) → Date (end) → Time (end)**

## Peak-time feature

Admin can define peak times with a player limit.
**Service:** `app/Services/PeakLimitService.php`
**Admin:** settings under Admin → Configuration

## Blade component reference

All components live under `resources/views/components/`.

| Component | Path | Purpose |
|---|---|---|
| Header | `layout/header.blade.php` | Logo, navigation, action buttons; carries class `no-print` |
| Admin sidebar | `layout/admin-sidebar.blade.php` | Dark sidebar (200px, `#1b1d21`); active item with `border-l-[3px] border-[#bf4316] bg-white/10` |
| Calendar grid | `calendar/grid.blade.php` | Booking table; cell clicks via Alpine `$dispatch('open-booking', {...})` |
| Calendar modals | `calendar/modals.blade.php` | All modals in the booking flow (create booking, cancel, feedback, admin iframe) |

**No Livewire.** Alpine.js is used directly on HTML elements (`x-data`, `x-show`, `x-model`, `x-transition`). There are no registered `<x-*>` component tags.

## Form patterns

### Date + time (two columns)

Standard pattern for all date/time combinations — 2-column grid, order always: date start → time start → date end → time end.

```html
<div class="grid grid-cols-2 gap-3">
    <input type="date" name="date_start" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316]">
    <input type="time" name="time_start" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316]">
    <input type="date" name="date_end" ...>
    <input type="time" name="time_end" ...>
</div>
```

### Form label (Tailwind pattern)

```html
<label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Field name</label>
<input class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316]">
```

### Court selection

Plain `<select>` with no custom styling — same classes as input fields.

### Conditional fields (player count)

Fields for players 3/4 are shown/hidden via Alpine:

```html
<div x-show="quantity == '4'">...</div>
```

### Legacy classes (admin forms)

Older admin views use CSS classes from `booking.css`:

```
.admin-form__row          → grid, 200px label + 1fr field
.admin-form__label        → 12px, bold, #444
.admin-form__field--flex  → flex-row for inline date+time
.admin-form__inline-group → label + input side by side
```

## Error states

### Inline errors (Tailwind views)

Error text directly under the field, no icon:

```html
<input class="... {{ $errors->has('alias') ? 'border-red-400' : 'border-[#d1cbc0]' }}">
@error('alias')
    <p class="text-xs text-red-600">{{ $message }}</p>
@enderror
```

- Error border: `border-red-400` (Tailwind)
- Error text: `text-xs text-red-600`

### Error summary (auth views)

Collected errors as a block above the form:

```html
@if($errors->any())
    <div class="error-message">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
```

CSS class `.error-message`: `background: #a84433`, `color: #fff`, `padding: 8px 10px`, `border-radius: 3px`.

### Focus ring (admin legacy)

Legacy-CSS admin inputs show an orange ring on focus:

```css
border-color: #c75518;
box-shadow: 0 0 0 3px rgba(199, 85, 24, 0.12);
```

## Popup layout pattern

Admin views that appear both as a full page (admin layout) and as an iframe popup:

```blade
@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')

{{-- For popup layout (yields 'content'): --}}
@section('content')
    <div class="ui-page">...</div>
@endsection

{{-- For admin layout (yields 'admin-content'): --}}
@section('admin-content')
    <div class="ui-page">...</div>
@endsection
```

Both sections contain the same content — Laravel only renders the one the active layout yields. `layouts.popup` yields `content`, `layouts.admin` yields `admin-content`.

Controller methods that return popup responses need a union return type:

```php
public function store(Request $request): RedirectResponse|Response
{
    // ...
    if ($request->boolean('popup')) {
        return response('<script>window.parent.location.reload();</script>')
            ->header('Content-Type', 'text/html');
    }
    return redirect()->route(...);
}
```

Forms in popups include `<input type="hidden" name="popup" value="1">`.

### Form includes with `popup_mode`

The `admin.events._form` partial has two layout branches:

```blade
@include('admin.events._form', ['popup_mode' => request('popup')])
```

- `popup_mode = true` → compact Tailwind layout (4-column for date/time, no section header)
- `popup_mode = false/null` → legacy UI classes (`.ui-form-shell`, `.ui-form-divider` etc.)

## Loading and AJAX states

The system uses **no Livewire, no `wire:loading`, no `axios`/`fetch` layer** in the views.

| Situation | Behavior |
|---|---|
| Modal opens | Alpine `x-transition.opacity` — smooth fade-in |
| Feedback after action | Session flash modal, closes itself after **4 seconds** (`setTimeout(() => open = false, 4000)`) |
| Legacy admin forms | Form in a hidden `<iframe>` — parent page reloads after successful submission (`window.location.reload()`) |

There are **no spinners, skeleton screens, or loading bars**. Load times are bridged by fast server responses and minimal JS.

## Email and print styles

### Email templates

Templates under `resources/views/emails/`:

| File | Purpose |
|---|---|
| `booking-confirmed.blade.php` | Booking confirmation with detail table |
| `booking-cancelled.blade.php` | Cancellation notification |
| `user-activated.blade.php` | Account activation |

Emails use **inline CSS** (no Tailwind, no external stylesheet) for maximum mail client compatibility:

```css
body { font-family: Arial, sans-serif; color: #222; font-size: 15px; line-height: 1.6; }
h1   { font-size: 20px; margin-bottom: 4px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
td   { padding: 8px 0; border-bottom: 1px solid #eee; }
.footer { margin-top: 32px; font-size: 13px; color: #888; }
```

Layout: simple `<table>` structure, no multi-column design. No primary orange in emails — neutral colors for maximum readability.

### Print styles

There are **no `@media print` rules** in the CSS files. Print exclusions are controlled exclusively via the `no-print` utility class:

| Element | Class |
|---|---|
| Header | `no-print` |
| Help panels | `no-print` |

Everything without `no-print` gets printed. New elements that aren't print-relevant should get `no-print` added.

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

## Responsive

The current templates are **desktop-first** (from 1024px). Mobile optimization is not part of this redesign for admin views — the Blade templates contain no responsive breakpoints there, which is intentional since the system is primarily used on desktop. The public calendar/header views do have mobile breakpoints (see Header section above).

## Fonts

`var(--font-display)` for headings/titles (configured in `app.css`/Tailwind). See Typography section above for the full font/size scale.
