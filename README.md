# Tennis-Booking

> ⚠️ **Work in Progress** — die Migration ist noch nicht abgeschlossen. Dieses Repository befindet sich aktiv in Entwicklung.

Online-Platzreservierung für Tennisvereine — eine **Laravel-13-Neufassung** des
Open-Source-Systems [ep3-bs](https://github.com/tkrebs/ep3-bs) (Zend Framework 2),
konzentriert auf die Kernanforderungen eines kleinen Vereins.

Mitglieder reservieren Plätze über einen Tageskalender, Administratoren
verwalten Buchungen, Veranstaltungen, Mitglieder und Einstellungen.

Das System läuft gegen dieselbe, unveränderte produktive MySQL-Datenbank wie
ep3-bs — beide Systeme können parallel betrieben werden.

---

## Tech-Stack

| Bereich    | Technologie                                   |
|------------|-----------------------------------------------|
| Framework  | Laravel 13, PHP 8.3+ (vorwärtskompatibel 9.0) |
| Datenbank  | MySQL (`booking_local`, Tabellen `bs_*`)      |
| Views      | Blade                                         |
| Frontend   | Vite + Tailwind CSS 4                          |
| Tests      | PHPUnit (Laravel Test-Suite)                  |

PHP-9.0-Stil: `declare(strict_types=1)` in jeder Datei, `readonly` auf
injizierten Abhängigkeiten, Enums für Status-/Typ-Felder, vollständige Typ-Hints.

---

## Schnellstart

Voraussetzungen: PHP 8.3+, Composer, Node.js, eine laufende MySQL-Instanz mit der
Datenbank `booking_local`.

```bash
composer install
npm install
cp .env.example .env        # bzw. vorhandene .env prüfen
php artisan key:generate    # nur falls noch kein APP_KEY gesetzt
```

DB-Zugang in `.env` (Standard für lokal):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=booking_local
DB_USERNAME=booking
DB_PASSWORD=REDACTED
```

Der sichtbare Name im Header kommt aus der Admin-Konfiguration `service.name` (`Name des Systems`). `BOOKING_NAME` ist der Fallback, wenn diese Option leer ist. Logo und feste Anzeigegröße können über `.env` angepasst werden:

```env
BOOKING_NAME=Tennis-Booking
BOOKING_LOGO_PATH=imgs-client/layout/client-logo.jpg
BOOKING_LOGO_WIDTH=112
BOOKING_LOGO_HEIGHT=108
```

Alles auf einmal starten (Server + Queue + Logs + Vite):

```bash
composer run dev
```

Danach im Browser: **http://localhost:8000**

> Nur den Webserver (ohne Queue/Vite): `php artisan serve` — reicht, wenn die
> Assets schon mit `npm run build` gebaut wurden.

> ⚠️ **Keine Migrations gegen die echte DB laufen lassen.** Das System nutzt das
> bestehende Legacy-Schema (siehe unten). Die mitgelieferten Migrations/Seeder
> beschreiben teils ein abweichendes Schema und sind nur für `:memory:`-Tests
> bzw. Doku gedacht — niemals `php artisan migrate` auf `booking_local`.

---

## Funktionsumfang

### Öffentlicher Kalender (`/calendar`)
- Zeigt **drei Tage gleichzeitig** (gestern / heute / morgen) und alle Plätze.
- Zeitraster **08:00–19:00 Uhr**, ein Block = 1 Stunde.
- Navigation tageweise per Pfeile, „Heute" oder Datumswähler.
- Farbcodierung:
  - **Weiß** – frei und buchbar
  - **Blau** – belegte/eigene Reservierung (Gäste sehen „Belegt")
  - **Rosa** – Veranstaltung oder Sperre
  - **Grau** – Vergangenheit, nicht mehr buchbar
- Plätze: `1 → Court B`, `2 → Court D`, `3 → Court C`
  (Anzeigenamen kommen aus `bs_squares_meta`, Fallback auf diese Legacy-Namen).

### Buchen & Stornieren (angemeldet)
- Freies Feld anklicken → Modal: **Einzel** (2 Spieler) oder **Doppel** (4 Spieler).
- Mitspielernamen mit **AJAX-Autocomplete** (`/bookings/players`, sucht in
  `alias`, `firstname`, `lastname`).
- Eigene Buchungen sind blau und lassen sich direkt aus dem Plan stornieren.
- Validierung beim Anlegen: Platzstatus, Buchungsfenster/-vorlauf, Tageslimit,
  Slot-Überschneidung und Veranstaltungs-Sperren (`SquareValidator` +
  `BookingService`, doppelt geprüft innerhalb einer DB-Transaktion).

### Mein Bereich (angemeldet)
- `/meine-buchungen` – eigene Reservierungen
- `/mein-konto` – Profil & Passwort ändern

### Admin-Bereich (`/admin`, rechtegesteuert)
- **Mitglieder** (`admin.user`)
- **Buchungen** (`admin.booking`) – anlegen/bearbeiten/stornieren für alle
- **Veranstaltungen** (`admin.event`) – Plätze sperren/blockieren
- **Konfiguration** (`admin.config`)
- Dashboard sichtbar ab `admin.see-menu`

### Bewusst nicht migriert
Diese Legacy-Funktionen aus ep3-bs werden in der Laravel-Version absichtlich nicht übernommen:

- Zusatzprodukte zur Buchung
- Preis-/Billing-Zusammenfassung im Public-Booking-Flow
- Akzeptieren von Regel-Dokumenten
- Akzeptieren von Regel-Texten

Die zugehörigen Legacy-Tabellen/Models können für historische Daten weiter existieren, sind aber nicht Teil des neuen öffentlichen Buchungsablaufs.

---

## Berechtigungsmodell

Es gibt **keine** Rollen-/Permission-Tabelle. Die Rechte ergeben sich aus
`bs_users.status` plus Meta-Flags:

- `status = 'admin'` → `can()` ist immer `true` (alle Rechte).
- `status = 'assist'` → Einzelrechte als Flags in `bs_users_meta`
  (`allow.<privilege> = 'true'`). Die Privileg-Strings unterstützen
  **ODER** (`,`) und **UND** (`+`): `"a, b+c"` = `a` ODER (`b` UND `c`).
- `status = 'enabled'` (und andere) → keine privilegierten Rechte.

Verfügbare Privilegien: siehe `User::PRIVILEGES`
(`admin.*`, `calendar.see-past`, `calendar.see-data`,
`calendar.create/cancel/delete-single-bookings`, … `-subscription-bookings`).

---

## Datenmodell (Legacy-Schema, `bs_*`)

> **Kompatibilität mit dem Altsystem (Pflicht).** Die Laravel-Neufassung läuft
> gegen **dieselbe produktive Datenbank** wie das alte ep3-bs-System.
> Beide Systeme können parallel auf denselben Daten
> arbeiten, daher muss das neue System zum bestehenden Schema **abwärtskompatibel**
> bleiben: bestehende Spalten und Keys dürfen **nicht** umbenannt, umtypisiert oder
> entfernt werden, und bestehende Werte müssen weiterhin so interpretiert werden wie
> bisher.
>
> **Neue Datenbankfelder sind erlaubt** — solange sie rein **additiv** sind und das
> Altsystem nicht stören: zusätzliche Spalten (`NULL`-bar oder mit Default) oder neue
> `*_meta`-Keys. Niemals bestehende Felder verändern, um Neues abzubilden.

Wichtige Eigenheiten der echten Tabellen — Code richtet sich danach, **nicht** nach
den Beispiel-Migrations:

- **`bs_users`** – Anmeldename ist `alias`, Passwort-Hash ist `pw` (nicht
  `password`). Profilfelder (firstname, lastname, phone …) liegen in
  `bs_users_meta`.
- **`bs_squares`** – kein `alias`-Spalte (Labels in `bs_squares_meta`),
  Öffnungszeiten als `TIME`-Spalten, `priority` ist `float`.
- **`bs_bookings`** – `status` = `single` | `subscription` (aktiv) | `cancelled`;
  `status_billing` = `pending` | `paid` | `cancelled`. Klartext-Strings, **keine**
  Enum-Casts.
- **`bs_reservations`** – `date` ist `'Y-m-d'`-String (kein Unix-Timestamp),
  `time_start`/`time_end` sind `'HH:MM:SS'`-Strings. Überschneidung wird als
  halboffenes Intervall `[start, end)` geprüft.
- **`bs_bookings_meta`** – Mitspielernamen als **serialisiertes PHP-Array** unter
  dem Key `player-names`.
- **`bs_events`** – Veranstaltungen/Sperren; `sid = NULL` sperrt alle Plätze.

Übersicht der Tabellen: `bs_users(_meta)`, `bs_squares(_meta/_products/_pricing/_coupons)`,
`bs_bookings(_bills/_meta)`, `bs_reservations(_meta)`, `bs_events(_meta)`, `bs_options`.

---

## Projektstruktur

```
app/
  Enums/         Status-/Typ-Enums (BookingStatus, SquareStatus, …)
  Http/Controllers/
    CalendarController        3-Tage-Kalender (öffentlich)
    BookingController         Buchen/Stornieren + Spieler-Autocomplete
    AccountController         Mein Konto / Meine Buchungen
    Auth/                     Login/Logout
    Admin/                    User-, Booking-, Event-, Option-, Dashboard-Controller
  Models/        Eloquent-Models auf die bs_*-Tabellen
  Services/
    BookingService            Buchung anlegen/stornieren/löschen (transaktional)
    ReservationService        Reservierungs-Queries & Slot-Overlap
    SquareValidator           Buchungsregeln (Status, Vorlauf, Limits)
resources/views/ Blade-Templates (calendar, account, admin, auth, layouts)
routes/web.php   alle Routen
```

---

## Tests

```bash
composer test
# oder
php artisan test
```

Tests laufen gegen SQLite `:memory:` und sind vom produktiven MySQL entkoppelt.

---

## Wichtige Routen

| Methode | Pfad                       | Zweck                          | Auth          |
|---------|----------------------------|--------------------------------|---------------|
| GET     | `/calendar`                | Kalender (Start)               | öffentlich    |
| GET     | `/login`, POST `/login`    | Anmeldung                      | öffentlich    |
| POST    | `/bookings`                | Buchung anlegen                | Mitglied      |
| DELETE  | `/bookings/{booking}`      | Buchung stornieren             | Eigner/Admin  |
| GET     | `/bookings/players`        | Spieler-Autocomplete (JSON)    | Mitglied      |
| GET     | `/meine-buchungen`         | eigene Buchungen               | Mitglied      |
| GET/PUT | `/mein-konto`              | Profil/Passwort                | Mitglied      |
| —       | `/admin/...`               | Verwaltung                     | rechtegesteuert |
