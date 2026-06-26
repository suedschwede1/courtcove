# Laravel Migration Plan — example.test Booking System

## Ausgangslage

| Komponente | Anzahl | Komplexität |
|---|---|---|
| Controller | 15 | Mittel |
| Actions (Endpoints) | 69 | Mittel |
| Views (.phtml) | 79 | Mittel |
| View Helpers (custom) | 93 | **Hoch** |
| Services | 24 | Mittel |
| DB-Tabellen | 15 | Gering (bleibt identisch) |
| Mail Services | 4 | Gering |

**Kritischer Befund:** 93 custom View Helpers sind der aufwändigste Teil —
sie kapseln die gesamte Kalender-Rendering-Logik (Cell, Row, Table, Events,
Reservations, Pricing etc.).

---

## Architektur-Mapping ZF2/Laminas → Laravel

```
Laminas                          Laravel
─────────────────────────────────────────────────────
module/Backend/                → app/Http/Controllers/Backend/
module/Frontend/               → app/Http/Controllers/Frontend/
module/Square/                 → app/Http/Controllers/Square/
module/User/                   → app/Http/Controllers/User/
module/Calendar/               → app/Http/Controllers/Calendar/
module/Booking/                → app/Http/Controllers/Booking/

module/*/src/*/Service/        → app/Services/
module/*/src/*/Entity/         → app/Models/  (Eloquent)
module/*/src/*/Manager/        → app/Repositories/ oder app/Services/

module/*/view/**/*.phtml       → resources/views/**/*.blade.php
module/*/src/*/View/Helper/    → app/View/Components/ + Blade Directives

module.config.php (routes)     → routes/web.php
module.config.php (services)   → app/Providers/AppServiceProvider.php

config/autoload/               → config/ + .env
public/                        → public/ (1:1)
public/css-client/             → public/css-client/ (1:1)
public/js/                     → public/js/ (1:1)
```

---

## Phasenplan

### Phase 0 — Vorbereitung (1–2 Tage)

**Ziel:** Laravel-Projekt aufsetzen, DB verbinden, Grundstruktur.

```bash
cd C:\development
composer create-project laravel/laravel bookingnew
cd bookingnew
git init && git add -A && git commit -m "Initial Laravel setup"
```

**.env konfigurieren:**
```
APP_NAME="Example Club Reservierungssystem"
APP_URL=http://localhost:8081

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=booking_local
DB_USERNAME=booking
DB_PASSWORD=REDACTED
```

**Packages installieren:**
```bash
composer require laravel/breeze
composer require doctrine/dbal
composer require barryvdh/laravel-debugbar --dev
php artisan breeze:install blade
```

**Lokaler Dev-Server:**
```bash
php artisan serve --port=8081
# Laminas läuft weiterhin auf :8080
```

---

### Phase 1 — Datenbank & Models (2–3 Tage)

**Ziel:** Alle 15 bs_* Tabellen als Eloquent Models, kein Schema-Change.

**Kein Migrations-File nötig** — DB existiert bereits. Nur Models erstellen.

#### Eloquent Models (15 Stück)

```php
// app/Models/Booking.php
class Booking extends Model {
    protected $table      = 'bs_bookings';
    protected $primaryKey = 'bid';
    protected $fillable   = ['uid', 'sid', 'status', 'date_start', ...];

    public function user()        { return $this->belongsTo(User::class, 'uid', 'uid'); }
    public function square()      { return $this->belongsTo(Square::class, 'sid', 'sid'); }
    public function reservations(){ return $this->hasMany(Reservation::class, 'bid', 'bid'); }
    public function meta()        { return $this->hasMany(BookingMeta::class, 'bid', 'bid'); }
}

// app/Models/User.php
class User extends Authenticatable {
    protected $table      = 'bs_users';
    protected $primaryKey = 'uid';
}

// app/Models/Square.php
class Square extends Model {
    protected $table      = 'bs_squares';
    protected $primaryKey = 'sid';
    public function meta()    { return $this->hasMany(SquareMeta::class, 'sid', 'sid'); }
    public function bookings(){ return $this->hasMany(Booking::class, 'sid', 'sid'); }
}
```

**Alle Models:**
- `Booking` (bs_bookings)
- `BookingMeta` (bs_bookings_meta)
- `BookingBill` (bs_bookings_bills)
- `Reservation` (bs_reservations)
- `ReservationMeta` (bs_reservations_meta)
- `Square` (bs_squares)
- `SquareMeta` (bs_squares_meta)
- `SquarePricing` (bs_squares_pricing)
- `SquareProduct` (bs_squares_products)
- `SquareCoupon` (bs_squares_coupons)
- `Event` (bs_events)
- `EventMeta` (bs_events_meta)
- `User` (bs_users) — extends Authenticatable
- `UserMeta` (bs_users_meta)
- `Option` (bs_options)

---

### Phase 2 — Authentication (1–2 Tage)

**Ziel:** Login/Logout/Registration auf bs_users Tabelle.

```php
// config/auth.php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model'  => App\Models\User::class,  // zeigt auf bs_users
    ],
],
```

**User-Rollen (Admin/User):**
```php
// app/Http/Middleware/AdminMiddleware.php
class AdminMiddleware {
    public function handle($request, Closure $next) {
        if (!auth()->user()?->isAdmin()) abort(403);
        return $next($request);
    }
}
```

**Aus Laminas migrieren:**
- `SessionController::loginAction`              → Laravel Breeze LoginController
- `SessionController::logoutAction`             → Laravel Breeze LogoutController
- `AccountController::registrationAction`       → Laravel Breeze RegisterController
- `AccountController::activationAction`         → eigener ActivationController

---

### Phase 3 — Routing (1 Tag)

**Ziel:** Alle 69 Endpoints als Laravel Routes.

```php
// routes/web.php

// Frontend
Route::get('/', [FrontendController::class, 'index'])->name('frontend.index');

// Square Booking
Route::middleware('auth')->group(function() {
    Route::get('/square/{sid}', [SquareController::class, 'index'])->name('square.index');
    Route::post('/square/{sid}/booking', [BookingController::class, 'confirm'])->name('booking.confirm');
    Route::post('/square/{sid}/booking/cancel', [BookingController::class, 'cancel'])->name('booking.cancel');
    Route::get('/square/booking/players/{bid}', [BookingController::class, 'players'])->name('booking.players');
});

// User Account
Route::middleware('auth')->prefix('user')->group(function() {
    Route::get('/bookings', [AccountController::class, 'bookings'])->name('user.bookings');
    Route::get('/settings', [AccountController::class, 'settings'])->name('user.settings');
    Route::post('/password', [AccountController::class, 'password'])->name('user.password');
});

// Backend (Admin only)
Route::middleware(['auth', 'admin'])->prefix('backend')->group(function() {
    Route::get('/', [Backend\IndexController::class, 'index'])->name('backend.index');
    Route::get('/user', [Backend\UserController::class, 'index'])->name('backend.user.index');
    Route::get('/user/edit/{uid?}', [Backend\UserController::class, 'edit'])->name('backend.user.edit');
    Route::get('/user/delete/{uid}', [Backend\UserController::class, 'delete'])->name('backend.user.delete');
    Route::get('/booking', [Backend\BookingController::class, 'index'])->name('backend.booking.index');
    Route::get('/booking/edit/{bid?}', [Backend\BookingController::class, 'edit'])->name('backend.booking.edit');
    Route::get('/booking/stats', [Backend\BookingController::class, 'stats'])->name('backend.booking.stats');
    Route::get('/config/square', [Backend\ConfigSquareController::class, 'index'])->name('backend.square.index');
    Route::get('/config/square/edit/{sid?}', [Backend\ConfigSquareController::class, 'edit'])->name('backend.square.edit');
    Route::get('/event', [Backend\EventController::class, 'index'])->name('backend.event.index');
    Route::get('/event/edit/{eid?}', [Backend\EventController::class, 'edit'])->name('backend.event.edit');
    Route::get('/config', [Backend\ConfigController::class, 'index'])->name('backend.config.index');
    Route::get('/config/behaviour', [Backend\ConfigController::class, 'behaviour'])->name('backend.config.behaviour');
});
```

---

### Phase 4 — Services & Business Logic (3–5 Tage)

```php
// app/Services/BookingValidationService.php
class BookingValidationService {

    // range_book: letzter buchbarer Tag bis 23:59
    public function isWithinBookingRange(Carbon $date, Square $square): bool {
        $rangeBook = $square->getMeta('range_book');
        $maxDate = Carbon::now()->addSeconds($rangeBook)->endOfDay();
        return $date->lte($maxDate);
    }

    // time_block_bookable_max: Tageslimit pro User pro Platz
    public function getDailyUsage(int $uid, int $sid, Carbon $date): int {
        return Reservation::whereHas('booking', fn($q) =>
            $q->where('uid', $uid)->where('sid', $sid)->where('status', '!=', 'cancelled')
        )->whereDate('date', $date)->sum(DB::raw('time_end - time_start'));
    }

    // Short Booking: ignoriert Tageslimit wenn < 30min bis Start
    public function isShortBooking(Carbon $bookingStart): bool {
        return Carbon::now()->diffInMinutes($bookingStart) < 30;
    }

    // Spielersuche: 1 Spieler bucht Mehrspielercourt
    public function isSpielersucheBooking(int $quantity, Square $square): bool {
        $minPlayers = $square->getMeta('min_players', 2);
        return $quantity === 1 && $minPlayers > 1;
    }
}
```

---

### Phase 5 — Views & Kalender (5–8 Tage) ⚠️ Kritischster Teil

**Ziel:** 79 .phtml → Blade, 93 View Helpers → Blade Components.

#### Kalender als dedizierter Service

```php
// app/Services/CalendarService.php
class CalendarService {

    public function buildCalendarData(Carbon $date, Collection $squares): array {
        return [
            'date'      => $date,
            'squares'   => $squares,
            'timeSlots' => $this->generateTimeSlots(),
            'cells'     => $this->buildCells($date, $squares),
        ];
    }

    public function getCellClass(Carbon $date, int $timeSlot, ?Booking $booking): string {
        if (!$booking)                              return 'cc-free';
        if ($booking->uid === auth()->id())         return 'cc-own';
        if ($date->isPast())                        return 'cc-single';
        if ($this->isSpielersucheBooking($booking)) return 'cc-spielersuche';
        if ($booking->isSubscription())             return 'cc-multiple-future';
        return 'cc-single-future';
    }
}
```

#### Helper-Kategorien

| Gruppe | Anzahl | Strategie |
|---|---|---|
| Format-Helper (PrettyTime, DateFormat etc.) | ~40 | Blade Components |
| Inline-Helper (Translate, Plural) | ~20 | Blade Directives |
| Kalender-Rendering (Cell, Row, Table) | ~33 | CalendarService |

---

### Phase 6 — Mail (1 Tag)

```php
// app/Mail/BookingConfirmation.php
class BookingConfirmation extends Mailable {
    public function __construct(public Booking $booking) {}
    public function content(): Content {
        return new Content(view: 'emails.booking.confirmation');
    }
}
// Mail::to($user->email)->send(new BookingConfirmation($booking));
```

---

### Phase 7 — Custom Features (2–3 Tage)

| Feature | Laminas | Laravel |
|---|---|---|
| Spielersuche (purple cell) | CellLogic.php | CalendarService::getCellClass() |
| Player Autocomplete | BookingController::playersAction | BookingController::players() → JSON |
| range_book (23:59) | SquareValidator | BookingValidationService |
| time_block_bookable_max | SquareValidator | BookingValidationService |
| Short Booking (30min) | SquareValidator | BookingValidationService |
| Court Aliases | View Helper | config/courts.php |
| Sprüche-Pool | config/autoload/sprueche.php | config/sprueche.php (1:1) |
| Future Bookings blau | CellLogic | CalendarService::getCellClass() |
| Past Bookings (2 Wochen) | CellLogic | CalendarService |
| Player Names in Cell | Cell.php | CalendarCell Component |

---

### Phase 8 — Assets & CSS (0.5 Tage)

```bash
cp -r ../booking/public/css-client  public/css-client
cp -r ../booking/public/js          public/js
cp -r ../booking/public/imgs-client public/imgs-client
```

CSS-Klassen bleiben identisch: cc-free, cc-own, cc-single, cc-multiple,
cc-single-future, cc-multiple-future, cc-spielersuche.

---

### Phase 9 — Testing & Deploy (3–5 Tage)

**Checkliste:**
- [ ] Login / Logout funktioniert
- [ ] Kalender zeigt korrekte Daten
- [ ] Buchung anlegen (single + subscription)
- [ ] Buchung stornieren
- [ ] Spielersuche (purple cell)
- [ ] Autocomplete Spielernamen
- [ ] Admin Backend: User, Bookings, Courts, Events
- [ ] Mail-Versand (Bestätigung)
- [ ] Mobile Darstellung
- [ ] Varnish-Cache Bypass testen

---

## Gesamtaufwand

| Phase | Aufwand |
|---|---|
| 0. Setup | 1–2 Tage |
| 1. DB & Models | 2–3 Tage |
| 2. Auth | 1–2 Tage |
| 3. Routing | 1 Tag |
| 4. Services | 3–5 Tage |
| 5. Views & Kalender | 5–8 Tage ⚠️ |
| 6. Mail | 1 Tag |
| 7. Custom Features | 2–3 Tage |
| 8. Assets | 0.5 Tage |
| 9. Testing & Deploy | 3–5 Tage |
| **Gesamt** | **~4–8 Wochen (Teilzeit)** |

---

## Repo-Setup

```
C:\development\
├── booking\       # Laminas — Prod (aktiv bis Migration fertig)
└── bookingnew\    # Laravel — dieses Repo
    ├── .env                # Lokal: booking_local DB
    └── MIGRATION-PLAN.md   # dieser Plan
```

Beide Repos teilen dieselbe DB (bs_* Tabellen) — kein Datenmigrations-Aufwand.
