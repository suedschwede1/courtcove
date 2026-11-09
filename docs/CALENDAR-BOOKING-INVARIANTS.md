# Calendar booking invariants

## Occurrences and ownership

An action with an occurrence date must find that date in the current locked booking.
A missing date never expands an occurrence action to the entire series. An explicitly
unscoped action still addresses the full booking. Unchanged booker text preserves the
stored account and optional free-text owner even when aliases are ambiguous.

Irregular legacy series accept metadata and court updates while preserving reservation
IDs, dates, individual durations and reservation metadata. Schedule changes must use an
explicit single occurrence or a supported recurrence rule. New irregular schedules
cannot be generated from free-form input.

## Time and availability

Every active reservation is visible in every hour it overlaps, including reservations
shorter than an hour and adjacent reservations within one hour. Exact segment times
are visible; booking, cancellation and search dialogs use the actual interval.
Free gaps use precise start/end seconds. A gap that cannot accommodate the effective
minimum is unavailable to members. The normal hour-based no-JavaScript route remains
compatible, with optional precise seconds for fractional gaps.

End must be after start before minimum-duration expansion. Members may still book the
current calendar hour while its end is future, but cannot start in an earlier hour.
The short-booking override ends at the configured future boundary. Global and peak
future limits count ongoing and future occurrences, excluding completed ones.

## Events and concurrent writers

Enabled events and closures cannot overlap active public bookings. Administrators
receive the affected occurrence list and resolve those bookings explicitly before
saving the event again. No booking is silently cancelled and no notification is sent
by a rejected event write. An all-court event checks every court.

Event writes lock affected courts in ascending ID order inside the same transaction
as the event and metadata write. Booking creation uses the same court mutex. Booking
updates read conflicts with current locking reads, including events committed while
the edit waited for its court lock. Event and booking edit transactions retry deadlocks.

## Verification

- `tests/Feature/CalendarReviewRegressionTest.php`: HTTP and database behavior.
- `tests/Feature/CalendarBookingConcurrencyTest.php`: two independent MySQL sessions;
  requires an isolated database whose name starts with `bookingnew_review_` and group
  `mysql`. It recreates that disposable database and must never target application data.
- `tests/Browser/CalendarBookingRegressionTest.php`: member booking, edit and cancel,
  fractional-gap booking, phone target sizes and desktop alignment.
- Existing calendar, player-exchange, series, API, CSS and translation checks remain
  part of the full suite.
