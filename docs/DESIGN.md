# Design-Dokumentation

**Layout-Referenz:** Git-Tag `mobile-view-stable`

## Farben

| Variable | Wert | Verwendung |
|---|---|---|
| Primär | `#bf4316` | Buttons, Akzente, aktive Navigation |
| Primär dark | `#9e3412` | Hover-Zustand Primär |
| Hintergrund | `#eae8e2` | App-Hintergrund |
| Karte | `#ffffff` | Header-Karte, Modals |
| Border | `#d8d2c8` | Karten-Rahmen |
| Text | `#151515` | Haupttext |
| Text sekundär | `#6a6e73` | Labels, Icons |
| Text placeholder | `#b8b8b8` | Input-Platzhalter |

## Header

**Datei:** `resources/views/components/layout/header.blade.php`  
**CSS:** `public/css/booking.css` ab Zeile 1350

### Desktop (> 900px)

```
┌─────────────────────────────────────────────────────┐
│ [Logo] Example Club    < 30.06.2026 >    [Nav-Links] │
└─────────────────────────────────────────────────────┘
```

- Hintergrund: `#eae8e2` mit weißer Karte (`rounded-[6px]`, `shadow`)
- Logo: konfigurierbar über `booking.logo_path`
- Titel: 18px, fett, `var(--font-display)`
- Nav-Links: `h-8`, `border`, `rounded-[6px]`, hover `#bf4316`

### Mobile (≤ 900px)

```
┌──────────────────────────────────┐
│ Example Club          [👤] [...] │
│ < Dienstag 30.06.2026 >          │
└──────────────────────────────────┘
```

- Grid-Layout: 2 Spalten (Titel | Icons), Datums-Nav in Zeile 2
- Logo: max 44px
- Titel: 15px
- Auth-Icons: Login/Logout als SVG-Icon-Button
- Admin-Menü: `...`-Button öffnet Dropdown (`is-open`)

## Kalender-Grid

**Datei:** `resources/views/components/calendar/grid.blade.php`  
**CSS:** `resources/css/calendar-grid.css`

### Zellen-Farben

| Klasse | Farbe | Bedeutung |
|---|---|---|
| `cc-free` | `#EEE` grau | Freier Slot |
| `cc-own` | `#8BB243` grün | Eigene Buchung |
| `cc-single-future` | `#2596be` blau | Fremde Buchung (Zukunft) |
| `cc-single` | `#808D96` grau-blau | Vergangene Buchung |
| `cc-blocked` | — | Gesperrter Slot |

## Buchungs-Modals

**Datei:** `resources/views/components/calendar/modals.blade.php`

### Neue Buchung (normaler User)

- Felder: Platz (readonly), Gebucht für (readonly), Datum, Uhrzeit, Spieleranzahl, Mitspieler
- **Einzel (2):** Mitspieler-Feld mit User-Autocomplete (`/bookings/players?q=`), Pflichtfeld
- **Doppel (4):** Mitspieler-Feld als Freitext (z.B. "Müller, Huber, Schmidt"), Pflichtfeld
- Buttons: nur **Speichern** (kein Abbrechen — schließen via ✕ oder Escape)

### Buchung bearbeiten

- Felder: Platz, Datum, Uhrzeit (alle readonly), Spieleranzahl, Mitspieler
- Gleiche Autocomplete-Logik wie Neue Buchung
- Buttons: **Speichern** + **Abbrechen**

### Stornierung (eigene Buchung)

- Anzeige: Buchungsdetails, Platz, Datum, Uhrzeit
- Buttons: Buchung bearbeiten, Buchung stornieren (rot), Abbrechen

## Formular-Komponenten

```css
.ui-input   /* Standard-Textfeld: border, rounded-[6px], h-9, px-3 */
.ui-select  /* Dropdown: gleiche Basis wie ui-input */
.ui-label   /* Label: text-[13px], font-medium */
.ui-btn     /* Button-Basis */
.ui-btn-primary   /* Rot (#bf4316), weiße Schrift */
.ui-btn-ghost     /* Transparent, border */
```

## Stoßzeiten-Feature

Admin kann Stoßzeiten mit Spieler-Limit definieren.  
**Service:** `app/Services/PeakLimitService.php`  
**Admin:** Einstellungen unter Admin → Konfiguration

## Texte und Lokalisierung

Die UI-Texte sind pro Sprache thematisch aufgeteilt und nicht mehr in einer einzelnen großen Datei gesammelt.

- Loader: `lang/{locale}/booking.php`
- Teil-Dateien: `lang/{locale}/booking/*.php`
- Aktive Sprachen: `de`, `en`

Die bestehenden Translation-Keys bleiben bewusst stabil, zum Beispiel:

- `booking.nav.login`
- `booking.account.my_bookings`
- `booking.admin.peak_limit.title`

Für neue UI-Elemente gilt:

- Keine Hardcoded-Texte in Views oder Komponenten
- Immer bestehende Key-Bereiche wiederverwenden
- Admin-Texte unter `booking/admin.php`, öffentliche Texte unter `booking/public.php`

## Schriften

`var(--font-display)` für Überschriften/Titel (konfiguriert in `app.css`/Tailwind).
