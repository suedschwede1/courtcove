# Setting up the development environment

## Prerequisites

| Tool | Version | Install location |
|------|---------|-----------------|
| Windows 11 | — | Host |
| WSL 2 (Ubuntu) | — | Windows feature |
| PHP | 8.3 | WSL |
| Composer | 2.x | WSL |
| Node.js | 20+ | Windows (native) |
| npm | 10+ | Windows (native) |
| MySQL | 8.x | Windows or WSL |
| Git | — | Windows |

> **Important:** PHP and Composer run **exclusively via WSL**, never directly in PowerShell.

---

## 1. Set up WSL

```powershell
# PowerShell as administrator
wsl --install -d Ubuntu
```

After the restart, open Ubuntu and install PHP + Composer:

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.3 + required extensions
sudo add-apt-repository ppa:ondrej/php
sudo apt install -y php8.3 php8.3-cli php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-mysql php8.3-zip php8.3-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Verify
php -v
composer --version
```

---

## 2. Clone the repository

```bash
# In PowerShell or Git Bash
git clone <repo-url> C:\development\bookingnew
cd C:\development\bookingnew
```

---

## 3. Install PHP dependencies

```powershell
# In PowerShell — commands run via WSL
wsl composer install
```

---

## 4. Install Node dependencies

```powershell
# In PowerShell (Node runs natively)
npm install
```

---

## 5. Environment configuration

```powershell
# Create .env
copy .env.example .env   # or create it manually

# Generate the app key
wsl php artisan key:generate
```

Adjust `.env`:

```env
APP_NAME="Tennis Club Booking"
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

## 6. Set up the database

The app runs against the **real legacy database** `booking_local` — do not run migrations that change the schema.

```sql
-- In MySQL: create the database if it doesn't exist
CREATE DATABASE booking_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import a DB dump (from the server or a colleague).

---

## 7. Build the frontend

```powershell
# Development (with hot reload)
npm run dev

# Production (for deployment)
npm run build
```

---

## 8. Sanity check

```powershell
# Run the tests
wsl php artisan test

# Check the configuration
wsl php artisan about
```

---

## Daily workflow

```powershell
# Start the frontend watcher (runs in the background)
npm run dev

# Run tests after changes
wsl php artisan test

# Before deployment: build production assets and commit them
npm run build
git add public/build
git commit -m "build: update frontend assets"
```

---

## Known quirks

- **Never run PHP directly in PowerShell** — always `wsl php artisan ...`
- **`public/build/` is committed** — commit the build files after `npm run build` (deployment via FTP to hosting.example.test)
- **No schema migrations** — the app uses the legacy schema of `booking_local`; migrations in `database/migrations/` are for reference only, not for execution
- **Deployment** — build the Vite assets locally, upload the whole project via FTP
