# Reviewed viewport references

No references are committed yet: the earlier captures showed the pre-restyle UI and
were not carried over. Capture them with `UPDATE_DUSK_SNAPSHOTS=1` (see `../README.md`),
inspect every PNG and record browser, OS, locale and device scale here.

| Files | Size requested | Content |
|---|---|---|
| `design-controls-desktop.png` | 1440 × 1000 | Shared controls in the `/_ui` gallery |
| `design-controls-mobile.png` | 390 × 1000 | Gallery on a phone; visible buttons and inputs at least 52px |
| `design-dialog-mobile.png` | 390 × 844 | Gallery dialog on a phone; Escape closes it and returns focus |
| `calendar-desktop.png`, `calendar-mobile.png` | 1440 × 900 / 390 × 844 | Calendar with seeded bookings and an event |
| `booking-modal.png` | 1024 × 768 | Booking dialog |
| `login-desktop.png`, `login-mobile.png` | 1440 × 900 / 390 × 844 | Login |
| `account-desktop.png`, `account-mobile.png` | 1440 × 900 / 390 × 844 | Account page |
| `admin-desktop.png`, `admin-mobile.png` | 1440 × 900 / 390 × 844 | Admin dashboard (current-date eyebrow masked) |

The actual PNG viewport can be shorter than the requested browser window; the
comparison also checks the exact PNG dimensions. Fixtures use synthetic users, fixed
branding without an external logo and fixed dates. Local translation or calendar
overrides must be absent for capture and compare.
