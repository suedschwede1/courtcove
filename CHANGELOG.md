# Changelog

This project does not use semantic version numbers (it is deployed continuously via FTP
to a single shared-hosting instance, not released as versioned packages). Instead, this
file groups notable user-facing and structural changes by date. The only tagged
reference point is `mobile-view-stable` (see [README.md](README.md#layout-reference)).

When adding an entry: describe the *why*/user-facing effect in one line, not the diff —
`git log` already has that. Add new entries at the top, under today's date.

## 2026-11-05
- Pyramid: members without admin rights get a 403 (not the admin shell) on unknown admin ids, the not-found page links to the calendar when no ladder exists, the admin phone bar respects the iPhone safe area and the public view stays reachable on phones.
- Members pages now use the shared admin shell: Mitglieder and Mitglieder-Einstellungen entries in the admin sidebar, module sub-pages as tabs; the separate members sidebar is gone.
- Pyramid admin on phones: compact participant rows with one pause/activate action (name opens the edit area), back arrow with pyramid eyebrow in the admin bar and a fixed "Teilnehmer anlegen" bar; desktop keeps the table.
- Pyramid: challenge, player profile and challenge confirmation get the phone top bar of the mockups (back arrow and title in place of the site header on phones); desktop unchanged.
- Pyramid: an unknown ladder, player or challenge shows a friendly not-found page with a way back (status stays 404; the administration gets it in the admin shell).
- Pyramid: the result form shows live whether the entered score is valid and who won (same check as on saving; without JavaScript the form still checks on submit).
- Tournament config (live-DB guard name, court names) moved to config/tournament-base.php so env values survive config:cache; static analysis is clean again.
- Phase 2: Mixed roster entries with a placeholder birth date (e.g. 1900) no longer break adding participants; implausible ITN values in the member export are ignored.
- Phase 2 go-live (module 3): /mixed-tournament is served by Laravel; rollback = re-add the path to the bridge allowlist.
- Phase 2: Mixed scorecards and blank scorecards as A4 print pages (page break per wave, no wave number).
- Phase 2: phase2:scrub-staging replaces Mixed data and the member roster with fictional demo events.
- Phase 2: Mixed role-by-route permission matrix pins every route against guest, reader, score, admin grant, test-mode and global admin roles.
- Phase 2: phase2:preflight mixed checks columns, charset, schema version, unique keys, the grant foreign key, orphans and data rules.
- Phase 2: Mixed round creation, proposal check, player swap and result entry in the new design.
- Phase 2: Mixed admin forms refuse over-long titles and names, out-of-range ITN and court counts, and oversized or non-text CSV files with a message instead of a server error.
- Phase 2: Mixed participant management and configuration in the new design; the member picker searches on demand instead of embedding the whole roster.
- Phase 2: Mixed admin overview with to-dos, event creation and member roster import in the new design.
- Phase 2: Mixed round (Americano/Mexicano), standings and Durchmischung pages in the new design.
- Phase 2: Mixed overview, help and event pages in the new design (German/English).
- Phase 2: /mixed-tournament routes registered in Laravel (same URLs); the path leaves the ZF2 bridge with the deploy of this branch.
- Phase 2: Mixed round config can be resubmitted unchanged (no false state error on MySQL); an invalid score is reported before the round state.
- Phase 2: creating a Mixed round and its proposal is atomic; a failed proposal leaves no empty round.
- Phase 2: Mixed round lifecycle ported; player swap is now refused on Mexicano rounds.
- Phase 2: Mixed round preview (repeated encounters from the second repeat, avoid-pair collisions, match counts) ported unchanged.
- Phase 2: Mixed participants, avoid pairs and the member roster ported; the roster import now runs in one transaction.
- Phase 2: Mixed events, default event, court count, test mode and per-event grants ported.
- Phase 2: Mixed roles (read/score/admin, test mode, per-event grants) ported; admin.mixed is grantable in the user admin.
- Phase 2: Mixed tables adopted in place (guarded create migrations, no-op rollback, schema manifest).
- Phase 2: Mixed member CSV import parsing (UTF-8/Windows-1252, BOM, columns by name) ported.
- Phase 2: Mixed wave planning and scorecard contents ported unchanged.
- Phase 2: Mixed standings ported; equal points averages are now broken by games average and then participant id (the old code skipped the games average when an exact int 0 met float 0.0) instead of database row order.
- Phase 2: Mexicano pairing and fair sit-out rotation ported unchanged.
- Phase 2: Americano round proposals ported unchanged.
- Phase 2: Mixed group building ported unchanged (sizes, distributions, extra group, repair).
- Phase 2: Mixed rotation and Durchmischung maths ported unchanged (golden files from the old code).
- Phase 2: MixedTournament module skeleton (provider, German/English texts).
- Logo: the header and phone menu show the design's tennis-ball mark on the clay circle by default instead of initials; a logo file is used only when `BOOKING_LOGO_PATH` (or the admin option) names one.
- Court closures are their own calendar state (hatched, spanning their hours): the event form has an "Art" select (Veranstaltung / Platzsperre), older events named "Platzsperre"/"Sperre" still count as closures; booking cells no longer repeat the owner's name in the detail line.
- Calendar cells use a calm pastel palette: my booking is pale blue with a blue ring (no more red), other members' bookings sand, subscriptions lilac, events soft green, tournament matches amber with a thin ring; hover only strengthens the ring. The legend gains the court closure state, and on phones the day name is clay with a "Heute" pill instead of the "Heute" suffix.
- Calendar: the large date heading above the day cards is gone on desktop (the cards name their dates; the h1 stays for screen readers), the date controls stay right-aligned in one row.
- Calendar fills exactly one screen: the 12 hour rows share the height under the toolbar and grow with the window (min 42px desktop, 44px phone, the grid scrolls below that), multi-hour events stay one block, today's card gets a clay ring and the legend moves into the Infos panel next to the contact, imprint and privacy links (the calendar page has no footer row); the date controls stay right of the heading on every desktop width.
- Header bar spans the full width in one 72px row with the calendar's 24px gutter (header content and calendar share their left/right edges, no inset above the bar); tournaments collapse into a keyboard-accessible "Turniere" dropdown and Mitglieder moves under Administration, so the nav never wraps; below 1024px the phone menu takes over.
- Pyramid admin ladder page: the ladder chips list only ladders the user may administer (a single-ladder grant holder no longer sees category, season or player count of foreign ladders).
- Pyramid public pages follow the mockups more closely: abbreviated player names (K. Huber), one chip per category/season, bold 'A – B' pairings with short dates, challenge and player pages with a back bar instead of the ladder header, beige avatar, readable disabled Fordern button, shorter phone hints, layout tokens instead of arbitrary Tailwind values.
- Pyramid: scheduling a date after the deadline flashes the info notice before the confirmation, as in ZF2.
- Pyramid: a logged-in user who is not a participant is no longer told to log in (side card and cell hints), /pyramide/0 shows the default ladder like ZF2, a broken date in a stored schedule event no longer breaks the ranking page, and the player's wins/losses are counted over all matches instead of the last 50.
- Phase 2 go-live: .htaccess comment names the one rule needed for the Pyramid rollback; the htaccess test router now checks directories and refuses conditions with flags.
- Pyramid admin: changing the status of a participant that disappeared meanwhile reports an error instead of a success message.
- Pyramid admin: the copy-participants dropdown lists only ladders the user administers and the card disappears when there are none.
- Pyramid admin: editing a participant works without JavaScript (edit link), the member search is rate limited and an internal spec reference is gone from the overdue text.
- Pyramid admin: the overview counts ranked players of all ladders in one query, and a new ladder needs a season between 2000 and 2100.
- Pyramid: late sign-up stores player and join event in one transaction and a double click shows the already-registered notice instead of an error.
- Member picker for Pyramid administrators no longer offers placeholder and blocked accounts (still no phone numbers).
- Pyramid mails carry an optional Reply-To from MAIL_REPLY_TO_ADDRESS / MAIL_REPLY_TO_NAME (none when unset).
- Pyramid: a ladder can only be closed once no challenge is open or scheduled; the refusal lists them and the admin page disables the close button with the same note.
- Pyramid: only global Pyramid admins can change which ladder is the default; for ladder admins the checkbox is hidden and the field ignored.
- Pyramid: ladder rights can only be granted and revoked by global Pyramid admins (ZF2: any ladder admin); holders of a single ladder no longer see the grants card.
- Phase 2 go-live (module 2): the challenge ladder /pyramide is served by Laravel with the Laravel login; rollback = re-add the prefix to the bridge rule.
- Fix: links styled with Tailwind utilities (Pyramid ranking, rules menu) no longer turn clay because of the legacy link rule; it now sits in a cascade layer below the utilities.
- Phase 2: Pyramid overdue decisions, result corrections, settings, season close and grants in Laravel.
- Fix: Pyramid admin rejects granting rights to a non-existent member, a blank ladder title and over-long correction score/note, and closing a season twice no longer logs a second event.
- Front-end bundle rebuilt for the new login and account pages.

- Auth layout: the sticky court panel no longer covers the form just above 900px, and only the sign-in page hides its intro on phones.
- Login, registration, password and account pages address members with "du".
- My account uses the new cards and fields; every field is now linked to its label.
- The page header title is 40px like the design mockup instead of 32px.
- My bookings uses the new cards, status badges and filter form; on phones each booking is a card.
- The clay court panel stays viewport-high beside tall forms such as registration on wide screens.
- Registration uses the new auth layout with grouped sections; old login stylesheet rules removed.
- A long club name stays on one line with an ellipsis on the phone band and wraps to two lines on the desktop panel instead of crowding the court lines.
- Password reset and login confirmation pages use the new auth layout.
- New login page: clay court panel, larger fields and a dark full-width sign-in button.
- Login and account access pages get a shared split layout with the clay court panel (used from the next commits on).

- Phase 2: Pyramid seeding, waiting list, player status and edits administered in Laravel.
- Phase 2: the unique-key preflight check is shared by Draw and Pyramid, and the Pyramid preflight gained fault-injection tests and a stricter read-only guard.
- Phase 2: read-only php artisan phase2:preflight pyramid checks columns, charset, unique keys, orphans and ranking gaps.
- Phase 2: phase2:scrub-staging replaces Pyramid data with a fictional demo ladder.
- Tests and code quality: PHPStan findings fixed with accurate model typing, each PHPUnit directory registered once, Dusk selectors fixed and visual tests skipped without baselines, Pint style applied.

- Phase 2: Pyramid ladder administration page; the member picker now searches on demand instead of embedding all members.
- Phase 2: Pyramid administration overview and ladder creation in Laravel.
- Phase 2: Pyramid challenge page (date, result, decline, withdraw) and self-registration in Laravel.
- Phase 2: Pyramid player profile and "Fordern" flow in Laravel.
- Phase 2: public Pyramid ladder page in Laravel (not yet routed by .htaccess).
- Phase 2: Pyramid rules page and searchable challenge list in the new design.
- Phase 2: login return path keeps the /booking prefix; expired-form notice only redirects within the site; member search treats % and _ literally.
- Phase 2: tournament modules send guests to the Laravel login with a return path and show expired forms as a notice.
- Phase 2: Pyramid adoption, lock order and writer races checked against a real MySQL server; no behaviour change.
- Phase 2: more Pyramid tests (ranking order, rule boundaries, forfeits, admin notes); no behaviour change.
- Phase 2: Pyramid admin notes are stored trimmed (as in ZF2); empty dates render as a dash.
- Phase 2: Pyramid mails (challenge, date, result, decline, withdrawal, deadline, forfeit, expiry, correction, admission, entry release) sent from Laravel.
- Phase 2: Pyramid mails now carry a greeting with the player name, a club signature with website line and are addressed by name, like the booking mails.
- Phase 2: Pyramid administration (seeding, waiting list, moves, normalising, deadlines, forfeits, corrections) ported unchanged.
- Phase 2: Pyramid results and forfeits move the ladder exactly as before.
- Phase 2: Pyramid challenge rules (positions, open-challenge lock, winner protection, loser cooldown, entry) ported unchanged.
- Phase 2: Pyramid admin rights (global right or per-ladder grant) ported to a Laravel policy.
- Phase 2: Pyramid data access ported; feed events keep ZF2's JSON and German wording.
- Phase 2: Pyramid rules (challengeable positions, shifts, seeding) ported unchanged, checked against golden files from the old code.
- Phase 2: Pyramid tables adopted in place (guarded create migrations, additive email column, no-op rollback, schema manifest).
- Phase 2 go-live (module 1): the public draw record pages /draw/record/* are served by Laravel; the rest of /draw stays on ZF2.
- Phase 2: public draw record page in the new design (German/English), same content and reveal rule as ZF2.
- Built assets updated for the new calendar.
- The phone header logo link is a full 44 px tap target and the calendar time column keeps its spacer heights.
- Free calendar cells are real links with a spoken label; own, booked, subscription, tournament, event and past slots get the new look; old calendar styles removed.
- The calendar shows the selected day and the next two as cards with court aliases and numbers and a time column; phones show one day with a sticky court row.
- The calendar shows a legend of all cell states, and the help texts describe the new colours.
- The calendar date, day arrows, date picker and Infos/Hinweise now sit in a toolbar above the plan instead of under the header.
- Calendar colours default to the new design tokens; CAL_COLOR_* and the local override file still win, tournament matches get their own colour keys.
- The feedback dialog closes by itself after four seconds again, and the close button of the admin popup closes its dialog.

- Old booking dialog styles removed; the new booking flow ships in the built assets.
- The booking and edit pages without JavaScript use the same tiles and buttons as the dialog.
- Admin event form and booking popup use the new dialog; the popup fills the screen on phones.
- Own bookings open a details view with Bearbeiten, Stornieren and Abbrechen; editing uses the same tiles as booking.
- After booking, the sheet shows "Platz gebucht" with the quote and links to the calendar and to my bookings.
- Booking opens as a dialog on desktop and a bottom sheet on phones, with Einzel/Doppel as tiles and a summary of date, time and court.
- Success and error messages use the new dialog (sheet on phones) and close after 4 seconds unless you interact with them.
- Shared dialog component: centred dialog on desktop, bottom sheet on phones, keeps focus inside and locks page scroll.
- Admin area shipped on the new design with a rebuilt asset bundle.
- Database page shows a wrong migrate confirmation as an inline error under its field.
- Texts, behaviour and database admin pages in the new cards; destructive actions marked as such.
- Event and court forms (also in the calendar popup) use the new cards, fields and date/time order.
- Admin lists for bookings, events and courts use the new tables with card rows on phones.
- Login history, API tokens, test mail and booking detail use the new admin look.
- Admin booking create page has its own title and breadcrumb; the phone bar and page are inert while the admin menu is open; dashboard dates follow the language.
- Admin member create and edit forms in section cards with clear save, password and delete areas.
- Admin member list with search card, status badges, initials and card rows on phones.
- Admin booking form in cards (who and where, when, note) with tiles for players, status and series scope; works the same in the calendar popup.
- Admin overview and statistics show key figure tiles and card lists on phones in the new style.
- Admin dialogs opened from the calendar get the dark title bar and a 44 px close button.
- Admin pages use the new shell; on phones the admin menu slides in behind a menu button.
- Admin sidebar in the new dark style with groups (Betrieb, Mitglieder, Einstellungen) and a clay marker for the current page.
- Admin navigation is built from one grouped list; tournament entries appear only once their pages exist.
- Members area restyle complete: committed build contains the module styles.
- Mail send confirmation keeps keyboard focus inside the dialog and returns it to the send button; subject and body errors are announced with their fields.
- Mail settings and club function forms use the shared fields and buttons.
- Mail composer uses audience chips, cards and a labelled confirmation dialog that closes with Escape.
- Fiscal year and privacy settings forms use the shared fields.
- Dues page uses the new table, labelled filters and a bulk bar; the status message no longer appears twice.
- Member and payment forms use the shared fields with labels and error text linked for screen readers.
- Member edit page uses the new header actions, dues card and change history; partial payments are highlighted without inline colours.
- Members dashboard shows key figures and age/category bars in the new style; fiscal years, functions and fee categories use the shared table.
- Members list uses the new table, badges and filters; on phones each member becomes a card with status, payment and category still editable in place.
- Members area gets the dark admin sidebar with grouped links, a phone menu behind one button and the new page header.
- Members module: inline saves read the CSRF token from the page head and show a translated error message.
- Legacy element rules use zero-specificity guards so existing buttons, links and inputs keep their original cascade.
- Admin and Members pages show their prefix in the browser tab again even when a view sets its own title.
- Legacy element rules (links, text inputs, touch-action) no longer restyle the new ds components, which now carry their own base font size.
- Phones get a full-screen menu behind one button instead of the "..." actions list; no bottom bar.
- Header switch no longer animates its knob.
- New dark header with main menu, admin mode, language and account; date navigation moves to a bar below until the calendar toolbar arrives.
- Navigation items are built once for the header and the phone menu; logo falls back to the club initials.
- Tab counts stay readable on the active phone tab.
- One shared tab bar for module sub-pages; chips on phones.
- Shared table component: header row, highlighted rows and card layout on phones.
- Shared form fields with visible labels, help and error text linked for screen readers.
- Shared card, key figure and notice components of the new design.
- Status badges use one shared colour map (good, neutral, attention, problem, you).
- Shared button components with 46/52 px touch targets.
- Saving a legacy series with irregular dates (repeat "custom") is now rejected with a clear message instead of silently collapsing it to one booking; such series can still be viewed, deleted or switched to once/weekly/every 2 weeks.
- Removed the legacy "names of other players" setting from the square admin form; the stored `capacity-ask-names` value is left untouched for the old system.
- New repeat bookings are limited to once, weekly and every two weeks (the club no longer offers daily/monthly/custom); existing legacy series with other intervals are still shown, editable (incl. single-occurrence and this-and-following) and deletable; the REST API and MCP tools follow the same rule (create limited, update of a legacy series still accepted).
- Removed unused price, product and coupon code from the Laravel app (legacy tables and bill cleanup on delete are unchanged).

- Fix saving on the full-page admin "edit booking" form: it posts the players as `player_name_2..4`, but the controller only accepted `mitspieler`, so every save failed with "mitspieler is required". The controller now accepts both field sets and the three players are stored separately.

## 2026-11-04

- Tests: the draw record page tests also cover closed token draws and hidden seeds of unrevealed draws.

- Tests: the test-mode session test no longer uses a helper name that collides with the test method naming.
- Phase 2: php artisan phase2:scrub-staging (staging only) replaces module data with fictional demo draws.
- Phase 2: PHASE2_LIVE_DB_DATABASE must be set on the staging host (name of the live database); phase2:scrub-staging refuses to run without it.
- Phase 2: admin "view as player" test mode state ported (used by the Tournament module).
- Tests: the legacy baseline test ignores the guarded draw adoption migrations, which are not part of the 15-table baseline.
- Tests: MySQL tests now hard-fail unless they target the local booking_test database (no DB_URL, local host), so a stray environment can never reach real data.
- Tests: composer test:mysql runs MySQL-only adoption and preflight checks.
- Phase 2: read-only php artisan phase2:preflight draw checks columns, ENUMs, charset, unique keys, orphans and recomputes every draw.
- Phase 2: draw preflight now reports duplicate counts even when a unique index is missing.
- Phase 2: draw:verify --compare-dir proves the Laravel JSON equals the ZF2 JSON for every captured live draw.
- Phase 2: shared WhatsApp link and court name helpers for the tournament modules.
- Phase 2: match score rules (best of three, match tie-break) ported unchanged with German/English messages.
- Phase 2: /draw/record/{slug}/json implemented in Laravel with the same keys, reveal rule and no-cache headers (not yet routed by .htaccess).
- Phase 2: shared module route group helper (`ModuleRoutes::middleware()`), module routes rely on the global 4-byte stripping.
- Phase 2: php artisan draw:verify {slug?} --all recomputes commitment, seed and assignment of stored draws.
- Phase 2: draw adoption tests now pin column types, plain indexes, no date casts and the existing-table guard for all three tables.
- Phase 2: draw tables adopted in place (guarded create migrations, no-op rollback, schema manifest).
- Phase 2: token draw helpers ported unchanged (golden vectors).
- Phase 2: stored draw payloads are checked to be exactly canonical JSON.
- Phase 2: Draw module with a byte-exact port of the commit/reveal draw maths (golden vectors from the old code).
- Phase 2 groundwork: golden draw/token/score vectors captured from the old services (`tests/Fixtures/draw`, regenerable with `generate_vectors.php`) plus the public `tools/verify_draw.py` verifier, so the port can be checked byte for byte.
- Phase 2 groundwork: shared TournamentBase module (config with environment-supplied court names, translations, injectable Randomizer).
- Shared SVG icon set for the new design (no icon font, no emoji).
- English members area no longer falls back to German for the short booking-system label.
- The UI linter now checks the new design rules (tokens, 46/52 px controls, no inline styles, emoji or character icons) across all views, module views and CSS.
- All pages share one page frame (fonts, styles, head) instead of five copies.
- Site-wide switch to the new palette and fonts on existing pages, with a visible keyboard focus outline everywhere.
- Fonts are served from the app itself (Barlow); no request to an external font service.
- Design tokens of the new look (sand, clay, court, Barlow scale) defined in one place; dark values prepared, not switched on.
- Fix HTTP 500 when `public/` is served through a symlink (Apache + PHP-FPM keeps `__DIR__` unresolved): `public/index.php` and `public/zf2-bridge.php` now derive the application root from `realpath(__DIR__)`, so vendor, bootstrap, maintenance file, `zf2.path` and the default ZF2 dir are found at the real location.
- Emoji stripping no longer touches password/token fields (an emoji password was silently altered) and skips the utf8mb4-only API member/due endpoints.
- Emoji and other 4-byte characters are stripped from request input (web and api) before they reach the utf8mb3 legacy tables, which previously failed with an HTTP 500; the Members module (utf8mb4) is exempt and keeps emoji.
- Bridge guard hardening: the direct-request check now uses the raw request URI (query cut off, decoded until stable, slashes collapsed; no `parse_url`), so `//zf2-bridge.php/x`, `///zf2-bridge.php/x` and encoded forms answer 404, and `public/.htaccess` additionally 404s any raw request line containing `zf2-bridge`.
- Tournament bridge hardening: direct requests to `zf2-bridge.php` (including `/zf2-bridge.php/x` PATH_INFO forms) answer 404; the default ZF2 dir (`../zf2/public`) applies only to an app directory named `bookingnew` (staging needs `zf2.path`/`ZF2_PUBLIC`, else 503); `zf2.path` must be absolute; allowlisted paths with a trailing slash are 301-redirected to the slashless URL; the deploy script follows the same default/trim rules.
- Docs: the cutover design, runbook, README and AGENTS now describe the same-host tournament model (ZF2 install in `~/apps/zf2`, `zf2.path`, asset linking, routing test table) instead of the `turnier.` subdomain.
- Deploy script links the legacy ZF2 static assets (css, js, imgs, vendor, ...) into `public/` after extracting (ZF2 dir from `zf2.path` or `../zf2/public`; skipped with a note when missing), so the same-host tournament pages are styled.
- Tournament buttons now link to the same host (`/pyramide`, `/mixed-tournament`, `/tournament`, honoring the `/booking` base path); `TOURNAMENT_BASE_URL` is empty by default and only needed for a separate tournament host. The `/user/login|logout|password[-reset]` redirects are gone because ZF2 serves those paths.
- Tournament pages stay on the same host: `public/.htaccess` routes `/tournament`, `/mixed-tournament`, `/pyramide`, `/draw`, `/service` and the ZF2 login/logout/password pages through the new `public/zf2-bridge.php` to the legacy ZF2 app (located via `ZF2_PUBLIC`, an untracked `zf2.path` file, or `../zf2/public`); everything else stays with Laravel.
- Deploy script: re-deploys no longer fail on stale generated `bootstrap/cache` files (now untracked and deleted before any artisan call), `.env.dusk*` are no longer shipped, the wipe keeps `lang/de/booking/quote_groups.php`, and new `--first-deploy` / `--skip-build` flags make the documented first-deploy order possible (without `--first-deploy` the script refuses when the server DB has no migrations table).
- Legacy ZF2 URL redirects are now temporary (302) instead of 301, so a rollback to the old system is not poisoned by cached permanent redirects, and `/user/password`, `/user/password-reset`, `/user/registration` and `/user/activation[/...]` are covered too.
- Added a guarded `sessions` table migration so `SESSION_DRIVER=database` (the production setting) works after `migrate`; the composite meta-table index migration is now re-runnable after a partial failure.
- Fixed a 500 on login with "remember me" for normal members: `bs_users` has no `remember_token` column, so the User model no longer persists one.
- Fixed the booking modals, player autocomplete and member/dues inline editing so their requests honor the `/booking` URL base path (they used root-absolute `/bookings/...` and `/members/...` URLs and would have hit the wrong host path in production).
- Restored `public/.htaccess` (front-controller rewrite, Authorization passthrough) that the Laravel scaffold had dropped; without it every route except `/booking/` returned 404 on Apache.
- The deploy script no longer deletes `*.sqlite*` files, refuses a server `.env` with `DB_CONNECTION=sqlite`, and aborts before extracting if cleaning the old files fails.
- Hardened the (local, untracked) deploy script: refuses non-`master` branches without `--allow-branch`, asks for confirmation, requires the server `.env`, and runs the deploy in maintenance mode (stale files removed, caches cleared, site left down if a step fails).
- Added temporary (302) redirects for the old ZF2 URLs (`/user/login`, `/user/logout`, `/user/bookings`, `/user/settings`, `/square/...`, `/backend/...`) and a tar-over-ssh deploy script (kept locally) that keeps the server `.env` and `storage/`; README/AGENTS deployment docs rewritten accordingly.
- Added `db:baseline-legacy [--dry-run]`, which records the `create_bs_*` migrations as applied for legacy tables that already exist, so `migrate --force` can run against the production database created by the old system without recreating tables.
- Highlighted club championship bookings (note contains "Vereinsmeisterschaft") in orange in the calendar, on desktop and mobile, as the legacy system did.
- Added tournament entry buttons (current championship, challenge pyramid, mixed/fun tournament) to the logged-in header, linking to the tournament pages that stay on the legacy system (`TOURNAMENT_BASE_URL`, defaulting to the legacy host when unset or empty).
- Added tournament privileges (admin.tournament/mixed/pyramid/draw) to the user admin so the legacy tournament modules' access flags can be granted and kept when saving a user.

## 2026-07-12

- Added `members:import-nuliga-license`, a command that backfills the `nuliga_id` on
  existing members from the official nuLiga membership export (matched by name +
  birthdate), treating the export as authoritative and overwriting conflicting existing
  values. Supports `--dry-run` to preview changes without saving.

## 2026-07-07

- Hardened the API/MCP event endpoints so square-specific events now reject unknown court IDs instead of accepting invalid `bs_squares.sid` values.

## 2026-07-06

- Documented in the README why `app/Modules/*` (homegrown, web-admin UI sections) and
  `Modules/*` (nwidart/laravel-modules, stateless feature modules like the API/MCP layer)
  are two separate, unrelated conventions — the near-identical naming kept causing
  confusion about which one to use for a new self-contained feature.
- Added an MCP (Model Context Protocol) server at `POST /api/v1/mcp` (via `laravel/mcp`),
  exposing the same booking/event/member/dues create+edit actions as MCP tools for LLM
  clients, authenticated with the same Sanctum tokens as the REST API. Each tool checks
  the token user's `admin.*` privilege itself before acting.
- Added create/edit JSON API endpoints for bookings, events, members, and dues under
  `/api/v1/...`, reusing the same validation and business logic as the admin panel (the
  repeat/conflict-check logic moved from `Admin\BookingController` into `BookingService`,
  and the date-combination/meta-sync logic from `Admin\EventController` into a new
  `EventService`, so both the admin panel and the API share one implementation).
- Removed the `/api/v1/auth/logout` endpoint (and its now-empty `AuthController`) —
  tokens are only ever revoked by an admin via `/admin/api-tokens`, not by the client.
- Added a versioned JSON API (`/api/v1/...`, self-contained module under `Modules/Api` via
  nwidart/laravel-modules) authenticated with Laravel Sanctum personal access tokens. Tokens
  are issued/revoked only through a new admin-only "API-Tokens" page (`/admin/api-tokens`,
  new `admin.api-tokens` privilege) for a chosen member — there is no public login endpoint,
  so external integrations get a token handed to them by an admin.
- Fixed a JS syntax error on the member detail page (`window.x = window.x || async (...) => {}`,
  invalid without parens around the arrow function) that silently broke every inline save
  on the page — Beiträge status/Bezahlt-bis/Teilbetrag/Kommentar edits and member field edits
  all failed with no visible error.
- Login-Historie: added stat tiles (successful/failed/throttled, today, this month)
  and a per-country breakdown to the admin page — all respect the currently applied
  filters (user/status/country/date range).
- Fixed risky login history handling so a suspicious login only becomes a trusted
  successful login after the email code is verified — the login_history row is now
  written after code entry succeeds, carrying the ip/country/risk flags captured at
  password-check time, instead of being recorded as `success` before verification.
- Added a risk-score column to the admin Benutzer (users) list so admins can spot
  risky accounts while browsing/filtering, without opening each user individually.
- Fixed the overdue member-dues filters so they include rows explicitly marked
  as overdue, not only rows computed overdue from a past due date.
- Added a standalone "Login-Historie" admin page (own sidebar entry) listing login
  events across all users, searchable/filterable by user, status, country, and date
  range — previously only viewable one user at a time from their edit page. Also
  surfaced the risk score directly on the user edit page instead of only on the
  per-user history page.
- Fixed the admin database-drift check reporting false positives on
  bs_squares.priority, member_changes.admin_user_id, and
  club_role_mail_category — the checker's manifest had drifted from what the
  migrations actually create.
- Fixed: adding a Beitrag from a member's detail page redirected to the general
  dues list instead of back to the member, making the save look like it had
  failed.
- Password reset emails now use the configured club/service name in the subject,
  matching the name shown in the booking UI.
- Documented the new risk-based email verification in the README's Security section
  (was only in CHANGELOG/code, undersold the current auth posture).
- Login now requires a 6-digit email code when it looks risky (unusual country,
  2+ recent failed attempts, or impossible travel within 24h) — normal logins are
  unaffected. Backed by a new 30-day `login_history` table. Country-based checks
  fail open when the MaxMind GeoLite2 file is missing. Admins can view a user's
  login history read-only from their edit page, including a display-only risk
  score (30-day sum of fired triggers) and which flags fired per login.
- Password reset: rate-limited the reset-password page (`GET /password/reset/{token}`,
  `throttle:20,1`) to match the throttling already on the other "forgot password" routes,
  and added tests confirming a reset token can't be reused after a successful reset and
  that an expired token is rejected.
- Password reset: added the "set new password" flow — `GET/POST /password/reset`
  (Task 5 of "forgot password" feature). A valid, unexpired token from the emailed
  link lets a user set a new password and logs them in; an invalid token or unknown
  email both show the same generic error, to avoid account enumeration. Replaces the
  temporary placeholder route added in Task 4.
- Password reset: added the "request a reset link" flow — `GET/POST /password/forgot`
  (Task 4 of "forgot password" feature) — users can request a reset email; the response
  is identical whether the email is unknown or the account is disabled, to avoid account
  enumeration. `GET /password/reset/{token}` is a temporary placeholder route (404) so
  the link generated here resolves a route name; Task 5 replaces it with the real
  set-new-password page.
- Password reset: added translation keys for forgot password and password reset pages
  (Task 3 of "forgot password" feature) — adds `password_reset` language group with
  all user-facing text for the two new pages.
- Password reset: added `PasswordReset` mailable and email template (Task 2 of
  "forgot password" feature) — sends users a 60-minute-valid reset link.
- Rate-limited `POST /register` (5/min per IP) — it had no throttling at all, unlike
  `/login` which already has a custom per-email/IP/account limiter.
- Extended the risk-based email verification spec with a display-only, per-user
  "risk score" on the admin login-history page — a 30-day sum of which triggers fired,
  no behavior change.
- Wrote the implementation plan for risk-based email verification — see
  the risk-based email verification plans document (kept locally, not in the repository).
  Updated to also persist which risk flags fired (for the display-only admin risk
  score). Not implemented yet.

## 2026-07-05

- Wrote a design spec for risk-based email verification on login (unusual country,
  repeated failed attempts, impossible travel) — see
  the risk-based email verification specs document (kept locally, not in the repository).
  Country-based checks fail open when the MaxMind GeoLite2 file is missing. Not
  implemented yet.
- Documented the security posture in the README: no MFA (deliberate — login must stay
  fast for a booking system), compensated by existing login rate limiting and
  country-based geo-blocking (`BOOKING_BLOCKED_LOGIN_COUNTRIES`).
- A `pre-commit` hook (`.githooks/pre-commit`) now blocks commits that touch
  code/behavior-relevant files without a matching CHANGELOG.md entry, so this file stays
  complete. Enable it once per clone: `git config core.hooksPath .githooks`.
- Club roles can now be tagged with multiple mail categories (many-to-many), replacing
  the earlier one-to-one `mail_category_id` link.
- `DatabaseSchemaChecker` manifest extended to cover all current tables/columns, plus a
  regression test that fails when a migration adds a table/column the checker doesn't
  know about yet.

## 2026-07-08 – 2026-07-10

- Members: added a free-text Notiz field, visible only on the edit/detail page.
- Beiträge (dues): the "bezahlt bis" fiscal year used by bulk-mark-paid is now
  configurable instead of hardcoded.
- Dashboard: charts arranged side by side; added a Mitglieder-pro-Beitragskategorie
  breakdown.
- Mail: added per-member send history, dynamic (role/category-based) audiences, and a
  GDPR settings page.

## Earlier

- Introduced the Members module (`app/Modules/Members`): member CRUD, fiscal years, fee
  categories with per-year rates, dues generation/tracking, club roles (Funktionen), and
  a bulk email composer with templates.
- Established the shared design system (see [docs/DESIGN.md](docs/DESIGN.md)) and
  tagged the stable calendar/header layout as `mobile-view-stable`.

For full detail, use `git log` — this file only tracks what's worth knowing at a glance.
