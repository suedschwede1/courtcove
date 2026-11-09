# Changelog

This project does not use semantic version numbers (it is deployed continuously via FTP
to a single shared-hosting instance, not released as versioned packages). Instead, this
file groups notable user-facing and structural changes by date. The only tagged
reference point is `mobile-view-stable` (see [README.md](README.md#layout-reference)).

When adding an entry: describe the *why*/user-facing effect in one line, not the diff —
`git log` already has that. Add new entries at the top, under today's date.

## 2026-11-09

- Sign-in panel claim is now the product tagline from `booking.product_tagline` ("Your club. Your court. Your game.") in both languages, replacing the translated two-line claim; the small tagline under the form is dropped as a duplicate.
- LICENSE clause 2 (German and English): use by non-profit clubs for their own operation (membership, court and tournament fees) is expressly permitted; sale, rental, paid hosting/SaaS and inclusion in paid products stay prohibited; other commercial use needs written permission.
- License block: `composer.json` license is `proprietary` (matches LICENSE), LICENSE is bilingual (German authoritative), upstream copyright line includes the year, contact address spelling corrected, third-party notice, README license paragraph.
- CourtCove brand block (name and tagline) under all sign-in forms and " – CourtCove" in their tab titles, both from `booking.product_name`/`product_tagline`; tennis-only wording and a Credits section (ep3-bs) in the README.
- Product name CourtCove (tagline: Your club. Your court. Your game.): one config entry `booking.product_name`, README/AGENTS/composer/docs retitled, "Powered by CourtCove" line on the sign-in pages, calendar info card label, `APP_NAME` default and `.env.example`. The club name in the header and mails is unchanged.
- Calendar booking review: preserve series on stale occurrence actions and ownership on unchanged ambiguous names, show every partial-hour reservation and usable gap, retain irregular-series schedules on metadata edits, enforce time/limit boundaries, and reject event closures over active bookings with a resolution list and shared court locks.

- Push guard hardened after review: binary-safe matching, term list validation (CRLF/BOM, invalid or empty-matching entries refuse), merge commits checked against the first parent, paths containing listed terms withheld in findings.
- Push guard: `.githooks/pre-push`, `pre-commit` and `commit-msg` check added lines, messages, author identity and file names against a local term list (fail-closed without it; findings show file:line and entry number only) plus generic secret, key, file-name and public-IP rules; multi-ref pushes need `BOOKING_GUARD_ALLOW_MULTI=1`; runbook and process tests added.

- Integrate reviewed member privacy, role audit, tariff import, payment consistency and mail outcome fixes without replacing Stage household billing, cancellation, closed-year rules or the mobile design; add annual preview and regression coverage.

- Design documentation brought up to date: header menu with module dropdown, sidebar per module, module overview and registry, two-day calendar board, WhatsApp account card, security page.
- Assets rebuilt over the whole tree for the modules overview grid (.ds-module-grid).
- Menu: the running championship is looked up once per request instead of up to three times (menu, module overview, sidebar); nothing is kept between requests, so a created or finished championship shows at once.
- Championship: the admin page title, the sidebar entry "Leitung" and the login hint now say "Meisterschaft" (en "Championship"); unused menu texts removed. The module overview card grid is one column on phones (CSS source only, asset build pending).
- Modules: the developer guide `docs/modules.md` ("new module in 10 steps") describes how a module registers; a module right that clashes with a core or another module's right is refused at registration.
- Modules: the menu entry and administration group "Turniere" are now "Module" (new overview page `/modules`); the club championship is called "Meisterschaft" in menu and administration. Modules register themselves in a registry, so a new module appears in menu, overview, administration group and rights list without touching the core navigation (see `docs/modules.md`). URLs are unchanged; the module order in menu and overview is now Pyramid, Meisterschaft, Mixed.
- API: the `/api` path now answers only to the client addresses in the server setting `API_ALLOWED_IPS` (comma separated); every other address gets 403, an empty setting refuses everyone, forwarding headers are ignored. Calendar, sign-in and the webhooks are not affected. Set the value in the server `.env` before an API client connects.
- Secure sign-in with device cookie is configurable: new admin page "Sicherheit" (Einstellungen, right admin.config) with four levels (Aus, Nur Admins, Admins und Bearbeiter = admins, assistants and members-right holders, Alle Benutzer); precedence .env > admin value > default off (BOOKING_TRUSTED_DEVICE_SCOPE now empty by default and accepts off|admins|privileged|all, a value there locks the page); widening shows a hint that affected users confirm one e-mail code at their next sign-in; saving a level that covers the configuring admin needs a confirmed device of theirs (the page offers "Dieses Gerät jetzt bestätigen"); changes are logged; `php artisan security:set-device-scope <level>` sets the admin value; runbook updated. Note: without .env value and admin value the scope is now off (before: privileged), set it once where the old default was relied on.
- WhatsApp card: the row no longer takes focus when its anchor is opened after switching (the browser drew a focus ring around the whole row); the scroll margin stays.
- WhatsApp card after the final visual check: switching shows a short inline status in the row ("Eingeschaltet." / "Ausgeschaltet, deine Einwilligung ist widerrufen.", polite live region) instead of the modal success dialog, refusals likewise at the row; the switch in the off state is an outlined track with a dark knob (contrast above 3:1, still recognisable when locked); rows keep a scroll margin when the anchor is opened; unambiguous dates per language (en "9 Nov 2026"); English intro reworded; a missing number carries the profile link in its reason box.
- WhatsApp card switch review fixes: switching on or off (and a refusal) lands back on the purpose's row (anchor, row is a focus target); the switch sends the version of the consent sentence it shows and an outdated or missing version is refused without storing anything ("Der Text wurde aktualisiert, bitte erneut einschalten"); the changed-number notice offers switching on again only while the number allows it.
- WhatsApp card: switching on or off is one click. The row switch is the consent (POST form with the switch as submit button, works without JavaScript): a short consent sentence per purpose stands right under it, the full text behind "Mehr erfahren"; switching on stores the consent at once (time, purpose, text version, number hash, language) and subscribes the opt-in types without filters, switching off withdraws at once. No consent box, tick, confirm button or switch-off question any more; filters stay optional in a fold-out. New consents of the player exchange carry text version `wa-2026-11-v2` (short sentence); existing `wa-2026-11-v1` consents stay valid. Purpose definitions get an optional `consentShortKey`.
- WhatsApp card review fixes: the STOP notice offers switching on again only while account and number allow it; "Abbrechen" puts the focus back on the row switch; a row that is on without any subscribed type opens its settings with a hint to choose; the registry reports purposes without an opt-in type (checked by a test, noted in the runbook); tests for odd panel queries and that the panel query changes no state.
- Assets rebuilt over the whole tree (without the old build) for the card switch style and row spacing of the WhatsApp card.
- WhatsApp account card is general: one card with a general intro, the number section once for every purpose (masked number, "must be your own", profile link; a missing, invalid or non-mobile number or an inactive account is said there once and locks every switch) and a list "Benachrichtigungen" with one row per registered purpose (title, short text, real switch). Switching a row on folds out that purpose's consent text (required tick, "Bestätigen"); switching it off asks first and withdraws the purpose's consent. Filters sit in a fold-out per row, only where a type defines them; several types of one purpose get a box each. STOP or a changed number shows once above the list when it concerns every row. One common privacy and STOP note under the list; the searching person's name moved into the player exchange row text. Works without JavaScript (the switch is a GET for the row's panel). No "WhatsApp aktivieren" buttons any more; new card switch style `ds-switch--card`.

## 2026-11-08
- Members: a departure now blocks the linked booking account (status blocked, as when an admin blocks it by hand), so WhatsApp consents are revoked, sign-in stops, the player-exchange mails and WhatsApp messages stop, and trusted devices and sessions end. It applies at once when the departure date is today or past, from the date on for a future date (new daily task `members-deactivate-left`, `members:deactivate-left-accounts --since=14`), and when a member is anonymised (before the link is removed). Accounts with an admin or assistant role or the members right are never blocked automatically; they show as a warning on the member page, the member list and the members dashboard. After a re-entry the account stays blocked; the member page and dashboard offer one click "Konto wieder aktivieren", only for accounts this logic blocked (marker in user meta `departure.deactivated`, turned into `departure.released` whenever an admin lifts the block, so a plain save or the daily run does not block the account again until the member departs anew; a manual "blocked" in the user form clears both). Anonymising a protected account tells the admin it stays active. Protocol actions `account_off` / `account_on`, dashboard card "Konten ausgetretener Mitglieder". One-off command for existing departures: `members:deactivate-left-accounts --dry-run`, then without the flag (ids and counts only). Replaces the earlier rule that anonymising leaves the account alone.
- WhatsApp review fixes for the generic card and admin page: a purpose is offered (shown and open for consent) only while one of its subscribable types is on, a follow-up alone does not count; with a consent but no type on the card only offers withdrawal. Admin: each type switch names its type, one hint per type lists every language without a template, price and template errors show under their field, last-sent time in Vienna time and the date format of the language. Consent text in the card: core sentence bold, rest normal, checkbox at the top. Choice filters validate with a quoted rule; the follow-up helper of the settings is gone; runbook names the deliberate exceptions and the purpose gate condition.
- WhatsApp account card and admin page are generic: the account page shows one WhatsApp card per consent purpose (own consent text and version, own gate, opt-in and withdrawal; URLs /my-account/whatsapp/{purpose}, the old URLs stay for the player exchange) with a switch per message type and filters only where the type defines them (validation derived from the definition). The admin page has a card per message type with on/off switch (replaces the separate follow-up switch), template IDs per language, the template variables, an optional own price and the sending figures, plus a hint when a language has no template. Runbook section on adding a new WhatsApp type.
- WhatsApp registry hardening: the admin follow-up switch now reads and writes the type option of "search closed" (the old option is only a read fallback and is removed on save); the gate refuses unregistered purposes and broken gate conditions (checked at registration too); revoking or STOP on a purpose that is no longer registered deletes all of the user's subscriptions; the admin test asks for the consent of its type's purpose; every registered test type stays out of the monthly message count.
- WhatsApp notifications are now extensible: one central registry (NotificationRegistry) holds a definition per message type (label, template key, resolver, purpose, default switch, filters, priority, follow-up, opt-in, test flag) and per consent purpose (texts, consent version, gate prerequisite); a new type needs only its registration, a resolver and its texts. Each type can be switched off (option whatsapp.type.<key>.enabled; queued rows are then skipped as "type switched off") and can carry its own price estimate (whatsapp.price.<key>, default the global one); consent, revocation and STOP work per purpose; per-type send figures (month, last 30 days) for the admin. Existing consents, log rows and the follow-up option keep working; player search behaviour unchanged.
- Staging mail protection: on a staging database (name ends in _staging, same rule now shared in App\Support\StagingDatabase) every outgoing mail (mailables, notifications, queued, raw) goes only to MAIL_STAGING_REDIRECT with a [STAGING → original] subject prefix and X-Original-To/Cc/Bcc headers; with a real mailer and no valid address nothing is sent (log warning without addresses); live databases are untouched. A blank or undeterminable database name now also counts as staging for WhatsApp; the name check ignores case. New command mail:staging-check [--send] and docs/mail-runbook.md (staging uses the same sender accounts as live).
- WhatsApp: the gate now looks at the booking account only (enabled, valid mobile number, player exchange on, consent, main switch); a linked or active member record is no longer required, and leaving, dormancy, unlinking, deleting or anonymising a member record neither revokes nor pauses a consent (the member observer is gone; blocked, disabled or deleted accounts still revoke). The admin test skips only the exchange check, so an admin without a member record can test. Prune no longer drops data of living accounts with an anonymised member record. Exchange mail recipients unchanged; runbook notes that a departed member keeps an active account unless it is blocked.
- WhatsApp: no "search closed" follow-up to members who joined that search themselves; the opened message takes the booking id from the listener instead of reading the search per recipient; runbook go-live items for the event cache after deploy and the open join suffix decision.
- WhatsApp account card: errors of every weekday and player-count choice are shown, the privacy link only appears for an http(s) address, and the weekday tiles carry the full weekday name for screen readers.
- WhatsApp: a new player search is now also sent by WhatsApp to exchange members with consent (same recipients as the mail, narrowed by consent, gate, switch and filters, fewest messages this month first), due after a 2-minute debounce and re-checked at send time; the exchange mail is unchanged and WhatsApp works without a mail server. Optional "search closed" follow-up behind whatsapp.followup_closed (off).
- Assets rebuilt from the tracked tree only (the previous build carried two stray utilities from files outside it).
- Assets: rebuilt `public/build` over the whole tree for the WhatsApp account card.
- WhatsApp admin test message: the refusal for a missing consent now links to the account card, and the runbook drops the Tinker workaround (consent is given in the card; Task 10 still follows).
- Account page: new card "WhatsApp-Benachrichtigungen" (consent per purpose with the v1 text and a masked number, player search switch with weekday/time/singles-doubles filters, withdrawal with revoked_via account, STOP and changed-number notices, refusal texts for every gate reason with a profile link); hidden while the main switch is off.
- Sign-in security for admins, assistants and holders of the members right: they must sign in from a trusted device. A browser without a valid device cookie takes the existing e-mail code step after the password and is trusted for 30 days (fixed, no renewal) once the code is right; the owner gets a "new device confirmed" mail. Devices are listed and removable under My account, and end on password change, rights change, removal of the user or "remove all". Risk checks still ask for a code on trusted devices; a new code can always be requested on the code page (only waiting times, no lock; the attempt counter is server-side); a password reset by mail link also confirms the current browser; changing password, rights or e-mail address ends other sessions. Settings `BOOKING_TRUSTED_DEVICE_DAYS` and `BOOKING_TRUSTED_DEVICE_SCOPE` (privileged / all / off); emergency command `security:reset-devices`; see docs/security-runbook.md.
- WhatsApp branch files normalised to mode 644 (no behaviour change).
- WhatsApp phone parsing drops a trunk zero after +43/+49 (+43 (0) 660 ... now counts as mobile), the admin switch check uses a closure transaction, and tests use only fictional 660 0000xxx numbers.
- WhatsApp sandbox mode now requires the sandbox sender before sending; the staging scrub also clears sandbox, webhook-base and provider-literal options; runbook corrected (test message needs admin consent, provider literals are .env only, go-live checks for the webhook route).
- WhatsApp final review fixes: the calendar's lazy notification drain now runs after the response is sent (never blocks the page), and the admin change log line records the real admin id.
- WhatsApp texts review fixes: version test now checks the stored consent version, privacy sentence test checks provider-free wording, runbook debounce note limited to player-search messages.
- Staging scrub: the WhatsApp scrub also deletes the budget alert address and the pause reason from a live copy, and sets driver and main switch in one transaction.
- WhatsApp: consent text v1 (wa-2026-11-v1) and privacy sentence in de/en, and the operations runbook (cron, env keys, staging flow, go-live checklist, items for the club to confirm); no provider is named in tracked files.
- Staging scrub: phase2:scrub-staging now also runs tagged config scrubs; the WhatsApp one deletes the credential and sender options, sets driver log and the main switch off, and empties the notification tables (output lists names only). New runbook docs/whatsapp-runbook.md with the scrub section.
- Assets rebuilt over the whole tracked tree after the WhatsApp admin pages (stylesheet hash changed).
- WhatsApp send log: card without duplicate title, long raw values no longer overflow on phones.
- Assets: rebuilt `public/build` for the WhatsApp send log mobile fixes.
- WhatsApp admin: send log filters as selects, unknown values shown raw and wrapped on phones, log-driver hint no longer red, usage figure keeps its percent sign.
- Assets: rebuilt `public/build` for the WhatsApp budget bar style.
- Notifications: new admin page "WhatsApp send log" (`admin/whatsapp/protokoll`, right `admin.config`, no admin mode, linked from the WhatsApp page header and its warning): time, type, user alias (linked, none for deleted accounts), status badge, reason or error code and estimated cost per message, filter chips for status, type and month, 25 per page, cards on phones. Only the last two phone digits are shown; no texts, full numbers or provider IDs.
- Notifications: WhatsApp budget bar is styled (court green, amber from 80 percent, red at 100 percent), the skip reason of a refused test message is shown translated, and the file modes of the WhatsApp page files are restored.
- Notifications: the WhatsApp admin page accepts the "fake" driver only while it is already stored, and switches the main switch on inside a transaction that is rolled back when the readiness check fails, so the dispatcher never sees a half-enabled state.
- Notifications: the WhatsApp admin page now has a connection test (reads account and service only, shows ok or an error code, stores nothing; with the log driver or without credentials it only says so, no API call), a test message to the admin own stored number (needs the admin active consent and the admin-test gate, no number field), a budget block for the current month with a bar and the previous month, notices from 80 percent and at 100 percent, and a resume button after a paused channel; the admin dashboard shows the same budget and pause hints while sending is on. Throttled to 5 per minute.
- Notifications: new admin page "WhatsApp" (Settings group, right `admin.config`, no admin mode): access data (account ID and service ID shown masked, auth token write-only and stored encrypted, empty input keeps the stored value, an unreadable stored secret shows a re-enter hint), driver, main switch (only with budget and price, with the `live` driver also credentials and provider config), test mode, budget, price, daily limit, warning recipient, default language and country code and the template IDs per message type and language; a value from `.env` locks its field. Changes are logged as one default-channel line (admin id, time, field names, never values); the page warns about failed or template-less sends of the last 7 days. Provider literals stay `.env`/config only; the page just says when they are missing.
- Notifications: WhatsApp webhooks are throttled before the signature check (rejected requests cannot flood the log), links in the reply texts are built from the configured webhook base URL instead of the request host, and the CSRF test now also proves the webhook route passes under a non-testing environment.
- Notifications: WhatsApp webhooks `POST /webhooks/whatsapp/status` and `/inbound` (the only routes without session and CSRF, throttled to 300 per minute): the signature (HMAC-SHA1 over the configured webhook base URL plus path and the sorted raw body pairs, header name and token from the settings) is checked first and a missing setting, header or mismatch is a 403 with a log line that names only the reason. Status updates `notification_log` by SID and never moves backwards (it also resolves `sending` rows and rows the stale check failed); unknown SIDs are ignored. STOP, STOPP, ABMELDEN and UNSUBSCRIBE revoke every active consent of that number (hash match) and are answered in the response body, START only points to the account page, anything else gets one help reply per number and day; no budget is used and no number is logged. Reply texts in de/en.
- Notifications: the WhatsApp dispatcher is scheduled (notifications:dispatch every minute, notifications:prune daily at 03:50) and the calendar also drains a few due rows at most once a minute, never breaking the page; retention: log rows 12 months, revoked consents 3 years after revocation, subscriptions and log user link removed for deleted or anonymised accounts (monthly usage stays). Without cron sending is delayed because rows only become due after the debounce period.
- Notifications: the WhatsApp dispatcher never fails, frees or resends a message the provider has accepted: the SID and `sent` are stored first and the budget is settled separately (an error is logged, the row stays sent); if even storing fails the row stays `sending` and the stale check resolves it. A pause set by a parallel run puts the next row back to `pending` and ends the run; stale rows are settled after their row transaction has committed.
- Notifications: the WhatsApp budget creates its month row outside the locking transaction and retries a transaction after a deadlock, so parallel reservations cannot deadlock on InnoDB; the budget alert mail now has render tests for both languages and both levels.
- Notifications: WhatsApp outbox channel and dispatcher. A notification only writes one `pending` row per dedupe key (IDs only, no name, text or number); the dispatcher claims due rows one at a time (row lock that skips rows of a parallel worker), re-checks main switch, pause, subject, consent, gate, number and the daily limit (Vienna day, admin test exempt) at send time, reserves the budget, rebuilds the text from the subject and sends outside the claim. Retryable errors back off 1, 5 and 15 minutes (never up to the subject deadline), permanent errors fail, a provider login error pauses sending, and rows stuck in `sending` for 5 minutes are marked `stale` instead of being sent twice. Admin test message text added (de/en); groundwork, not scheduled yet.
- Notifications: monthly WhatsApp budget (BudgetService) that reserves the estimated price under a row lock before each message, so parallel workers cannot pass the budget, settles accepted and rejected messages, and mails the admin once at 80 % and once at 100 % (recipient: budget alert address, else the booking Reply-To address); groundwork, not used anywhere yet. New WHATSAPP_SANDBOX key listed in .env.example.
- Notifications: WhatsApp connection check only queries the messaging service when one is configured; the staging check fails closed (unknown database counts as staging); empty template variables are sent as `{}`.
- Notifications: WhatsApp consent with a gate (main switch, enabled account, linked active member, mobile number, player exchange; the admin test skips the member rules), opt-in that stores only a hash and the last two digits of the number plus the notice version, and revocation: a changed or removed number (account page, admin user form, any phone write), member exit, anonymisation, unlinking or deleting the member and a blocked, deleted or disabled account end the consent at once; dormant members and leaving the player exchange only pause it. Changes are logged with IDs and reason only.
- Notifications: WhatsApp transports (log, fake, live over the HTTP client) with a connection check and an error table; the live driver is downgraded to log on a `_staging` database unless sandbox mode or WHATSAPP_ALLOW_LIVE_ON_STAGING is set; groundwork, not used anywhere yet, no real sending.
- Notifications: WhatsApp settings (config/whatsapp.php, WhatsAppSettings, SecretOptions) with .env > admin option > default resolution and encrypted secret options; the provider URL, paths and signature header name have no default and must be configured; groundwork, not used anywhere yet.
- Notifications: four new tables (consent, subscriptions, outbox log, monthly usage) with models and schema checker entries, groundwork for the WhatsApp notifications and not used anywhere yet; the log keeps IDs only, no message text or phone numbers.
- Phone numbers: new strict E.164 parser (PhoneNumber class) with a mobile check for +43 and +49, groundwork for the WhatsApp notifications and not used anywhere yet.
- Members, Anschlussmitglied: an Anschlussmitglied leaving (Austrittsdatum on the form or in the list) now also ends its connection automatically, logged on both and shown as a notice on the Vollzahler's page instead of the former warning; categories and open dues stay unchanged. A re-entry restores no connection.
- Members: recording a payment ("Zahlung erfassen" and the bulk "Als bezahlt markieren") now writes the change protocol of the payer and of every member it covers: amount, payment date, covered dues (year, category, household), a short note and, for a bulk run, a shared "Sammelzahlung" id; shown readably in German and English on the member page.
- Members dashboard: the full-width last block now has its own style rule (spacing above), assets rebuilt.
- Members dashboard: the "Recently changed" card is now its own full-width block at the very end of the page, outside every grid, so it stays at the bottom on phones and desktop even when cards or columns are added; the other three cards share one grid.
- Members, Anschlussmitglied (club decisions): any current member can be connected to any other (Pensionisten and other full-paying categories too); categories are no longer checked, each member simply pays its own category rate, and only data rules stay (no self link, no chain, one partner, no Familientarif). When the Vollzahler leaves, its Anschluss links end automatically, logged on both and shown on the partner's page (category unchanged). New "Rollen tauschen" on the member page swaps the two after a confirmation; categories and open dues stay unchanged.
- Assets rebuilt after merging the player exchange switch (stylesheet hash changed).
- Test housekeeping: the join dialog markup test no longer counts the buttons of the players dialog that follows it.
- Calendar board on medium widths: the board is now a size container; below the three-day minimum (980px of board width) it shows two days (selected day and the next) instead of scrolling sideways with the third day cut off. Phones (< 48rem) still show one day, wide boards three. Step size, arrows and date picker are unchanged.
- Calendar: player search and series bookings no longer rely on two similar violets. A search cell gets a 2px violet ring and a person-plus symbol before its "Sucht noch N" text; a series cell gets a bar on its left edge; the legend lists the series state too ("Abo / Serie") and the swatches match.
- Player lists: legacy rows with several names in one field ("Helga, Sandra, Inge") are split into single names centrally (comma, semicolon, slash, "und"), so lists and the "+N" count are right in the calendar, dialogs and lists. The phone cell shows full names on up to two lines ("+N" after four); the title lists everyone.
- Players of a booking in every list: one model helper (other players without the booker, short first names) feeds the calendar cell, the My bookings list (new column), the admin dashboard table (new column) and the booking confirmation mail (teammates row); admin bookings list and detail already showed them. Members only, guests get no names.
- Calendar on phones: a booked cell shows the players as first names under the name (at most three, then "+N"; own bookings show them instead of "Meine"), for logged-in members only like the desktop detail line. Tapping someone else's booking opens a dialog with the booker and all players; the cancel dialog of an own booking lists them too. Guests still see no names.
- Members dues list: "Als bezahlt markieren" for many dues at once. Tick rows (or "Alle der aktuellen Filterung auswählen", resolved again on the server, at most 500 rows), choose the payment date (today by default, never in the future) and an optional note, confirm count and sum; every due gets its own real payment like "Zahlung erfassen" (cancellable), household dues once by the payer, each in its own transaction with the fiscal-year lock. Already paid, other-year, closed-year and unknown rows are skipped and listed with the reason. "Bezahlt bis" keeps the existing status-only action.
- Members: "Zahlung stornieren". The member page lists the member's payments (and payments of household dues covering the member) with date, amount, covered years and status; a booked payment can be cancelled after a confirmation with an optional reason. The payment stays as "Storniert" (who, when, reason), its dues count as unpaid again everywhere (open amounts, "Beitrag offen", bezahlt bis, overview, Rundmail audience) and are set to "Ausstehend" so they can be paid again; refused in a closed fiscal year and logged in the change protocol of the payer and the affected members.
- Members detail page, card Beiträge: the status of the covering household dues (Haushalt rows) can now be changed there too, like the member's own dues, so a paid due can be set back to Ausstehend on the member page (closed years stay locked, "Bezahlt am" is cleared). A due linked to a payment from "Zahlung erfassen" shows that payment in its row; setting it back to not paid keeps the payment and says that the due still counts as paid while the payment exists.
- Player exchange: a switch in the header and in the phone menu turns it on and off with one tap (same opt-in and opt-out as the checkbox in My account, consent recorded with source menu, a short message after the tap). Build rebuilt.
- Player exchange: joining no longer needs the detour via My account. A member outside the exchange who taps a player search now gets the join dialog with the consent notice and one button (join the exchange and play); ticking Seek players in the booking dialog shows a notice and joins the exchange with the booking. The consent is stored with time, source and notice version; My account keeps the checkbox, every e-mail keeps the unsubscribe link. The separate hint dialog is gone.
- Booking dialog: a reply that is not JSON (maintenance mode during a deploy, expired session) now says so instead of the generic save error, and validation messages are shown. An unexpected failure while booking is logged (exception class and location, court, date, number of players; no names or messages).

## 2026-11-07
- Rebuilt the compiled assets after merging the player exchange with the household and connection work, so the committed build matches the sources again.
- "Jetzt ausführen" on Geplante Aufgaben only raises a shorter PHP time limit for the run and restores it afterwards; it no longer sets a fixed 120-second limit that ended a plain `php artisan test` run two minutes after the scheduler tests.
- Security update within the current versions: symfony/http-foundation, http-kernel, routing (8.1.11) and mime (8.1.10), guzzlehttp/guzzle 7.16.1 (with psr7 2.13.2, promises 2.6.0), league/commonmark 2.10.7, league/flysystem 3.36.0; `composer audit` reports no advisories any more. New tests prove that script tags, event handlers and javascript:/data: links in a Rundmail body stay inert in the preview and in the sent mail.
- Admin mode now counts only for calendar and bookings (calendar view, booking, booking limits, foreign bookings, events in the calendar: /calendar, /bookings*, /admin/bookings*, /admin/events*, API bookings and events). Members, all tournament modules with their admin pages and the sidebar group Einstellungen, and the administration under /admin (users, courts, options, scheduler, database, statistics ...) check the stored right and open for an authorised account without the admin mode cookie; the middleware admin.mode stays only on the booking and event admin routes. Ladder and event grants are unchanged.
- German validation messages for the two rules Laravel 13.40 added (array_keys, base64), so every rule has a German text.
- Maintenance: Laravel framework 13.17 -> 13.40, Sanctum 4.3.3, Pint 1.32.1, Dusk 8.7.2, PAO 1.1.5 and Laravel Prompts 0.3.26 (bug fixes within the same major versions; no other package moved).
- Members: Anschlussmitglied review fixes: a departed Anschlussmitglied is reported on its Vollzahler, anonymising an Anschlussmitglied is also logged on the Vollzahler, a Familientarif household can still be renamed (only a tariff change is checked), category rows are locked while connecting.
- Members: the dues run bills Anschlussmitglied and Vollzahler from their own categories as before and lists every Anschlussmitglied problem in its report (nothing is changed); the export gets the column "Verbunden mit".
- Members: member page card "Anschlussmitglied · optional" ("Verbunden mit: …" on the Anschlussmitglied, "Anschlussmitglied: …" on the Vollzahler, warnings, connect to a Vollzahler, remove the link); the category page sets the role Vollzahler/Anschluss (badge in the list, locked while connections use it).
- Members: Anschlussmitglied rules in one service: a member with the Anschluss category is connected to exactly one Vollzahler (no self link, no chains, at most one Anschlussmitglied, not together with a Familientarif household), logged on both members; invalid states produce warnings and are never changed silently; anonymisation removes the link.
- Members (Anschlussmitglied groundwork): additive columns members.connected_to_member_id and fee_categories.connection_role (Vollzahler / Anschluss); the categories "Vollzahler" and "Ehepartner/in, Lebensgefährte/in" are marked once, nothing else changes.
- Booking dialog error: scrolled and focused only after it is drawn, so at phone width it now really ends above the footer (checked in the browser at 375 px). A disabled select in admin forms (e.g. the locked repeat of a booking with a player search) looks like the read-only fields next to it. Build rebuilt.
- Editing an own booking whose player search was ended without anyone signed up asks for a partner again (before, the booking could be saved with neither partner nor search). New tests: a blocked or inactive member in the exchange cannot join, and a member who did not book cannot end a search over HTTP.
- Booking dialog: a server error is scrolled into view and focused when it appears (at phone width it was hidden under the footer) and disappears when Einzel/Doppel or the player search choice changes. Cancel dialog of an own booking with an open search or joined players says what cancelling does: the search ends and everyone who joined gets an e-mail. de/en text.
- Admin edit of a booking with a running player search or joined members: a notice explains the lock and lists the joined members, "Gebucht für", date, time and repeat are shown read-only, and the searched places may stay empty (no browser "required" on the player names, the server accepts empty names while the search binds the booking), so the admin can save e.g. a court change. The server lock (admin_edit_locked) stays. de/en texts.
- A member signed up to a player search that is full or was ended by the booker can leave again until one hour before the start: his cell (shown as a normal booking) opens the join dialog with "Austragen" and says "Du spielst mit" to screen readers; other members still see a plain booking. The dialog hides "Noch N Mitspieler gesucht" when nobody is sought. de/en text.
- "Mein Konto": after a validation error (e.g. an invalid e-mail) the "Spielerbörse" box shows what was submitted; an unticked box no longer comes back ticked for a member in the exchange.
- Player exchange dialogs and cells: the screen-reader name of someone else's search cell starts with the booker's name for members (guests still hear "Belegt" only); the search count select in the booking dialog is linked to its help text (aria-describedby); the "Mitspieler suchen" checkbox no longer submits a dead form field. Tests cover the booking without JavaScript (success flash with search), the 60/61-minute boundary of the search lead time, the join payload of a member already signed up and the refusal text in the join dialog.
- Admin cancel of a booking that was already cancelled (e.g. lifted by its player search meanwhile) now says "Diese Buchung ist schon storniert oder abgelaufen" instead of "Buchung storniert". A test proves the lapse after a cancel lifts nothing and sends no expiry mail.
- Player exchange Verfall in web requests (calendar load, before a join) waits for a held booking lock only once instead of three times, so a busy moment costs at most one lock wait timeout; the scheduler task `player-exchange:expire` keeps its three attempts (proven on MySQL).
- Player exchange mails: the date in subject and slot table follows the language the mail is rendered in (e.g. "Sunday, November 8, 2026" in English, formats as de/en texts; members have no language of their own, so it is the app language at send time); the "players wanted" mail counts the open places once per sending run instead of once per recipient; a blocked or deactivated booker no longer gets the "new player" mail (spec: exchange mails only go to active accounts). Tests also check for untranslated booking.mail keys and the chunk size of 50.
- Admin edit of a booking with a player search no longer gets stuck when a joined member has no name stored in the booking (older bookings): he counts as present, his name is written back into his slot, and typing his name does not add him twice.
- Cancelling or deleting a booking keeps the player exchange consistent: an admin delete tells every joined player once with the same mail as a cancel and removes the search and signups in the same transaction (no rows left behind, the lapse stays idempotent); joined players get the cancel mail exactly once, also after opting out of the exchange mails. The cancel now decides on the locked booking: a booking the lapse already lifted is not cancelled again, the booker gets no cancel confirmation on top of the "search expired" mail and sees "schon storniert oder abgelaufen" (proven on MySQL). Player searches stay limited to single bookings, so cancelling one occurrence of a series never touches a search. Bookings without a search cancel and delete as before. de/en text.
- Admin edit of a booking with a player search keeps the exchange intact: members who joined keep their slot and name even though the form lists names by position, removing or renaming them is refused, the owner-edit capacity rule applies (Einzel refused while joined members plus own partners do not fit), and date, time, repeat and booker cannot change while the search runs or members are signed up (court, own partners and Einzel/Doppel can). The edit now takes the booking row lock first and works on the current booking state. Bookings without a search, or with an ended search without signups, edit as before. de/en texts.
- Player exchange time conflicts are checked in both directions: a member signed up as a player (open, filled or ended search of an active booking) can no longer book a court for himself at an overlapping time ("Zu dieser Zeit spielst du schon"); back-to-back slots, other days, left, lapsed and cancelled searches stay free, and bookings without signups behave as before (no new rule for two own bookings). Join and booking use one shared availability check, and the booking now locks the member row like a join does, so a member booking and joining at the same moment never gets both (proven on MySQL). de/en text.
- Editing an own booking re-checks it under the booking lock: if the player search lapse or a cancel got there first, the edit is refused ("kann nicht mehr geändert werden") instead of writing players and Einzel/Doppel onto a cancelled booking. A retried edit after a deadlock now always writes its changes. de/en text.
- Editing an own booking with a player search keeps the exchange intact: members who joined keep their slot and name (the form shows only your own typed partner, lists the joined members and lets you clear your partner), Einzel is refused while typed partners plus joined members would not fit, a joined member moves into the remaining slot when it fits, and the places still searched never grow (nothing left: the search is filled, or ended without signups so the booking does not lapse). The edit runs under the same booking row lock as join and leave, so a parallel join and an edit never leave a player outside the booking (proven on MySQL). Leaving clears only the own slot, also with duplicate names. Bookings without a search edit as before. de/en texts.
- Own booking with an open player search: the cancel dialog gets "Suche beenden" (only while the search is open). It ends the search through the player exchange endpoint, shows the result (or the reason for a refusal) in the dialog and reloads the calendar when the dialog is closed; the booking and anyone already signed up stay. Double clicks are ignored and closing the dialog mid-request reloads once the answer is in, like the join dialog. "Buchung stornieren" is unchanged; an own booking without an open search shows no extra button. de/en texts.
- Join dialog: closing it (Escape, backdrop, X) while a sign-up or leave request is still running hides it and reloads the calendar as soon as the answer is in, so the result is never lost; "Austragen" and "Zu meinem Konto" get the first focus like "Mitspielen". An own booking with an open search keeps its screen-reader name (owner, "Meine") and appends the search.
- Calendar join dialog for player searches: a click on a search cell opens "Mitspieler gesucht" with date, time, court, who booked, how many players are still wanted and who is already in; members in the player exchange sign up with "Mitspielen", members already signed up leave with "Austragen", others get a hint with a link to "Mein Konto". The result (success or the reason for a refusal, e.g. busy at that time or the last place just went) shows in the dialog, closing it reloads the calendar. Without JavaScript the search cell links to the calendar day instead of a dead "#"; an own booking with an open search names the search in its screen-reader label. de/en texts.
- Calendar shows player searches: a booking of someone else with an open search (places left, deadline not reached) gets the new state "Sucht Mitspieler" (pale violet with a violet ring, token cell-seek) and a text line "Sucht noch N" (shorter or longer form by cell width, so the number is never cut off); an own booking with an open search stays "Meine Buchung" and shows "Sucht noch N Mitspieler". Members in the player exchange open the join dialog with a click (event open-join; the dialog itself follows), other members get a hint dialog with a link to "Mein Konto", guests go to the login. Legend entry for members and guests, de/en texts. Searches and signups are loaded with the calendar in two queries (no query per booking).
- Tests: the calendar query-count test sets the once-a-minute player search lapse as done before counting, so it again compares only the queries per booking (no production change).
- Player exchange Verfall runs from the one scheduler task list: "Geplante Aufgaben" now lists "Mitspielersuchen ablaufen lassen" (every 5 minutes, de/en title and description) with next run, last result and "Jetzt ausführen"; the separate schedule entry in routes/console.php is gone.
- Mixed-/Fun-Turnier: members have a read-only event list (/mixed-tournament/events, sidebar "Veranstaltungen") that stays reachable while a default event is set (the module start still opens the default event); "Zur Liste" on member event, round and standings pages and on the info page leads there instead of the admin list, which showed the "Admin-Modus einschalten" page.

## 2026-11-06
- Player exchange Verfall: one hour before the start an open player search without signups lifts its booking on the normal cancel path (court free again, no cancel confirmation; the booker gets the "search expired" mail instead), a search with signups only ends and the booking stays. Checked by `php artisan player-exchange:expire` every five minutes (scheduler, needs the cron for `schedule:run`) and lazily on calendar load (at most once a minute) and before a join; runs under the booking row lock, so running twice or next to a join never cancels a booking with players or mails twice. When the booker cancels, the search is cancelled and every joined player gets a mail. These two mails carry plain values, so a queue worker can still render them after the booking rows are gone.
- Player exchange mails: opening a player search mails every active, opted-in member except the booker (court, date, time, number of players still wanted, link to the calendar day, one-click unsubscribe link), sent after the response in chunks of 50; the booker gets a mail when someone joins (with the places still free or "complete"). Mails for an expired search (to the booker) and for a cancelled booking (to joined players) are ready for the lapse task. All mails are queued, use the booking Reply-To, have de/en texts (du-form) and are only sent when a real mailer is configured.
- Booking dialog: logged-in members can tick "Mitspieler suchen" (only when the booking starts in more than an hour) and pick how many players are missing (1 up to places minus booker minus a named partner); the partner field is then optional, the booking opens the player search and the success view says so. de/en texts.
- Player exchange joins: a member joining two overlapping searches at the same moment now gets only one place (the member's row is locked before anything is read); deadlocks and lock waits are retried and, if they persist, answered with "Bitte versuch es nochmal" (JSON 422 or flash) instead of an error page.
- Player exchange: opted-in active members can join an open search (POST /bookings/{booking}/join; their name goes into the first free player slot, the last place sets the search to filled) and leave it again until one hour before the start (DELETE, a filled search opens again); the booker can end an open search (POST /bookings/{booking}/search/end, booking and joined players stay). Joining is refused for the booker, after the deadline, when full, or when the member already has an active booking or a signup at an overlapping time on any court; joins run under a row lock on the booking, so two parallel joins never get the last place twice (proven on MySQL). No UI or mail yet.
- Schema checker knows the player exchange tables (player_exchange_searches, player_exchange_signups), so the manifest test passes again.
- Player exchange: a new single booking can open a player search (number of missing players, at most places minus booker minus named players); it ends one hour before the start and is refused when the booking starts in less than an hour (no booking is created then); no UI or mail yet.
- Player exchange opt-in: "Mein Konto" has a checkbox "Spielerbörse" (de/en texts) that joins or leaves the exchange; a signed, non-expiring link /player-exchange/unsubscribe/{user} opts a member out with one click without login (tampered links get 403) and shows a confirmation page.
- Player exchange foundation: new tables player_exchange_searches and player_exchange_signups, models PlayerSearch and PlayerSignup, and an opt-in service (user meta player-exchange) that lists active opted-in members with an e-mail address; no UI yet.
- Each tournament sidebar has its own group "Einstellungen" below the entries with the module's administration (Pyramid: Verwaltung; Championship: Leitung and Auslosungen verwalten; Mixed: Verwaltung and Mitglieder importieren), shown only to those who may administer.
- Tournaments are standalone: a page of the Forderungspyramide, the Vereinsmeisterschaft (with its draws) or the Mixed-/Fun-Turnier shows only that tournament's own sidebar (its name as heading and eyebrow) instead of one common sidebar with all three; the admin sidebar group "Turniere" is the way between them.
- Members Datenschutz page: new card "Verarbeitungszwecke" naming the family assignment for fee calculation (suggestions compare address, e-mail and phone and are never applied automatically).
- Members: page "Mögliche Familien" (button on the Haushalte list) lists suggested families with their reasons; a household is created only by "Haushalt anlegen" with the ticked members (optional name and tariff), "Kein Haushalt" hides the suggestion; the page names the processing purpose and creates nothing on its own.
- Members (family fee groundwork): suggestions of possible families from a shared address (normalised street and zip), e-mail or phone (last 9 digits) among current members without household; read-only, dismissed groups stay hidden, nothing is created automatically.
- Members export: two new columns "Haushalt" (household name or last names) and "Zahlende Person" ("ja" for the paying person).
- Members Rundmail: new audience "Beitrag {Jahr} offen" (active members with an open own due and, for an open household due, only the paying person); households without a paying person get no mail and are shown as "n Haushalte ohne zahlende Person (nicht angeschrieben)".
- Members list: members of a household get a "Haushalt" badge, the dues column shows the household due status read-only with a link to the household, "Beitrag offen" and the due filter also find members covered by an open household due, and "Weitere Filter" can show members with or without household.
- Members: the dues card on the member page lists the household dues covering the member as read-only rows with a "Haushalt" chip linking to the household, and "bezahlt bis" also counts paid household coverage.
- Members overview: "Beitrag offen" and "Bezahlt" count each household due once (count and sum, with "davon n Haushaltsbeiträge"), and the open-dues list links household rows to the household.
- Members: the payment form on a member page also offers the open household due covering the member or of their household ("Haushalt … · Jahr · Betrag"); the exact-amount rule applies to member and household dues together, a paid household due cannot be allocated twice, and the payment marks the household due paid for every covered member.
- Members: the Beiträge page lists the household dues of the year once in their own card (household, covered members, amount, open rest) with the same inline cells as member dues (status, Bezahlt am, Bezahlt bis, Teilbetrag, Kommentar), search by household or covered member, the status filter, the bulk "als bezahlt markieren" and paid-through creating the following household dues; closed years stay locked, the open total and the close confirmation count each household due once.
- Members: "Durch Haushaltsbeitrag ersetzen" on the household page: own unpaid dues of household members are named with a hint, a preview shows which own dues are deleted, which paid or partly paid ones stay untouched and the household due used or created; only after confirmation (one transaction under the year lock, every due re-checked) are the unpaid own dues deleted and the members covered.
- Members: household dues can be entered by hand on the household page (rate of the tariff unless an amount is given, one per household and year, only with an active per-household tariff and 2 active members); an own due is refused for a member a household covers in that year (form, API, MCP tool, CSV import), paid-through skips such years, and a joiner is covered only while the current year is open (checked under the year lock).
- Members: generating dues creates one household due per household and year (from 2 active or dormant members, amount from the household tariff rate, current year included) covering members without an own due, falls back to the member category for new members, and reports every member or household that got no due with a reason and links (no category, no rate, deactivated, household too small, own due to replace or check).
- Members: a fee category can be billed "Pauschal pro Haushalt" (badge in the list, choice on the category page); the billing cannot change while members, households or dues use the category, and per-household categories are never offered or accepted for a single member or an individual due.
- Members: member page card "Haushalt · optional" (household link and size, the current-year household due status, "Haushalt verlassen", or assigning to a household / "Neuen Haushalt anlegen"); the member protocol shows household and payer changes by household name (the global protocol only says that they changed).
- Members: household page with name, tariff and paying person, the members with their coverage in the current fiscal year, adding (members of another household are shown but locked; a different address only gives a hint) and removing members, the household dues, and "Auflösen" (dues and coverage stay until year end).
- Members: new sidebar entry "Haushalte" with a list of households (members, tariff, current household due and status, hint when fewer than 2 active members), search, active/dissolved filter and "Haushalt anlegen" (only active per-household categories as tariff; creating a household creates nothing else).
- Members: household changes (join, leave, payer, dissolve) run through one service that logs each change on the member, covers joiners in the current household due without surcharge, never deletes household dues or coverage, clears the payer on departure and detaches an anonymised member in the same protocol entry.
- Members (family fee groundwork): household and household-due models with per-year coverage, the effective due per member (own due first, else the covering household due) and the "replaceable own due" rule (unpaid, no payment, no partial amount); no screen uses them yet.
- Members (family fee groundwork): new additive tables for households, household dues, their coverage and payments plus `members.household_id` and `fee_categories.billing_unit` (default "member"); nothing creates a household, existing members, categories and dues are unchanged, and the database page tracks the new schema.
- Multi-hour calendar events and court closures now stay joined on phones, with one label and an outline around the whole event instead of repeated hourly tiles.
- The calendar cell title for a closed court no longer shows a raw translation key: the missing `calendar.closed` label (Geschlossen / Closed) was added.
- `booking:adopt-booking-rules --override=<rule>=<value in the display unit>` (repeatable, e.g. `--override=short_booking_window=15`) sets a rule globally even when the courts disagree and reports, per court, which stored value it supersedes and where the effective behaviour changes; honours `--dry-run`, replaces an already set option only together with `--force`, validates everything before writing.
- Courts with the peak-time switch no longer have a limit-free zone: outside the peak windows the global "Gleichzeitige Buchungen pro Benutzer" limit applies, inside the windows the peak limit applies in addition and the stricter one wins; the short-booking window lifts both, admin mode still bypasses only the peak rule (the global limit has no admin bypass); help texts de+en updated.
- Calendar grid: Konfiguration > Verhalten > Buchungsplan has a global start and end (options calendar.time_start / calendar.time_end, default 08:00 to 20:00) that build the calendar rows instead of the fixed 8 to 19; the court time fields become opening hours ("Öffnungszeit von/bis"), hours outside them show as closed and cannot be booked from the calendar, existing bookings stay visible.
- New admin page Einstellungen > "Geplante Aufgaben" (admin mode, right Konfiguration): scheduler health with a traffic light from the last tick (and the ready-to-copy cron line when the cron is missing), all scheduled tasks with plain-text schedule, next run, last result and failures of the last 7 days, "Jetzt ausführen" per task (confirmation, 5 per minute, never twice at the same time), queue card when a queue is used and the last 50 runs; the server cron `php artisan schedule:run` (every minute) now runs housekeeping only: heartbeat, run-log cleanup, expired API tokens, expired password links.
- Members Geschäftsjahre: the closed-year lock is proven on MySQL with two connections (composer test:mysql): a close and a payment or due write into that year never both succeed (the write is refused, or one side waits for the other).
- Konfiguration > Verhalten has a new card "Buchungsregeln" (Zeitblock in minutes, Buchungsvorlauf in minutes, Buchung im Voraus in days, kurzfristige Buchung in minutes, Stornierung in hours) with help texts and validation; the court form keeps only per-court settings (name, status, opening hours, visibility, notes, labels, peak switch), the card "Buchungszeitraum" and the dead checkbox "Min. buchbaren Zeitblock nur für die Verwaltung" are gone, stored court values stay untouched.
- Zeitblock (grid), Buchungsvorlauf, Buchung im Voraus, "Kurzfristige Buchung trotz Limits" and the cancellation deadline (Stornierung) are read from global options (booking.time_block, booking.min_range_book, booking.range_book, booking.short_booking_window, booking.range_cancel, all in seconds) with the court value as fallback; `booking:adopt-booking-rules` adopts them too when all courts agree.
- Konfiguration > Verhalten has a new card "Buchungsdauer" with "Zeitblock min. buchbar" and "Zeitblock max. buchbar" in minutes (whole numbers 0 to 1440, max 0 = unlimited or at least the minimum, empty = court values still apply); both fields are gone from the court form, the court columns stay untouched and a new court stores the global values.
- "Zeitblock min. buchbar" and "Zeitblock max. buchbar" are read from the global options booking.time_block_bookable_min/max (seconds) for every booking check; while an option is not set the court value still applies. New command `php artisan booking:adopt-booking-rules [--dry-run]` adopts the court values as global options when all courts agree (otherwise it lists the courts and leaves that setting alone); it only writes options and never overwrites one.
- Konfiguration > Verhalten: the peak-time card holds switch, limit and both windows with an example; times must be HH:MM, each window must start before it ends and window 2 after window 1; unticking the global switch now really turns the peak limit off (it stayed on before). The court form always shows "Stoßzeiten-Limit für diesen Platz aktivieren": while the limit is globally off it is disabled with a hint and link, and saving the court keeps its stored switch instead of resetting it.
- Peak-time limit has its own global value "Max. Buchungen in den Stoßzeiten" (empty/0 = the global "Gleichzeitige Buchungen pro Benutzer" applies, both 0 = no peak limit) and no longer needs a global max-active value; counted as before across all courts (future bookings starting inside a window, checked when the new booking starts inside a window on a court with the switch on); privileged accounts only bypass it in admin mode.
- Tests of the admin-mode booking limits now set the global max-active-bookings option (Konfiguration > Verhalten) instead of the removed per-court field (2026-11-06).
- The admin mode switch is a POST form and sets a browser-session cookie (no 30-day/1-year lifetime, HttpOnly, SameSite=Lax, Secure and path like the session cookie); switching off, logging in and logging out delete it.
- Administration, Mitglieder, Pyramide-, Mixed-, Turnier- and Auslosungs-Verwaltung show a page "Admin-Modus einschalten" with a button (returns to the requested page) instead of an error when a privileged account opens them without admin mode; AJAX calls get a clear 403 JSON. The menu entries stay visible; ladder and event grants keep working without admin mode.
- Booking limits (Zeitblock max. buchbar per day, Vorlauf, Mindestvorlauf, gesperrte/schreibgeschützte Plätze, Buchen in der Vergangenheit) are only lifted for privileged accounts while admin mode is on; changing the partner of an own booking no longer counts that booking twice against the daily and active-booking limits.
- Admin mode is now the switch for all privileges: an administrator or assistant only has administrator rights while "Admin-Modus" is on; without it the account behaves like a normal member in every check (booking rules, calendar, admin pages). API/MCP requests with an API token keep their rights.
- Mixed-/Fun-Turnier: the event tabs became the Turniere sidebar (Aktuelle Runde, Wertung, the event with its pages, Info, Verwaltung, Mitglieder importieren); the print views stay without the shell.
- Vereinsmeisterschaft and draws: the module tabs became the Turniere sidebar group (Übersicht, Gruppen, KO-Raster, Spielplan, Meine Spiele, Teilnehmer, Modus, Leitung, Losprotokoll(e), Auslosung, Auslosungen verwalten); the draw record and the token page sit in the same shell.
- Forderungspyramide: the ranking/challenges/rules tabs are gone; every Pyramid page (also detail and admin pages) uses the common Turniere sidebar with Rangliste, Forderungen, Regeln, Mein Profil and Verwaltung.
- Tournaments only for logged-in users: every tournament page, also the Pyramid ranking, rules and challenges and the draw record with its JSON, sends guests to the login (JSON: 401); TOURNAMENT_REQUIRE_LOGIN=false makes exactly those pages public again.
- Administration, Members and tournaments share one area shell: the same left sidebar (phones: slide-in from the top bar's menu button) built from one navigation structure per area; the slide-in now reliably moves the focus to its close button and back, and its phone head names the area as in the mockups.
- Members detail page: the Beiträge card is the compact list of the mockup (one line per year with Jahr, Kategorie · fällig, Betrag, Status, Bezahlt am; Bezahlt bis, Teilbetrag and Kommentar in a details row behind the arrow, open from the start when it holds something; "Zahlung erfassen" under the list) and no longer scrolls sideways at 1440, 1024 or 390 px.
- Members Geschäftsjahre: every write that a closed year blocks (year edit, closing/reopening, fee rates in the matrix and on the category page, creating/generating dues, inline due changes, marking paid in bulk or "Bezahlt bis", payments, the CSV import, and the API/MCP due tools, which ignored closed years before) checks the year again inside its transaction with the year row locked first, so a year closed between the form check and saving is refused with the closed-year message instead of being written.
- Members CSV import (members:import-csv): a row whose Mitgliedsart maps to a deactivated Beitragskategorie is skipped completely (no new member, no backfill, no dues) and counted in the summary per category, next to the dues skipped in closed years; the summary lines follow the app language (German/English).
- Members Geschäftsjahre: a new or edited year whose date range shares a day with another year (closed years included) is refused with "Der Zeitraum überschneidet sich mit Geschäftsjahr … (von – bis)"; adjacent years are fine, the end may equal the start but not lie before it, and malformed or array-shaped dates are a form error instead of a server error.
- Admin statistics: the booking-account figures and the name column say "Benutzer mit Buchung (Jahr/Monat)" and "Benutzer" (English "Users with bookings", "User") instead of "Mitglieder", so they are not mistaken for club members of the members area.
- Members overview: the link "Alle Beiträge" opens the dues list with the status filter "Alle" (status=alle in the URL) instead of the default "Offen".
- Members overview: members whose linked booking account is a placeholder are left out of every figure (Mitglieder, davon Jugend, ruhend, Aktiv, age structure, categories) and count as "Ohne Benutzerkonto"; the member list, dues, Rundmail and export are unchanged.
- Admin "Texte": a rejected mail address or name in the tournament and Reply-To cards is reported with the field label ("Antwortadresse für Buchungsmails") instead of the raw input name.
- Admin "Texte": new card "Antwortadresse für System-/Buchungsmails" with one Reply-To address for booking mails and one for password/account mails; a filled field wins over the .env value, an emptied field removes it again.
- Booking mails (confirmation, cancellation) and account mails (password reset, account activation, login code) can carry a Reply-To, one address per group from MAIL_BOOKING_REPLY_TO_ADDRESS / MAIL_ACCOUNT_REPLY_TO_ADDRESS; unset means no Reply-To header as before, and the tournament Reply-To stays separate.
- Admin dashboard: the KPI that counts enabled booking accounts is labelled "Aktive Benutzerkonten" (English "Active user accounts") instead of "Aktive Mitglieder", so it is not mistaken for the club member count of the members area.
- Members Beitragskategorien are deactivated instead of deleted ("Deaktivieren" / "Reaktivieren" in the list and on the edit page, badge "Deaktiviert"): a deactivated category keeps its dues, rates and members, is no longer offered for manual dues, new members or other members, is refused server-side for those, is skipped by dues generation (the flash says how many members were skipped), and stays visible as "… (deaktiviert)" for members who already have it.
- Members Geschäftsjahre can be closed on their edit page ("Geschäftsjahr abschließen"; with open dues only after ticking "Trotz offener Beiträge abschließen") and reopened by administrators ("Wieder öffnen"); a closed year is locked server-side (year data, its fee rates, creating/generating/editing dues, marking paid or open, "Bezahlt bis" across it, "Bezahlt am", payments and the CSV import), shown as "Abgeschlossen" in the year list, as "2025 (abgeschlossen)" in year selects and read-only in the dues list and member card; viewing, export and mails stay; no entry in the member change log.
- Members dues list opens with status "Offen" (outstanding and overdue, also dues of later Geschäftsjahre not yet due); the status filter offers Offen / Alle / Ausstehend / Überfällig / Bezahlt, an explicit "Alle" stays in the URL across year change, search and paging, unknown status values fall back to "Offen", and dues settled by a payment no longer count as outstanding or overdue (nor in "Offen gesamt").
- Members dues list keeps the chosen Geschäftsjahr: it is remembered when coming back from a member or payment, marking paid returns to the same year and filters, the generate select follows the listed year, the default is the year containing today (the newest if years overlap, the last day included) or else the latest year with dues; an unknown or malformed year falls back with a notice instead of an empty list under the oldest year's name, a year without dues says "Für Geschäftsjahr … sind noch keine Beiträge erzeugt", generating without rates says so, and a new "Offen (alle unbezahlten)" filter lists every unpaid due; the member page preselects the current year for a new due and the overview uses the same default year.
- Members dues get "Bezahlt am": set from the payment date when "Zahlung erfassen" settles a due (the form's date field is now labelled "Bezahlt am" and refuses future dates), defaulted to today when a due is marked paid by hand (inline status, bulk action with its own date field, "Bezahlt bis"), cleared when the due goes back to open, editable inline on paid dues and shown in the dues list and the member's dues card; `php artisan members:backfill-paid-at --dry-run` fills existing paid dues from their single linked payment.
- Rebuilt `public/build` from an empty directory over the whole tracked tree (the moved colour custom properties); asset files are committed without the executable bit so the committed build equals a fresh Linux build, and the field id tests joined `composer check:design`.
- Form fields resolve one id for label, control, help and error text (and `aria-describedby`): `<x-ui.field name="email" id="contact-email">` no longer renders the id twice or puts `name` on the wrapper, an own id on the child input is followed by label and texts, and repeated field names on one page get unique ids (`email`, `email-2`) instead of colliding (rules in README "UI components").
- `lint:ui` fails when a baseline exception is no longer needed (its finding was fixed or occurs fewer times than allowed) and lists those entries with the `--prune-baseline` hint, so a fixed design defect cannot return unnoticed under an old allowance; baseline entries without file, rule or source are rejected.
- The colour check no longer lets literal `rgb()`/`rgba()`/`hsl()`/`hsla()`/`oklch()` (and `hwb`/`lab`/`lch`/`oklab`) colours or 4/8-digit hex colours pass in views (Tailwind arbitrary values, style attributes and blocks, Alpine `:style` bindings) or component CSS; token forms (`var(--color-*)`, `rgb(var(...) / a)`, `color-mix` of tokens) still pass, and the three remaining literal colours of the restyle moved to named custom properties in `app.css`.
- The CI build check no longer fails (or passes) by accident: it rebuilds `public/build` from an empty directory and compares added, deleted and changed files, and a test rejects manifest entries without a file and stale, unreferenced assets.
- Design checks are enforceable: `lint:ui` now fails on any blocking finding, also covers `public/css`, tracks the legacy findings of the old stylesheet in a shrinking `ui-design-baseline.json`, runs in `composer lint`, the pre-commit hook and a GitHub `Design checks` workflow, and the Dusk visual checks gained login/account/admin and gallery references with neutral fixtures.
- Members forms (Geschäftsjahr, Funktion, Mitglied, Anonymisieren, Zahlung, Beiträge, Rundmail, Mail-Konfiguration, Datenschutz) no longer fail with an error page when a rejected request sent a list where one value belongs (`name[]=x`) or one value where a list belongs: the shared input, textarea and select fields show such old input as empty and validation rejects it.
- Members list follows the new mockups: seven compact columns (Nr., Name as "Nachname, Vorname", Kategorie, Status, Beitrag of the current fiscal year, Telefon, Benutzerkonto optional) that fit next to the side menu without sideways scrolling, a search field for name, number or e-mail, server-side chips Alle / Aktiv / Ruhend / Jugend (18 or younger) / Beitrag offen (any open due) that keep search and pager, "Weitere Filter" for category, dues status and departed members, and compact cards on phones that open the member; "Alle" (active and dormant) is now the default instead of only active members.
- Members settings use the labels of the new mockups: "Geschäftsjahre" instead of "Fiskaljahre" (also in dues and fee-category texts), "Mail-Konfiguration" instead of "Mail-Einstellungen" and "Datenschutz" instead of "DSGVO" (English "Mail configuration", "Data protection") in the side menu, page titles and flashes; the URLs stay the same.
- Members overview: "Aktiv" (and the age structure and category breakdown of active members) no longer counts members whose linked booking account is a placeholder; members without an account still count by their own status.
- Members > Beitragskategorien lists every existing category with its rates per fiscal year (or "Noch kein Beitragssatz") and a "Bearbeiten" page to rename it and add or change a rate per fiscal year; without a fiscal year the page now says so and links to creating one, instead of hiding all categories.
- Members area gets a side menu on the left like the administration: group "Mitglieder" (Übersicht, Mitglieder, Beiträge, Rundmail, Protokoll) and group "Einstellungen" (Fiskaljahre, Beitragskategorien, Funktionen, Mail-Einstellungen, DSGVO), with "Zum Kalender" and the slide-in menu behind ☰ on phones; the tab row and the settings chip row under the page title are gone, all /members URLs stay the same.

## 2026-11-05
- Members labels: dues list uses singular labels ("Fiskaljahr", "Beitragskategorie", "Fiskaljahr für neue Beiträge") and "Betrag" without "(€)" next to amounts that carry the sign; the phone filter disclosure is "Weitere Filter"; the overview says "5 von 19 angezeigt" for open dues; the detail history card is "Änderungsprotokoll" with spacing in the account card; the new-function form saves with "Speichern".
- Members on phones and desktop: Rundmail no longer overflows the phone width (long audience chips wrap, one shrinkable column below xl), template names in "Vorlagen" no longer break mid-word, mail category names keep a usable width (buttons wrap below), the "+ Beitrag manuell anlegen" button no longer covers the "Beiträge" title, the tab rows lose the scrollbar thumb over the active underline, the active settings pill scrolls into view, and overview/log name links get 44px tap height.
- Admin user edit: a user linked to a member shows "Mitglied: Nr. …"; the number links to the member page for users with the members permission; nothing is shown for unlinked users.
- Members detail: "Anonymisieren" (in a "Datenschutz" side card, as in the mockup) only appears once the retention period after leaving has really passed; before that the card says from which date anonymisation is possible; the header no longer shows a red "Löschen" button for every departed member.
- Members: all amounts (dues list, member detail, overview totals, payment check) use one format, German "3.800,00 €" and English "€3,800.00", instead of a mix with "3,800.00 €".
- Admin users: placeholder accounts are hidden from the list (and its total) unless the status filter picks "Platzhalter" or "Platzhalter anzeigen" is ticked; a hint with a link says when they are hidden.
- Admin users: filter by the date of the last booking ("Letzte Buchung von/bis", inclusive) or "Ohne Buchung" (wins over the dates), combinable with status, permission and search; the list is paginated (50 per page) and the pager keeps the filter; invalid filter values are ignored with a message instead of failing.
- Admin statistics: the page is computed with a few grouped SQL queries instead of loading every booking (about 28 s down to well under 0.1 s on a demo club with 200 members and 20,000 bookings); without a submitted search only the summary is computed; results unchanged.
- Members: the mail tab and page are called "Rundmail" as in the mockups.
- Members overview: four figures (Mitglieder with youth, Aktiv with dormant, open dues of the current fiscal year with total, members without a user account), the five open dues with a link to all dues, the five latest changes with a link to the log, and "Mitglied anlegen"; age structure and category breakdown stay below.
- Members: new tab "Protokoll" lists every change to members (newest first, filter by name and period, paged); personal fields only show "… geändert" without values, and anonymised members never show old personal data, also in the history on their detail page (GDPR).
- Members: the link to a booking-system account is shown as optional: list column "Benutzerkonto (optional)" with "verknüpft"/"–", and on the detail page a side card "Benutzerkonto · optional" to link or unlink (unlinking is logged); mail, export, dues and filters keep working for members without an account.
- Admin sidebar: the group "Mitglieder" is now "Benutzer" with only "Benutzerkonten" and "Anmeldeverlauf" (booking-system logins); the member module and "Mitglieder-Einstellungen" entries are gone, member management is reached through the header.
- Members area stands on its own: the pages use the normal app header instead of the admin shell (no admin sidebar), with eyebrow "Mitgliederverwaltung" and one tab row Übersicht · Mitglieder · Beiträge · Rundmail · Einstellungen (settings pages get a second row); the list offers Export, and on phones "Mitglied anlegen"/"Speichern" is a full-width button pinned at the bottom.
- Header: "Mitglieder" is its own top-level entry right before the administration (also in the phone menu) instead of sitting in the administration dropdown; it only lights up on member pages.
- German validation messages for every framework rule (decimal, list, prohibited and others showed the raw key "validation.…" in German before).
- Admin "Texte" page: new section "Absender der Turnier-Mails" (sender, Reply-To, signature club/website/text); a filled field wins over .env, an emptied field falls back again.
- Tournament mails (Pyramid now, later tournament modules): From, Reply-To and signature come from one shared TournamentBase helper, configurable via .env (TOURNAMENT_MAIL_*, MAIL_REPLY_TO_*); unchanged when nothing is set, and MAIL_REPLY_TO_* no longer adds a Reply-To to the booking mails.
- Admin booking form: the booking status can no longer be picked (it follows from single slot vs. series; only the cancel action cancels), and the player count sits next to court and booked-for.
- Admin dialogs (booking, event and other framed forms): the white shell strip with a second close button is gone, so the dialog starts with the form's own dark title bar and one close button; Escape in the form closes it, and focused fields are no longer hidden behind the action bar.
- Users: artisan users:split-role (--dry-run) moves admin/assist from the status into the role column once USERS_SPLIT_ROLE is on; idempotent, refuses while the flag is off, never run by a migration.
- Admin users: the list shows the permission and the date of the last (not cancelled) booking, also on the phone cards, and filters by permission; bulk actions never change admins or demote assistants; the booking count no longer costs a query per row.
- Admin users: the form has separate fields for status (Platzhalter, aktiviert, auf Aktivierung wartend, gesperrt, gelöscht) and permission (Mitglied, Assistent, Admin); a permission needs the status "aktiviert", single permissions are only shown and kept for assistants.
- Users: account status and permission are separate concepts in the code; new additive column bs_users.role and flag USERS_SPLIT_ROLE (default off: the permission stays merged into status as the old system expects).
- Pyramid: members without admin rights get a 403 (not the admin shell) on unknown admin ids, the not-found page links to the calendar when no ladder exists, the admin phone bar respects the iPhone safe area and the public view stays reachable on phones.
- Pyramid admin on phones: compact participant rows with one pause/activate action (name opens the edit area), back arrow with pyramid eyebrow in the admin bar and a fixed "Teilnehmer anlegen" bar; desktop keeps the table.
- Pyramid: challenge, player profile and challenge confirmation get the phone top bar of the mockups (back arrow and title in place of the site header on phones); desktop unchanged.
- Pyramid: an unknown ladder, player or challenge shows a friendly not-found page with a way back (status stays 404; the administration gets it in the admin shell).
- Pyramid: the result form shows live whether the entered score is valid and who won (same check as on saving; without JavaScript the form still checks on submit).
- Tournament config (live-DB guard name, court names) moved to config/tournament-base.php so env values survive config:cache; static analysis is clean again.
- Phase 2: an oversized or zero match id answers 404 instead of an error page, and a failed immediate draw in a test tournament shows a generic text instead of the internal error.
- Phase 2: closing a picking phase shows the actionable reasons (more players than free tokens, no tournament, no format, not marked as applied) translated again; only unexpected errors stay generic.
- Phase 2 go-live (module 4): the club championship and the draw admin and token pages are served by Laravel (with the deploy of this branch; rollback: re-add tournament|draw| to the bridge rule in public/.htaccess).
- Phase 2: championship admin notes from bracket generation and refresh are shown translated, internal errors only as a generic text.
- Phase 2: phase2:preflight tournament and fictional championship demo data for staging.
- Phase 2: a token pick held up by a parallel close or pick asks to try again instead of failing, and close no longer shows internal error texts.
- Phase 2: a manual lot decision that does not match the open tie is refused, an outdated one is ignored and flagged in the championship admin instead of breaking the tables.
- Phase 2: championship test tools (view as player, simulated results, reset) ported.
- Phase 2: knockout bracket refresh (qualifiers, byes, advancing winners) ported.
- Phase 2: championship admin fits narrow cards (group list, stacked draw fields) and lists only applicable draws as to-dos.
- Phase 2: championship group and knockout draws are prepared, executed and applied in Laravel.
- Phase 2: admins manage couples, comments and manual lot decisions.
- Phase 2: admins create, activate and test-copy championships in Laravel.
- Phase 2: championship admin in the new design (not live yet).

## 2026-11-05
- Phase 2: deleting a discarded draw locks it first, so no late token claim can slip in.
- Phase 2: closing the pick phase assigns non-pickers reproducibly and fills the groups.
- Phase 2: applying a draw to groups or bracket lines is validated first and happens exactly once.
- Phase 2: players pick their draw token in the new design; group and matches appear right after the pick.
- Phase 2: token claims stay atomic against the draw status.
- Phase 2: executing a draw re-verifies the commitment and writes result and token slots atomically.
- Phase 2: draws admin in the new design (not live yet).
- Phase 2: manual draws refuse line/paragraph separators and numeric slot names, so every new draw verifies with the Python verifier.
- Phase 2: public randomness beacon client ported (two endpoints, 10 s timeout).
- Phase 2: championship pages show the ITN to every member again (as before), tint your own matches in the schedule and word KO WhatsApp messages correctly.
- Phase 2: championship result entry with live validation and confirmation by the opponent.
- Phase 2: championship pages polished after the visual check (phone chip rows, key figure cards).
- Phase 2: "Meine Spiele" in the new design.
- Phase 2: participants list; contacts only for participants.
- Phase 2: knockout bracket in the new design.
- Phase 2: championship schedule with status and group filters in the new design.
- Phase 2: group tables with tie-break explanation in the new design.
- Phase 2: championship overview and rules pages in the new design (not live yet).
- Phase 2: a non-string match_format or runner_up mode in a stored tournament format falls back to the default instead of failing.
- Phase 2: tournament matches shown as match cards (new design).
- Phase 2: tournament areas redirect guests to the Laravel login with a module message and explain expired forms.
- Phase 2: championship access levels (member, participant, admin) and "view as player" ported.
- Phase 2: match generation runs in one transaction and reads fresh player rows.
- Phase 2: automatic lot tie-break (HMAC of the first draw's seed) ported byte-exactly.
- Phase 2: championship read model (groups, promotion, progress) ported.
- Phase 2: knockout placeholder resolution ported unchanged.
- Phase 2: match and bracket generation ported unchanged.
- Phase 2: championship standings and tie-breaks ported unchanged (golden vectors).
- Phase 2: tournament format configuration ported unchanged (golden vectors).
- Phase 2: tournament tables adopted in place (guarded create migrations, no-op rollback, schema manifest).
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
- Member header is a light sand bar (ground fill, hairline underline, ink text) with a clay underline on the active item and a grey one on hover, a white card for the Turniere dropdown and no "Platzbuchung" eyebrow; the admin sidebar and the opened phone menu stay dark.
- Calendar: club events get a green outline around the whole block, as in the state overview (closures stay plain grey hatching).
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
