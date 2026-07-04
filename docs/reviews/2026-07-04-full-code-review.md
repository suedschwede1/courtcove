# Code- & Funktionalitäts-Review — 2026-07-04

Vier parallele Review-Durchläufe (Security, Booking-Kernlogik, Members-Modul, Wiring/Funktionalität) plus kompletter Testlauf. Alle Findings wurden gegen den tatsächlichen Code verifiziert (Datei:Zeile).

## Testlauf

`php artisan test`: **10 failed, 339 passed** (931 Assertions, ~6,5 min):

- `Tests\Unit\Services\SquareValidatorTest` — 2× „configured short term w…"
- `Tests\Feature\Admin\AdminBookingTest` — „index lists active booking…", „admin can create overlappi…", „index shows free text owne…"
- `Tests\Feature\Admin\EventManagementTest` — „create validates end af…"
- `Tests\Feature\Admin\UserManagementTest` — „index lists non deleted…"
- `Tests\Feature\Auth\LoginTest` — „register page renders configurable…"
- `Tests\Feature\Mail\BookingMailTest` — „confirmation mail is queued", „cancellation mail is queued" (Mailable wird nicht gequeued — passt zum Befund, dass Mails via `afterResponse()` versendet werden)

## Booking-Kernlogik

### HIGH

**B1 — Double-Booking-Race: kein Locking, kein Unique-Constraint.**
`app/Services/BookingService.php:37-56` — Der „Re-Check" in der Transaktion ist ein einfaches `SELECT ... SUM()` ohne `lockForUpdate()` (`hasEnoughCapacity()`, Z. 256-276); `bs_reservations` hat keinen Unique-Key auf (sid, date, time_start). Unter InnoDB REPEATABLE READ sehen zwei parallele Requests beide den Slot als frei → zwei bestätigte Buchungen für denselben Platz/Slot, beide bekommen Bestätigungsmail. Fix: `SELECT ... FOR UPDATE` auf die Square-Zeile als Mutex oder DB-Unique-Constraint.

**B2 — App-Timezone UTC, Daten aber Wien-Wallclock.**
`config/app.php:68` — `'timezone' => 'UTC'` hart codiert, nirgends überschrieben. Die Legacy-DB speichert lokale DATE/TIME-Strings, alle `now()`-Vergleiche laufen aber in UTC:
- Vergangenheits-Check (`SquareValidator.php:65`): im Sommer (UTC+2) kann um 20:30 lokal noch der 18:00–19:00-Slot gebucht werden.
- Storno-Deadline (`BookingService.php:207`): Mitglieder können bis zu 2h näher am Start stornieren als `range_cancel` erlaubt.
- `min_range_book`/`range_book`-Fenster und Short-Term-Override um 1h (Winter) / 2h (Sommer) verschoben; springt bei jeder DST-Umstellung.
- Kalender-„Heute"-Markierung (`CalendarController.php:233,264`) kippt zwischen 22/23 Uhr und Mitternacht auf den falschen Tag.
Fix: `'timezone' => 'Europe/Vienna'`.

### MEDIUM

**B3 — Admin-Konfliktprüfung komplett außerhalb der Transaktion (TOCTOU).**
`app/Http/Controllers/Admin/BookingController.php:134-140` (store) und `:228-235` (update): `batchCheckConflicts()` läuft vor `DB::transaction()`, kein Re-Check innen. Fenster noch größer als bei B1 (Meta-Sync, Multi-Row-Inserts bei Serien). Nebenbefund: `hasBookingConflict()`/`hasEventConflict()` (`:564-591`) sind tote private Methoden.

**B4 — Quota-Checks beim Bearbeiten zählen die eigene Buchung mit.**
`BookingService.php:153` reicht `$excludeBookingId` nur an den Kapazitätscheck weiter, nicht an `SquareValidator::validate()` (`:234`). Bei `max_active_bookings = 1` kann ein Mitglied seine einzige Buchung nicht bearbeiten („max_active_bookings_reached"); analog `time_block_bookable_max`. Spieler-Änderung nach Slot-Ende scheitert an `booking_in_past`.

**B5 — Member-`store()` akzeptiert beliebige, nicht Slot-alignierte Sekunden.**
`BookingController.php:47-48`: `time_start`/`time_end` sind freie Integers; kein Alignment-Check auf `square.time_block`, kein Per-Booking-Maximum. Ein gecrafteter POST bucht 08:00–22:00 in einem Request; einzige Bremse ist die Tagesquote (kann 0/unlimited sein). Misalignierte Zeiten brechen zudem das Kalender-Grid (B6).

**B6 — Kalender-Slot-Map schneidet Minuten ab.**
`CalendarController.php:84-88`: Stunden werden per `substr(...,0,2)` extrahiert. Admin-Buchung 09:30–10:30 belegt visuell nur Slot 9; Slot 10 erscheint frei, jede Buchung darauf schlägt aber fehl.

### LOW

**B7 — Storno ohne Guards / doppelte Mails.**
`BookingService.php:161-180`: `cancelSingle()` prüft keinen Status — erneutes Stornieren einer stornierten Buchung schickt die Storno-Mail nochmal (`canUserCancelSingle()` Z. 188 schließt nur `subscription` aus). Admin-`cancel()` (`Admin/BookingController.php:287-301`) hat gar keine Guards (auch Vergangenheit/ganze Abos); setzt `status_billing = 'cancelled'`, obwohl der Docblock nur `pending|paid` dokumentiert — gegen echte DB prüfen. Kein Refund-/Gutschrift-Handling beim Storno (Bills bleiben unangetastet).

**B8 — `normalizeBookableEnd()` „repariert" ungültige Eingaben vor der Validierung.**
`BookingService.php:34, 290-303`: `time_end <= time_start` wird still zu `start + time_block_bookable` umgeschrieben — es entsteht (und kostet) eine Buchung, die der User nie beschrieben hat. 30-min-Anfrage auf 60-min-Platz wird still verdoppelt.

**B9 — `App\Enums\BookingStatus` ist toter Code** mit falschen Werten (`enabled/disabled` statt `single|subscription|cancelled`).

### Design-Hinweise (gegen Club-Regeln prüfen)
- Peak-Limit zählt nur nach `time_start` (`SquareValidator.php:94, 177-178`): 16:00–18:00 über das 17:00-Peak-Fenster triggert/zählt nicht. Beide Count-Queries nutzen `date >= today` — heute bereits gespielte Slots zählen noch als „aktiv zukünftig".
- Kapazitäts-Semantik (`BookingService.php:269-275`): bei `capacity_heterogenic = 1` und capacity ≥ 4 können zwei unabhängige 2-Spieler-Buchungen denselben Platz gleichzeitig halten.
- `$sameDayAsMax`-Ausnahme (`SquareValidator.php:79-80`): letzter Tag des `range_book`-Horizonts ist komplett buchbar („aufgerundet auf Tagesende") — vermutlich gewollt.

### Sauber befunden
Overlap-Operatoren korrekt half-open (Ende 10:00 blockiert 10:00-Slot nicht); Event-Vergleiche konsistent (Carbon-Casts); Mails erst nach Commit via `afterResponse()`; Meta-Modelle konsistent; stornierte Buchungen überall via `ACTIVE_STATUSES` ausgeschlossen; Serien-Generierung terminiert korrekt (`addMonthNoOverflow`); Komma-Syntax in `can('a, b')` ist gewollte OR-Syntax.

## Members-Modul

### HIGH

**M-H1 — Dues-Bootstrap-Sackgasse: neue Mitglieder können nie fakturiert werden.**
`MemberDueController.php:51-55` — `generate()` kopiert nur den *vorherigen* Beitrag (`if (! $lastDue) return;`); es gibt keine Route/UI zum manuellen Anlegen eines einzelnen Beitrags, Mitglieder haben kein Fee-Category-Feld. Auf frischer Installation oder für Neuzugänge entstehen für immer 0 Beiträge — still.

**M-H2 — Zahlungsbetrag wird nie gegen die Beiträge geprüft; Teilzahlung markiert voll bezahlt und blockiert den Rest dauerhaft.**
`PaymentController.php:28-56` validiert nur `amount >= 0.01`; `MemberDue::isPaid()` (`MemberDue.php:40-43`) ist bloß `payments()->exists()`. €1 auf €120-Beitrag → überall „bezahlt", zweite Zahlung wird abgelehnt, restliche €119 nie erfassbar. Überzahlung wird still geschluckt.

**M-H3 — Zahlungserstellung nicht transaktional + TOCTOU-Doppel-Allokation.**
`PaymentController.php:49-56`: `create()` + `attach()` ohne Transaktion (Orphan-Payment bei Fehler); „already paid"-Check nur in der Validierung; `payment_dues`-Unique ist nur (payment_id, member_due_id) → zwei parallele Submits allokieren denselben Beitrag doppelt.

### MEDIUM

- **M1** „Aktuelles Geschäftsjahr" inkonsistent: Dashboard `orderByDesc('ends_on')` vs. `$fiscalYears->last()` in Dues/FeeCategory; vorangelegtes 2027/28 kippt das Dashboard auf das leere Zukunftsjahr. Keine Overlap-/Duplikats-Validierung bei Geschäftsjahren.
- **M2** Fehlender Rate-Eintrag überspringt Mitglieder bei der Generierung still (`MemberDueController.php:57-63`) — ganze Kategorie bleibt unbemerkt unfakturiert.
- **M3** `latest('fiscal_year_id')` setzt chronologische Anlage voraus; nachträglich erfasste Altjahre liefern falsche Kategorie.
- **M4** CSV-Export: Komma-Delimiter bricht deutsches Excel (BOM ist da, Delimiter nicht); exportiert alle inkl. ausgetretener Mitglieder, ignoriert Filter; Route `members.export` ist von keiner View verlinkt (unerreichbar).
- **M5** Kein `max` auf Geldbeträgen vs. DECIMAL(8,2) → >999.999,99 ergibt 500er; >2 Dezimalen werden still gerundet.
- **M6** `cascadeOnDelete` auf `fee_category_id` in `member_dues` würde historische Beiträge (und Allokationen) mitlöschen — `restrictOnDelete` wäre sicherer (aktuell keine Delete-Routen, nur via SQL/tinker erreichbar).
- **M7** Zahlungen/Beiträge sind unveränderlich: keine Edit-/Delete-Routen — Tippfehler dauerhaft unkorrigierbar.
- **M8** „Alle"-Filter zeigt nur Aktive (`MemberController.php:18-22`).

### LOW
Unvalidierte Array-Keys in `storeRates` (500 bei geforgtem Key); Ratenänderung nach Generierung ohne Warnung; N+1 in `Member::paidUntil()`; `number_format` nicht lokalisiert („1,234.56" in deutscher UI); Generierungsloop nicht transaktional (Doppelklick → 500 mit Teilstand); keine Unique-Constraints auf Kategorie-/Jahres-Namen.

### Sauber befunden
Geld konsistent DECIMAL(8,2) + `decimal:2`-Casts, kein Float-Rechnen; Duplikat-Beiträge per Unique verhindert; Payment-Ownership erzwungen und getestet; Übersetzungen DE/EN vollständig; Routen korrekt hinter `auth` + `can:admin.members`; keine N+1 in Index-Views; Nullable-Handling sauber.

## Security

### MEDIUM
**S1 — Web-triggerbare Migration gegen bekannt falsche Schema-Definitionen.**
`Admin/DatabaseController.php:58` — POST `/admin/database/migrate` führt `migrate --force` aus (Gate `admin.config` + „MIGRATE"-Bestätigung). Da die Repo-Migrationen nicht dem echten `booking_local`-Schema entsprechen, ist das ein One-Click-Weg, die Produktions-DB zu beschädigen.

### LOW
- **S2** Stored XSS auf der öffentlichen Registrierungsseite: `register.blade.php:140` rendert `{!! $registrationPrivacy !!}` unescaped; Quelle ist die Option `service.user.registration.privacy` (nur `string|max:1000` validiert). Braucht `admin.config`, Payload läuft aber bei allen Besuchern.
- **S3** Open Redirect im Admin-Booking-Delete: `Admin/BookingController.php:311-313` nutzt user-kontrolliertes `redirect_to` ohne Same-Origin-Check (Login-Controller macht es richtig — `normalizeRedirectTarget` wiederverwenden).
- **S4** `.env.dusk` (mit APP_KEY, `APP_DEBUG=true`) fehlt in `.gitignore` — `git add .` würde es committen. Nur lokaler Dusk-Key, trotzdem ignorieren.

### Sauber befunden
Alle Admin-/Members-Routen korrekt hinter `auth` + Gates; keine IDOR (Ownership-Checks in BookingController/AccountController korrekt); Mass Assignment überall explizit whitelisted; einzige Raw-Query mit Bindings; Login mit mehrschichtigem Throttling, Session-Regenerate, sicherem Redirect, Country-Blocking; Registrierung failt safe auf `status='disabled'`; keine CSRF-Ausnahmen; `admin_mode`-Cookie nur dekorativ (echtes Gate wird re-checked).

## Wiring / Funktionalität

- **Country-blocked-login-Plan** (`docs/superpowers/plans/2026-07-02-country-blocked-login.md`): vollständig implementiert und committed (Commits 0ddea9f, d722b8a, cdbb603, 0c8a3a8) — Location-Package, MaxMind-Driver ohne Fallbacks, `booking.blocked_login_countries`, LoginController-Check + 4 Tests. Nur die Checkboxen im Plan-Doc sind noch offen; Upload der .mmdb auf hosting.example.test aus dem Repo nicht verifizierbar.
- **`CheckDatabaseHealth`-Listener** (untracked): nirgends registriert, wird aber via Laravel-Auto-Discovery + `health: '/up'` (`bootstrap/app.php:13`) trotzdem ausgeführt; macht nur `DB::connection()->getPdo()`, nutzt `DatabaseSchemaChecker` nicht; kein Test. → Committen und ggf. testen.
- **Tote/verwaiste Routen**: `members.export` von nirgends verlinkt; `bookings.create`/`bookings.edit` (Non-Admin-GET-Seiten) haben keinen Inbound-Link (Buchung läuft über Kalender-Modals); `admin.testmail.index` nur per URL erreichbar.
- **Hardcodierte Fetch-URL**: `resources/views/components/calendar/modals.blade.php:72,201` (+2 weitere Blades) nutzen `fetch('/bookings/players?...')` statt der benannten Route.
- **Rollen-Preset lückenhaft**: `admin/users/_form.blade.php:89-90` — „assist"-Preset kennt `admin.members` nicht.
- **EN-Übersetzungslücke**: `lang/en/booking/quotes.php` — `quotes_named` 94–102 fehlen (DE ist Fallback, bricht nichts).
- **MakeModuleCommand-Falle**: generiertes `can:admin.{name}` wäre nicht in `User::PRIVILEGES` (`User.php:34`) — neue Module brauchen manuelle Erweiterung der Konstante.
- **Sauber**: alle route()-Namen in Views existieren; alle `config('booking.*')`/`config('calendar.colors.*')`-Keys vorhanden und referenziert; Vite-Build konsistent mit committetem `public/build/` (Shared-Hosting-Deploy); Console-Commands referenzieren existierende Klassen.

## Empfohlene Priorisierung

1. **B2** Timezone auf `Europe/Vienna` (eine Zeile, behebt eine ganze Fehlerklasse)
2. **B1/B3** Locking bzw. Unique-Constraint gegen Double-Booking (Member + Admin-Pfad)
3. **M-H1/M-H2/M-H3** Members-Zahlungslogik (Teilzahlung, Bootstrap, Transaktion)
4. **10 rote Tests** analysieren (teils vermutlich mit B2/Mail-Dispatch-Weg verwandt)
5. **B4** Quota-Exclude beim Bearbeiten; **B5/B6** Slot-Alignment-Validierung
6. Security-Kleinkram: S1 (Migrate-Button entschärfen/entfernen), S3 (Redirect normalisieren), S4 (`.gitignore`)
