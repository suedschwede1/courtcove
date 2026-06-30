# Laravel Update-Anleitung

## Überblick

Diese App läuft auf **Laravel 13** mit **PHP 8.3**. Updates folgen diesem Ablauf:

1. Backup & Branch
2. Composer-Update
3. Breaking Changes prüfen & beheben
4. Tests
5. Deployment

---

## Vor dem Update

### 1. Branch anlegen

```bash
git checkout -b update/laravel-XX
```

### 2. Aktuellen Stand sichern

```powershell
# Alle Änderungen committen
git add -A
git commit -m "chore: vor Laravel-Update"
```

### 3. Upgrade Guide lesen

Vor jedem Major-Update den offiziellen Upgrade Guide lesen:
- Laravel 13 → 14: https://laravel.com/docs/14.x/upgrade
- Laravel 14 → 15: https://laravel.com/docs/15.x/upgrade

---

## Minor-Update (z.B. 13.8 → 13.12)

Minor-Updates sind in der Regel sicher und brechen keine öffentliche API.

```powershell
# composer.json anpassen (falls nötig)
# "laravel/framework": "^13.8"  →  bleibt, ^ erlaubt Minor-Updates automatisch

# Update durchführen
wsl composer update laravel/framework

# Tests
wsl php artisan test
```

---

## Major-Update (z.B. Laravel 13 → 14)

### Schritt 1: PHP-Version prüfen

Laravel 14 erfordert mindestens PHP 8.3 — bereits erfüllt.
Neue Major-Versionen können höhere PHP-Versionen erfordern — immer im Upgrade Guide nachsehen.

### Schritt 2: `composer.json` anpassen

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^14.0"
    }
}
```

Auch Abhängigkeiten anpassen, die Laravel-Versionen voraussetzen (z.B. `laravel/tinker`, `larastan/larastan`).

### Schritt 3: Update durchführen

```powershell
wsl composer update
```

Bei Konflikten einzeln lösen:

```powershell
# Einzelnes Paket aktualisieren
wsl composer require laravel/framework:^14.0

# Alle auf einmal, mit Abhängigkeiten
wsl composer update --with-all-dependencies
```

### Schritt 4: Konfigurationsdateien prüfen

Laravel veröffentlicht manchmal neue Konfigurationsdefaults. Diff mit dem offiziellen Skeleton vergleichen:

```powershell
# Welche Config-Dateien haben sich geändert?
# Manuell: https://github.com/laravel/laravel/compare/v13.0.0...v14.0.0
```

Wichtige Dateien die sich häufig ändern:
- `config/app.php`
- `bootstrap/app.php`
- `app/Http/Kernel.php` (falls vorhanden)

### Schritt 5: Breaking Changes beheben

Häufige Breaking Changes und wo sie auftreten:

| Bereich | Was prüfen |
|---------|-----------|
| Middleware | `bootstrap/app.php` — Middleware-Registrierung |
| Eloquent | Geänderte Model-Methoden oder Cast-Verhalten |
| Validation | Neue oder geänderte Validierungsregeln |
| Collections | Geänderte Methoden-Signaturen |
| Routing | Geänderte Route-Definitionen |

### Schritt 6: Statische Analyse

```powershell
wsl vendor/bin/phpstan analyse
```

Fehler beheben bevor Tests laufen.

### Schritt 7: Tests

```powershell
wsl php artisan test
```

Alle Tests müssen grün sein (außer bekannte pre-existing Failures).

### Schritt 8: App starten und manuell prüfen

```powershell
npm run dev
```

Kritische Seiten im Browser prüfen:
- [ ] Login
- [ ] Kalender-Ansicht
- [ ] Buchungs-Modal öffnen
- [ ] Buchung anlegen (Singles + Doppel)
- [ ] Admin-Bereich

---

## Nach dem Update

### Frontend neu bauen

```powershell
npm run build
git add public/build
git commit -m "build: Frontend nach Laravel-Update neu gebaut"
```

### Merge & Deployment

```bash
git checkout master
git merge update/laravel-XX
```

Dann normales Deployment per FTP auf hosting.example.test.

---

## Rollback

Falls etwas nicht funktioniert:

```bash
# Branch verwerfen
git checkout master
git branch -D update/laravel-XX

# Oder: Composer auf alte Version zurücksetzen
wsl composer require laravel/framework:^13.0
wsl composer update laravel/framework
```

---

## Bekannte Einschränkungen

- **Kein `php artisan migrate`** auf der Produktions-DB — die App nutzt das Legacy-Schema `booking_local`
- **Deployment manuell via FTP** — kein `git pull` auf dem Server (hosting.example.test Shared Hosting)
- **PHP via WSL** — alle `php`/`composer`-Befehle über WSL, nie direkt in PowerShell
