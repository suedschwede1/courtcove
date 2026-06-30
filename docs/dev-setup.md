# Entwicklungsumgebung einrichten

## Voraussetzungen

| Tool | Version | Installationsort |
|------|---------|-----------------|
| Windows 11 | — | Host |
| WSL 2 (Ubuntu) | — | Windows-Feature |
| PHP | 8.3 | WSL |
| Composer | 2.x | WSL |
| Node.js | 20+ | Windows (nativ) |
| npm | 10+ | Windows (nativ) |
| MySQL | 8.x | Windows oder WSL |
| Git | — | Windows |

> **Wichtig:** PHP und Composer werden **ausschließlich über WSL** ausgeführt, nie direkt in PowerShell.

---

## 1. WSL einrichten

```powershell
# PowerShell als Administrator
wsl --install -d Ubuntu
```

Nach dem Neustart Ubuntu öffnen und PHP + Composer installieren:

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.3 + benötigte Extensions
sudo add-apt-repository ppa:ondrej/php
sudo apt install -y php8.3 php8.3-cli php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-mysql php8.3-zip php8.3-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Prüfen
php -v
composer --version
```

---

## 2. Repository klonen

```bash
# In PowerShell oder Git Bash
git clone <repo-url> C:\development\bookingnew
cd C:\development\bookingnew
```

---

## 3. PHP-Abhängigkeiten installieren

```powershell
# In PowerShell — Befehle laufen via WSL
wsl composer install
```

---

## 4. Node-Abhängigkeiten installieren

```powershell
# In PowerShell (Node läuft nativ)
npm install
```

---

## 5. Umgebungskonfiguration

```powershell
# .env erstellen
copy .env.example .env   # oder manuell anlegen

# App-Key generieren
wsl php artisan key:generate
```

`.env` anpassen:

```env
APP_NAME="Tennisclub Buchung"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=booking_local
DB_USERNAME=root
DB_PASSWORD=REDACTED
```

---

## 6. Datenbank einrichten

Die App läuft gegen die **echte Legacy-Datenbank** `booking_local` — keine Migrations ausführen, die das Schema verändern.

```sql
-- In MySQL: Datenbank anlegen falls nicht vorhanden
CREATE DATABASE booking_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Dann ein DB-Dump einspielen (vom Server oder Kollegen).

---

## 7. Frontend bauen

```powershell
# Entwicklung (mit Hot-Reload)
npm run dev

# Produktion (für Deployment)
npm run build
```

---

## 8. Funktionsprüfung

```powershell
# Tests laufen lassen
wsl php artisan test

# Konfiguration prüfen
wsl php artisan about
```

---

## Täglicher Workflow

```powershell
# Frontend-Watcher starten (läuft im Hintergrund)
npm run dev

# Tests nach Änderungen
wsl php artisan test

# Vor Deployment: Produktion bauen und committen
npm run build
git add public/build
git commit -m "build: Frontend aktualisiert"
```

---

## Bekannte Eigenheiten

- **PHP nie direkt in PowerShell** — immer `wsl php artisan ...`
- **`public/build/` ist committed** — nach `npm run build` die Build-Dateien committen (Deployment via FTP auf hosting.example.test)
- **Keine Schema-Migrations** — die App nutzt das Legacy-Schema von `booking_local`; Migrations in `database/migrations/` dienen nur als Referenz, nicht zur Ausführung
- **Deployment** — Vite-Build lokal bauen, gesamtes Projekt per FTP hochladen
