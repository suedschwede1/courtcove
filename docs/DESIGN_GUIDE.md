# Design Guide — Example Tennis Club Tennis-Buchungssystem

## Philosophie

**Minimal, funktional, klar.** Das Design folgt dem Grundsatz: so wenig wie
möglich, so viel wie nötig. Kein Dekor, keine Effekte ohne Zweck. Jedes
Element verdient seinen Platz.

---

## Farben

### Primärpalette

| Token | Wert | Verwendung |
|-------|------|-----------|
| Club-Orange | `#bf4316` | Primäre Aktionen, Platznummern, aktive Zustände |
| Orange Hover | `#9e3412` | Hover auf primären Buttons |
| Orange Surface | `#fde8e1` | Leichter Hintergrundton (Veranstaltungszellen, aktiver Tag) |
| Orange Border | `#bf4316` @ 30% | Outline-Buttons, Fokus-Ringe |

### Neutrale Palette

| Token | Wert | Verwendung |
|-------|------|-----------|
| Text Primary | `#151515` | Haupttext, Überschriften |
| Text Secondary | `#6a6e73` | Labels, Hilfstexte, Datum |
| Text Muted | `#b8b8b8` | "bis X:XX Uhr", Platzhalter |
| Background | `#fafafa` | App-Hintergrund, Tabellenköpfe |
| Surface | `#ffffff` | Cards, Inputs, Modals |
| Border | `#e0e0e0` | Trennlinien, Input-Rahmen |
| Border Light | `#f0f0f0` | Subtile Trennlinien |

### Status-Farben

| Zustand | Hintergrund | Text | Verwendung |
|---------|-------------|------|-----------|
| Aktiv/Erfolg | `#f0faf0` | `#3e8635` | Aktive Buchungen |
| Gefahr | `#f9f0f0` | `#c9190b` | Stornierungen, Löschen |
| Info | `#f0f8ff` | `#0066cc` | Abo-Buchungen, Info-Status |
| Warnung | `#fff3cd` | `#f0ab00` | Warnungen |

### Kalender-Zellen

| Zustand | Hintergrund |
|---------|-------------|
| Frei (buchbar) | `#ffffff` |
| Gebucht (eigene) | `#eff6ff` |
| Veranstaltung | `#fde8e1` |
| Vergangen | `#f4f4f4` |

### Admin-Chrome

| Element | Farbe |
|---------|-------|
| Sidebar Hintergrund | `#1b1d21` |
| Aktiver Nav-Eintrag | `rgba(255,255,255,0.1)` + `3px solid #bf4316` links |
| Inaktiver Nav-Text | `#a0a0a0` |
| Header Hintergrund | `#ffffff` |
| Kalender-Hintergrund | `#eae8e2` (Beige) |

---

## Typografie

### Schriftarten

**Red Hat Display** — Überschriften, Zahlen, Platznummern
- Weights: 600 (Semibold), 700 (Bold)
- Einsatz: Seitentitel, Kalender-Tagesname, Platznummern, Modal-Titel, KPI-Werte

**Red Hat Text** — Alles andere
- Weights: 400 (Regular), 500 (Medium), 600 (Semibold)
- Einsatz: Labels, Fließtext, Buttons, Hilfstexte, Tabellendaten

### Größen-Skala

| Klasse | Größe | Verwendung |
|--------|-------|-----------|
| Display | 22–26px, Bold | Seitentitel |
| Heading | 18–20px, Bold | Modal-Titel, Sektionsüberschriften |
| Body | 14px, Regular | Formularinhalte, Tabellenzellen |
| Label | 13px, Medium | Formular-Labels |
| Small | 11–12px | Hilfstexte, Metadaten, "bis X:XX Uhr" |
| Micro | 10px | Platznamen unter Nummern |

### Section-Headers (Formular-Sektionen)

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

> **Popup-Formulare**: Section-Header werden in Popup-Dialogen **nicht** verwendet —
> der Platz ist begrenzt und der Kontext ergibt sich aus dem Popup-Titel.
> Felder folgen direkt aufeinander mit `space-y-4`/`gap-3`.

---

## Abstände

| Klasse | Wert | Verwendung |
|--------|------|-----------|
| xs | 4px | Interne Button-Gaps, kleine Icons |
| sm | 8px | Kompakte Abstände, Inline-Elemente |
| md | 12–14px | Standard-Gap zwischen Formularfeldern |
| lg | 16–20px | Sektionsabstände, Karten-Padding |
| xl | 24px | Haupt-Content-Padding |
| 2xl | 32–40px | Seiten-Padding, große Sektionen |

---

## Formular-Komponenten

### Input-Felder

```css
height: 36px (normal) | 40px (prominent)
border: 1px solid #c7c7c7
border-radius: 6px
padding: 0 12px
font-size: 14px
font-family: Red Hat Text
background: #ffffff
```

Focus-Ring: `2px solid #151515` (Standard) oder `1px solid #d4d4d4` (subtil)

### Select-Felder

Identisch zu Inputs. `cursor: pointer`.

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

| Variante | Hintergrund | Text | Border | Verwendung |
|----------|-------------|------|--------|-----------|
| Primary | `#bf4316` | `#fff` | none | Hauptaktion (Speichern, Buchen) |
| Outline | `#fff` | `#bf4316` | `1px solid #bf4316` | Sekundäraktion |
| Ghost | `transparent` | `#151515` | none | Abbrechen, Navigation |
| Danger | `#c9190b` | `#fff` | none | Löschen, destruktive Aktionen |

Alle Buttons: `height: 36px`, `border-radius: 6px`, `font-weight: 500–600`, `font-size: 13–14px`

---

## Cards / Kacheln

```css
background: #ffffff
border: 1px solid #e8e8e8 (oder Tailwind border-zinc-200)
border-radius: 6px (Formulare/Tabellen) | 8px (Modals/Dialoge)
box-shadow: 0 2px 8px rgba(0,0,0,0.10) (subtil) | 0 4px 20px rgba(0,0,0,0.15) (prominent)
overflow: hidden
```

### KPI-Kacheln (Dashboard)

Zusätzlich: `border-top: 3px solid [Farbe]` (Signal-Farbe je KPI)

---

## Tabellen

### Kopfzeile

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

### Datenzeilen

```css
padding: 11–12px 16–20px
border-bottom: 1px solid #f5f5f5
font-size: 13px
color: #151515 (primär) / #6a6e73 (sekundär)
```

Hover: `background: #fafafa`

### Paginierung

- Inaktive Seite: `border-zinc-200`, `bg-white`, `text-zinc-600`
- Aktive Seite: `border-color: #bf4316`, `background: #fff3f0`, `color: #bf4316`, `font-weight: 600`

---

## Status-Badges

```css
display: inline-flex
border-radius: 4px
padding: 2px 8px
font-size: 11px
font-weight: 600
```

Farbkombinationen aus Status-Farben-Tabelle oben.

---

## Kalender-Grid

### Zeitspalte (links)
- Breite: `76px`
- Slot-Höhe: `60px`
- Uhrzeit: Red Hat Display, 17px, bold, `#151515`
- "bis X:XX Uhr": Red Hat Text, 11px, `#b8b8b8`
- Alternierende Hintergründe: `#ffffff` | `#fafafa`

### Tages-Spaltenköpfe
- Tagesname: Red Hat Display, 13px, bold, `#151515` (aktiver Tag: `#bf4316`)
- Datum: Red Hat Text, 11px, `#6a6e73` (aktiver Tag: `#bf4316`, font-weight 500)
- Aktiver Tag: `background: #fff8f6`

### Platznummern (unter Tagesköpfen)
- Nummer: Red Hat Display, 12px, bold, `#bf4316`
- Name: Red Hat Text, 10–11px, `#bf4316`
- Jeder Platz in eigenem Grid-Column (nicht flex innerhalb eines Tages)

### Buchungszellen
- Frei: `background: #ffffff`, klickbar
- Gebucht: `background: #eff6ff`, Text zentriert (Red Hat Text, 12–13px bold, `#1a3a6b`)
- Veranstaltung: `background: #fde8e1`, Text zentriert (Red Hat Text, 12px bold, `#7f2010`)
- Vergangen: `background: #f4f4f4`, nicht klickbar
- Borderstyle: `1px solid #e8e8e8` rechts + unten

---

## Admin-Sidebar

```
Breite: 200px
Hintergrund: #1b1d21 (dunkel)
Section-Label: 11px, uppercase, #6a6e73
Nav-Item inaktiv: color #a0a0a0, padding 9px 16px 9px 19px
Nav-Item aktiv: color #fff, font-weight 600, background rgba(255,255,255,0.1), border-left 3px solid #bf4316
```

---

## Login-Seite

**Aufbau**: 2-spaltige Karte auf grauem Hintergrund

```
Gesamt: max-width 672px, grid-cols-2
Links: padding 40px 32px, flex-col, justify-center, gap 14px
Rechts: padding 40px 32px, flex-col, gap 16px, bg #fafafa, border-left
```

Linke Spalte:
- "Mitgliedsbereich": 11px, uppercase, tracking-widest, `#6a6e73`
- "Anmelden": 26px, bold, Red Hat Display, `#151515`
- Beschreibung: 13px, `#6a6e73`, line-height 1.65

Rechte Spalte: Formularfelder (40px Höhe), Checkbox, Button full-width

---

## Modal-Header (Admin-Popups)

```css
background: #1b1d21
height: 52px
padding: 0 24px
display: flex, align-items: center, justify-content: space-between
```

Titel: 15px, bold, Red Hat Display, `#ffffff`
Close-Button: `×`, 20px, `#8a8d90`

---

## Formularsektionen

Jede Sektion hat:
1. Section-Header (Micro-Uppercase, Orange oder Grau, `border-bottom: 1px solid #ebebeb`)
2. Formularfelder mit 14–16px Gap
3. Abstand zur nächsten Sektion: 20–24px

Feldreihenfolge bei Datum/Zeit: **Datum (Beginn) → Zeit (Beginn) → Datum (Ende) → Zeit (Ende)**

---

## Responsive

Die aktuellen Templates sind **Desktop-first** (ab 1024px). Mobile-Optimierung
ist nicht Teil dieses Redesigns. Die Blade-Templates enthalten keine
Responsive-Breakpoints — das ist bewusst, da das System primär am Desktop
verwendet wird.

---

## Weitere Hinweise

### Keine Gradients oder Schatten-Spielereien
Alle Hintergründe sind flache Farben. Schatten nur wo nötig (Modals, Karten).

### Emoji: Keine
Statusanzeigen über Text-Badges und Farbe, nicht über Emoji.

### Animationen: Minimal
Nur `transition-colors` für Hover-Zustände (0.1–0.15s). Keine Page-Transitions.

### Icons: Minimal
Pfeil-Zeichen (◄ ►) für Navigation als Text-Zeichen. Keine Icon-Library nötig.

### Schriftgröße-Minimum
- Desktop-Formulare: 13px minimum
- Hilfstexte/Meta: 11px minimum (nie kleiner)
- Kalender-Platznamen: 10px (Ausnahme, da sehr kompakter Kontext)

---

## Blade-Komponenten-Referenz

Alle Komponenten liegen unter `resources/views/components/`.

| Komponente | Pfad | Zweck |
|------------|------|-------|
| Header | `layout/header.blade.php` | Logo, Navigation, Action-Buttons; trägt Klasse `no-print` |
| Admin-Sidebar | `layout/admin-sidebar.blade.php` | Dunkle Sidebar (200px, `#1b1d21`); aktiver Eintrag mit `border-l-[3px] border-[#bf4316] bg-white/10` |
| Kalender-Grid | `calendar/grid.blade.php` | Buchungstabelle; Zell-Klicks via Alpine `$dispatch('open-booking', {...})` |
| Kalender-Modals | `calendar/modals.blade.php` | Alle Modals des Buchungsflows (Buchung erstellen, stornieren, Feedback, Admin-Iframe) |

**Kein Livewire.** Alpine.js wird direkt auf HTML-Elementen verwendet (`x-data`, `x-show`, `x-model`, `x-transition`). Es gibt keine registrierten `<x-*>`-Komponenten-Tags.

---

## Formularmuster

### Datum + Zeit (zweipaltig)

Standard-Pattern für alle Datum/Zeit-Kombinationen — 2-spaltiges Grid, Reihenfolge immer: Datum Start → Zeit Start → Datum Ende → Zeit Ende.

```html
<div class="grid grid-cols-2 gap-3">
    <input type="date" name="date_start" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316]">
    <input type="time" name="time_start" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316]">
    <input type="date" name="date_end" ...>
    <input type="time" name="time_end" ...>
</div>
```

### Formular-Label (Tailwind-Pattern)

```html
<label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Feldname</label>
<input class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316]">
```

### Platzauswahl

Einfaches `<select>` ohne Custom-Styling — identische Klassen wie Input-Felder.

### Bedingte Felder (Spieleranzahl)

Felder für Spieler 3/4 werden per Alpine ein-/ausgeblendet:

```html
<div x-show="quantity == '4'">...</div>
```

### Legacy-Klassen (Admin-Formulare)

Ältere Admin-Views verwenden CSS-Klassen aus `booking.css`:

```
.admin-form__row          → grid, 200px Label + 1fr Feld
.admin-form__label        → 12px, bold, #444
.admin-form__field--flex  → flex-row für Datum+Zeit inline
.admin-form__inline-group → Label + Input nebeneinander
```

---

## Fehlerzustände

### Inline-Fehler (Tailwind-Views)

Fehlertext direkt unter dem Feld, kein Icon:

```html
<input class="... {{ $errors->has('alias') ? 'border-red-400' : 'border-[#d1cbc0]' }}">
@error('alias')
    <p class="text-xs text-red-600">{{ $message }}</p>
@enderror
```

- Fehler-Border: `border-red-400` (Tailwind)
- Fehlertext: `text-xs text-red-600`

### Fehler-Summary (Auth-Views)

Gesammelte Fehler als Block über dem Formular:

```html
@if($errors->any())
    <div class="error-message">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
```

CSS-Klasse `.error-message`: `background: #a84433`, `color: #fff`, `padding: 8px 10px`, `border-radius: 3px`.

### Focus-Ring (Admin-Legacy)

Admin-Inputs (Legacy-CSS) zeigen bei Fokus einen orangen Ring:

```css
border-color: #c75518;
box-shadow: 0 0 0 3px rgba(199, 85, 24, 0.12);
```

---

## Popup-Layout-Pattern

Admin-Views die sowohl als Vollseite (Admin-Layout) als auch als iframe-Popup erscheinen:

```blade
@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')

{{-- Für Popup-Layout (yields 'content'): --}}
@section('content')
    <div class="ui-page">...</div>
@endsection

{{-- Für Admin-Layout (yields 'admin-content'): --}}
@section('admin-content')
    <div class="ui-page">...</div>
@endsection
```

Beide Sections enthalten denselben Inhalt — Laravel rendert nur die, die das aktive Layout
yieldet. `layouts.popup` yieldet `content`, `layouts.admin` yieldet `admin-content`.

Controller-Methoden die Popup-Responses zurückgeben, brauchen einen Union-Return-Typ:

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

Formulare in Popups senden `<input type="hidden" name="popup" value="1">` mit.

### Formular-Includes mit `popup_mode`

Der `admin.events._form`-Partial hat zwei Layout-Zweige:

```blade
@include('admin.events._form', ['popup_mode' => request('popup')])
```

- `popup_mode = true` → kompaktes Tailwind-Layout (4-spaltig für Datum/Zeit, kein Section-Header)
- `popup_mode = false/null` → Legacy-UI-Klassen (`.ui-form-shell`, `.ui-form-divider` etc.)

---

## Lade- und AJAX-Zustände

Das System verwendet **kein Livewire, kein `wire:loading`, keinen `axios`/`fetch`-Layer** in den Views.

| Situation | Verhalten |
|-----------|-----------|
| Modal öffnet | Alpine `x-transition.opacity` — sanftes Einblenden |
| Feedback nach Aktion | Session-Flash-Modal, schließt sich nach **4 Sekunden** automatisch (`setTimeout(() => open = false, 4000)`) |
| Legacy-Admin-Formulare | Formular in verstecktem `<iframe>` — nach erfolgreicher Submission lädt die Elternseite neu (`window.location.reload()`) |

Es gibt **keine Spinner, Skeleton-Screens oder Ladebalken**. Ladezeiten werden durch schnelle Server-Responses und minimales JS überbrückt.

---

## E-Mail- und Print-Styles

### E-Mail-Templates

Templates unter `resources/views/emails/`:

| Datei | Zweck |
|-------|-------|
| `booking-confirmed.blade.php` | Buchungsbestätigung mit Detailtabelle |
| `booking-cancelled.blade.php` | Stornierungsbenachrichtigung |
| `user-activated.blade.php` | Account-Aktivierung |

E-Mails verwenden **Inline-CSS** (kein Tailwind, kein externes Stylesheet) für maximale Mail-Client-Kompatibilität:

```css
body { font-family: Arial, sans-serif; color: #222; font-size: 15px; line-height: 1.6; }
h1   { font-size: 20px; margin-bottom: 4px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
td   { padding: 8px 0; border-bottom: 1px solid #eee; }
.footer { margin-top: 32px; font-size: 13px; color: #888; }
```

Layout: einfache `<table>`-Struktur, kein Multi-Column-Design. Kein Club-Orange in Mails — neutrale Farben für maximale Lesbarkeit.

### Print-Styles

Es gibt **keine `@media print`-Regeln** in den CSS-Dateien. Print-Ausschlüsse werden ausschließlich über die Utility-Klasse `no-print` gesteuert:

| Element | Klasse |
|---------|--------|
| Header | `no-print` |
| Hilfe-Panels | `no-print` |

Alles ohne `no-print` wird gedruckt. Wer neue Elemente ergänzt, die nicht druckrelevant sind, fügt `no-print` hinzu.
