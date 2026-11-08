# Security runbook: trusted devices for sign-in

Admin and assistant accounts must sign in from a *trusted device*. A device is a browser with a valid device
cookie. Without one (first sign-in, new browser, cleared cookies, expired after 30 days, removed), the sign-in
goes through the existing e-mail code step after the correct password; the right code trusts the browser.

## How it works

- Cookie `trusted_device_<user id>`: random 256-bit token, encrypted and signed by the app, HttpOnly, Secure,
  SameSite=Lax. One cookie per account, so several accounts can share a browser.
- Table `trusted_devices`: only the SHA-256 of the token, a label from the user agent (display only), country,
  created / last seen / expires / revoked.
- Validity is fixed: `booking.security.trusted_device_days` (default 30) from the confirmation, no renewal.
- The risk check (unusual country, failed attempts, impossible travel) still asks for a code on a trusted device.
- Code: 6 digits, 10 minutes, 5 wrong tries, stored hashed, at most 5 codes per account and hour.
- After a confirmation the account owner gets the mail "Neues Gerät bestätigt".
- Devices end on password change (own, by an admin or by reset link), role / state / single-right change, removal
  of the user, and on "Alle Geräte entfernen" in *Mein Konto*. Each device can also be removed on its own there.
- API token requests (REST, MCP) are not affected. Normal members only get the risk check unless the scope is
  widened.

## Configuration (`.env`)

| Variable | Default | Meaning |
| --- | --- | --- |
| `BOOKING_TRUSTED_DEVICE_DAYS` | `30` | Validity of a confirmed device in days. |
| `BOOKING_TRUSTED_DEVICE_SCOPE` | `privileged` | `privileged` = admins and assistants, `all` = every account, `off` = no device check. |

Run `php artisan config:clear` after changing them.

## Emergencies

**An admin lost the browser or cookie** – nothing to do: the next sign-in asks for an e-mail code and trusts the
new browser.

**Reset one account's devices**

```bash
php artisan security:reset-devices <user id or e-mail>
```

All its devices are revoked; the next sign-in needs a new code.

**The mail system is down, so no code can be sent** – nobody privileged can sign in on a new browser. Already
signed-in sessions and browsers with a valid device keep working. To get in:

1. Set `BOOKING_TRUSTED_DEVICE_SCOPE=off` in `.env`, run `php artisan config:clear`.
2. Sign in, repair the mail setup.
3. Set the value back to `privileged` (or remove the line) and clear the config again.

While `off`, sign-in is password only plus the normal risk check: keep the time short.

**An admin has no usable e-mail address** – accounts sign in with their e-mail address, so without one there is
no sign-in at all; with a wrong address the code goes to the wrong mailbox. Another admin corrects the address
under *Benutzer*; if nobody can sign in, use the switch above, fix the address, switch back.

**Suspected stolen device** – the owner removes the device in *Mein Konto* → *Meine Geräte*, or an admin runs
`security:reset-devices`; also change the password (that removes all devices as well).
