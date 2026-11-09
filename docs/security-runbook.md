# Security runbook: trusted devices for sign-in

Accounts in the configured *scope* must sign in from a *trusted device* (independent of admin mode). A device
is a browser with a valid device cookie. Without one (first sign-in, new browser, cleared cookies, expired after
30 days, removed), the sign-in goes through the existing e-mail code step after the correct password; the right code
trusts the browser.

## Scope: who needs a device

Four levels, widening from top to bottom (`App\Services\TrustedDeviceSettings`):

| Level (admin page) | Value | Accounts |
| --- | --- | --- |
| Aus | `off` | nobody (default when nothing is configured) |
| Nur Admins | `admins` | role admin |
| Admins und Bearbeiter | `privileged` | admins, plus assistants (role "Assistent", the staff role) and every holder of the members right (`User::requiresTrustedDevice()`) |
| Alle Benutzer | `all` | every account, regular members included |

"Bearbeiter" in the club's wording is the assistant role of the system; there is no separate role of that name.

Where it is set, in order of precedence: **`.env` > admin value > default `off`**.

- Admin page *Verwaltung → Einstellungen → Sicherheit* (`/admin/sicherheit`, right `admin.config`, no admin mode
  needed). **The page is editable only while `.env` does not set `BOOKING_TRUSTED_DEVICE_SCOPE`**; with a value
  in `.env` it shows the level as "Fest durch Serverkonfiguration" and cannot save.
- Admin value from the shell (idempotent, no secrets): `php artisan security:set-device-scope privileged`
  (`off` | `admins` | `privileged` | `all`); it stores option `security.trusted_device_scope` and prints the effective
  value and its source. A value in `.env` still wins (the command warns).
- Saving writes one line to the log (`admin.security.config_changed`: who, when, `trusted_device_scope: old > new`).
- Widening: the newly covered users confirm one e-mail code at their next sign-in; running sessions stay.
- Lock-out guard: the admin cannot save a level that covers their own account unless this browser is a confirmed
  device. The page then offers "Dieses Gerät jetzt bestätigen" (code by e-mail, trusts this browser even while the
  scope is still `off`, returns to the page). Narrowing or `off` is always possible. The shell command has no such
  guard: use it only with a working mail system.
- **Default changed:** without a `.env` value and without an admin value the scope is now `off` (before: `privileged`
  from the code default). An installation that relied on the old default must set the level once
  (`php artisan security:set-device-scope privileged`, or the admin page), or keep `BOOKING_TRUSTED_DEVICE_SCOPE=privileged` in `.env`.
- Running sessions are not ended when the scope is widened; to end them for an account use `security:reset-devices`.

## How it works

- Cookie `trusted_device_<user id>`: random 256-bit token, encrypted and signed by the app, HttpOnly, Secure,
  SameSite=Lax. One cookie per account, so several accounts can share a browser.
- Table `trusted_devices`: only the SHA-256 of the token, a label from the user agent (display only), country,
  created / last seen / expires / revoked.
- Validity is fixed: `booking.security.trusted_device_days` (default 30) from the confirmation, no renewal.
- The risk check (unusual country, failed attempts, impossible travel) still asks for a code on a trusted device.
- Code: 6 digits, 10 minutes, 5 wrong tries (counted server-side per account), stored hashed.
- A new code is always possible on the code page ("Neuen Code senden"), also after a wrong, expired or used-up
  code and after a device expired. It voids the old code and restarts the counter. Limits are waiting times only:
  30 seconds between two codes, 5 codes per account, hour and IP address, 15 per account and hour in total.
  At the limit the page names the wait and offers a password reset; nothing is locked for good.
- After a confirmation the account owner gets the mail "Neues Gerät bestätigt".
- Devices end on password change (own, by an admin or by reset link), role / state / single-right change, change
  of the e-mail address, removal of the user, and on "Alle Geräte entfernen" in *Mein Konto*. Each device can also
  be removed on its own there. Open codes are voided at the same moment; for all of these except "remove all" and
  single removal the account's other sessions end as well (database session driver).
- **Password reset by mail link:** it revokes all devices and sessions, then confirms the current browser in the
  same flow: a code goes to the mailbox, entering it signs in and trusts the browser. This is also the way back in
  for someone who lost their only device (no admin needed).
- API token requests (REST, MCP) are not affected. Normal members only get the risk check unless the scope is
  widened.

## Configuration (`.env`)

| Variable | Default | Meaning |
| --- | --- | --- |
| `BOOKING_TRUSTED_DEVICE_DAYS` | `30` | Validity of a confirmed device in days. |
| `BOOKING_TRUSTED_DEVICE_SCOPE` | *(unset)* | `off`, `admins`, `privileged` or `all` (see above). Leave unset to manage the scope on the admin page; a value here wins and locks the page. Invalid values count as unset. |

Run `php artisan config:clear` after changing them.

## Emergencies

**An admin lost the browser or cookie** – nothing to do: the next sign-in asks for an e-mail code and trusts the
new browser.

**Reset one account's devices** (also ends its sessions)

```bash
php artisan security:reset-devices <user id or e-mail>
```

All its devices are revoked; the next sign-in needs a new code.

**The mail system is down, so no code can be sent** – nobody privileged can sign in on a new browser. Already
signed-in sessions and browsers with a valid device keep working. To get in:

1. Switch the scope off: `php artisan security:set-device-scope off` (works while `.env` does not set the
   variable; otherwise set `BOOKING_TRUSTED_DEVICE_SCOPE=off` in `.env` and run `php artisan config:clear`).
2. Sign in, repair the mail setup.
3. Set the level back (`security:set-device-scope privileged`, or the admin page; remove the `.env` line again)
   and clear the config if `.env` was touched.

While `off`, sign-in is password only plus the normal risk check: keep the time short.

**An admin has no usable e-mail address** – accounts sign in with their e-mail address, so without one there is
no sign-in at all; with a wrong address the code goes to the wrong mailbox. Another admin corrects the address
under *Benutzer*; if nobody can sign in, use the switch above, fix the address, switch back.

**Suspected stolen device** – the owner removes the device in *Mein Konto* → *Meine Geräte*, or an admin runs
`security:reset-devices`; also change the password (that removes all devices as well).

## API only from allowed addresses

The API path (`/api` and everything below it) answers only to client addresses listed in the server environment value
`API_ALLOWED_IPS` (comma separated, for example `API_ALLOWED_IPS=203.0.113.10,203.0.113.11`). Any other address gets
`403` with an empty-looking body and the header `X-Api-Restricted: 1`. Everything outside `/api` (calendar, sign-in,
the provider webhooks, ...) is not affected.

- **Fails closed:** the value unset or empty means nobody may use the API. Set it before the first API client connects.
- **Where:** only in the server `.env` of each installation (never in the repository, never in a tracked file). Run
  `php artisan config:cache` afterwards.
- **Address source:** the connection address. No proxy is trusted, so `X-Forwarded-For` and similar headers cannot be used
  to pretend to be an allowed address. If the host is ever put behind a proxy or CDN, configure trusted proxies first,
  otherwise every caller looks like the proxy.
- **Check:** from an allowed address a call without a token returns `401` from the application; from any other address
  it returns `403` with the header above (the application is not even reached).
- **Why not Apache:** the deploy replaces `public/.htaccess` with the tracked file and the web server ignores
  `.htaccess` files above the document root, so an address rule there would be lost on every deploy.


## Guard against pushing confidential information (hooks)

Git hooks in `.githooks/` stop confidential information before it leaves the machine. They are active when
`core.hooksPath` is `.githooks` (set in the shared clone configuration; check with `git config core.hooksPath`, set it
in a fresh clone with `git config core.hooksPath .githooks`).

| Hook | Checks |
| --- | --- |
| `pre-commit` | staged changes (added lines, file names), before the CHANGELOG/UI checks |
| `commit-msg` | the commit message |
| `pre-push` | every commit to be sent: added lines of the diff, message, author/committer names and e-mails, file names. Commits whose author or committer identity matches the term list are refused, so configure a neutral identity for this repository (`git config user.name`, `git config user.email`) before committing |

For a ref that already exists remotely only the new commits (`remote..local`) are checked; for a new branch the whole
history (an empty base) is. Verified commits are remembered per term list and hook version
(`<git-dir>/booking-guard/`), so repeated pushes are fast; the first push of a long history takes about a minute.

**Term list (local, never in the repository).** One extended regular expression per line, `#` for comments, in
`$BOOKING_GUARD_TERMS_FILE`, default `~/.config/booking-guard/terms.txt` (keep it private, `chmod 600`). Put the club,
host, domain and person names, real addresses and similar there. **Without the file, or with an empty one, every hook
fails closed**: commit and push are refused with instructions, nothing is checked. Findings show only
`<file>:<line>` and the number of the list entry, never the matched text; the entry number refers to the n-th active
line of the list.

**Generic rules (no list needed):** credential assignments (`name = "literal"` with password, secret, token, api key,
app key; env-file lines such as `NAME_PASSWORD=value`; not in `tests/`, placeholders such as `REDACTED`/`example`
are ignored), private key headers, secret-like file names (`.env*` except `*.example`, `*.sql` except
`tests/Fixtures/`, `*.pem`, `*.key`, `*.zip`, `*.p12`, `*.pfx`, `*.sqlite`), and IPv4 addresses outside the
documentation ranges (203.0.113.x, 198.51.100.x, 192.0.2.x), loopback (127.x) and private ranges (10.x, 172.16-31.x,
192.168.x; repeated-digit test addresses such as 1.1.1.1 are ignored). A line with the marker `guard:allow` is exempt
from the generic rules (never from the term list). `gitleaks` is run in addition when it is installed
(`BOOKING_GUARD_NO_GITLEAKS=1` skips it).

**Several refs at once.** A push of more than one ref (`--all`, `--mirror`, several branches) is refused with the
number of refs; confirm deliberately with `BOOKING_GUARD_ALLOW_MULTI=1`. Deleting a ref is not scanned.

**Do not bypass.** `git commit --no-verify` and `git push --no-verify` are forbidden for this repository; if a
check is wrong, fix the rule (generic rule, marker `guard:allow` on a harmless line, or the list), never skip the hook.
A finding in an already committed change needs a history rewrite, not a new commit on top.

Limits: binary files are checked by name only (not by content), and annotated-tag messages are not scanned. A term list with CRLF line ends or a BOM is read correctly; an invalid entry, or one that matches the empty string, makes every hook refuse. Paths that contain a listed term are shown as `<path withheld>`.

Tests: `tests/Feature/Tooling/PushGuardTest.php` (process tests with dummy terms in a temporary list).

### Server-side backstop (one-time setting on the hosting platform)

Local hooks only protect machines that have them. In the repository settings of the hosting platform enable
*Secret scanning* and *Push protection* (Settings > Code security). Push protection rejects a push that contains a
recognised secret; it does not know club or person names, so the local term list stays necessary.
