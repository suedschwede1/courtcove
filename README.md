# Example Club – Reservierungssystem (bookingnew)

Laravel-basiertes Platzbuchungssystem für den Example Club Tennisclub.  
Ersetzt das alte Zend/Laminas-System (`booking`).

## Stack

| Komponente | Version |
|---|---|
| PHP | 8.3 |
| Laravel | 13.x |
| Datenbank | MySQL (`booking_local`) |
| Frontend | Tailwind CSS, Alpine.js, Vite |
| Deployment | Shared Hosting hosting.example.test, FTP |

## Lokale Entwicklung

```bash
# Abhängigkeiten
composer install
npm install

# .env konfigurieren (DB: booking_local)
cp .env.example .env
php artisan key:generate

# Dev-Server
php artisan serve       # http://localhost:8001
npm run dev             # Vite HMR

# Tests (via WSL)
php artisan test
```

> **Wichtig:** `php`/`composer` immer via WSL ausführen, nie direkt in PowerShell.

## Deployment (hosting.example.test)

1. `npm run build` lokal ausführen
2. `public/build/` committen
3. Geänderte Dateien per FTP auf den Server übertragen

Kein Node.js auf dem Server — Vite-Build muss lokal gebaut und committed werden.

## Layout-Referenz

Das stabile UI-Layout ist mit dem Git-Tag **`mobile-view-stable`** markiert.  
Siehe [docs/DESIGN.md](docs/DESIGN.md) für Details zum Design-System.

```bash
# Zum stabilen Layout-Stand zurücksetzen (einzelne Dateien)
git checkout mobile-view-stable -- public/css/booking.css
git checkout mobile-view-stable -- resources/views/components/layout/header.blade.php
```

## Wichtige Dateien

| Datei | Zweck |
|---|---|
| `public/css/booking.css` | Haupt-CSS inkl. Mobile-Responsive-Styles |
| `resources/css/calendar-grid.css` | Kalender-Grid-Layout |
| `resources/views/components/layout/header.blade.php` | App-Header (Desktop + Mobile) |
| `resources/views/components/calendar/grid.blade.php` | Kalender-Raster |
| `resources/views/components/calendar/modals.blade.php` | Buchungs-Modals (Alpine.js) |
| `resources/views/admin/` | Admin-Bereich |
| `app/Http/Controllers/BookingController.php` | Buchungs-Logik (User) |
| `app/Http/Controllers/Admin/BookingController.php` | Buchungs-Logik (Admin) |
| `app/Services/PeakLimitService.php` | Stoßzeiten-Limitierung |

## Berechtigungsmodell

Keine Roles-Tabelle. Steuerung über `bs_users.status`:

| Status | Rechte |
|---|---|
| `admin` | Alles |
| `assist` | Laut `bs_users_meta` `allow.*`-Flags |
| `enabled` | Normale Buchungen |

## Buchungsmodi

- **Einzel** (2 Spieler): Mitspieler per User-Suche auswählen (Pflichtfeld)
- **Doppel** (4 Spieler): Mitspieler als Freitext eingeben (Pflichtfeld)
