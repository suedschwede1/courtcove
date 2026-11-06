# Changelog

This project does not use semantic version numbers (it is deployed continuously via FTP
to a single shared-hosting instance, not released as versioned packages). Instead, this
file groups notable user-facing and structural changes by date. The only tagged
reference point is `mobile-view-stable` (see [README.md](README.md#layout-reference)).

When adding an entry: describe the *why*/user-facing effect in one line, not the diff —
`git log` already has that. Add new entries at the top, under today's date.

## 2026-11-06
- The admin mode switch is a POST form and sets a browser-session cookie (no 30-day/1-year lifetime, HttpOnly, SameSite=Lax, Secure and path like the session cookie); switching off, logging in and logging out delete it.
- Administration, Mitglieder, Pyramide-, Mixed-, Turnier- and Auslosungs-Verwaltung show a page "Admin-Modus einschalten" with a button (returns to the requested page) instead of an error when a privileged account opens them without admin mode; AJAX calls get a clear 403 JSON. The menu entries stay visible; ladder and event grants keep working without admin mode.
- Booking limits (Zeitblock max. buchbar per day, Vorlauf, Mindestvorlauf, gesperrte/schreibgeschützte Plätze, Buchen in der Vergangenheit) are only lifted for privileged accounts while admin mode is on; changing the partner of an own booking no longer counts that booking twice against the daily and active-booking limits.
- Admin mode is now the switch for all privileges: an administrator or assistant only has administrator rights while "Admin-Modus" is on; without it the account behaves like a normal member in every check (booking rules, calendar, admin pages). API/MCP requests with an API token keep their rights.
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
