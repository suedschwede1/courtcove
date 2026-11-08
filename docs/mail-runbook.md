# Mail-Runbook: Staging-Mailschutz

Gilt fuer jede Instanz, deren Datenbankname auf `_staging` endet. Auf jeder anderen Datenbank (Live) hat der Schutz keine Wirkung, auch wenn die Variable gesetzt ist.

## Was der Schutz tut

Ein zentraler Listener (`App\Services\Mail\StagingMailGuard`, Ereignis `MessageSending`) greift bei jeder ausgehenden Mail: Mailables, Benachrichtigungen ueber den Mail-Kanal, Passwort-Zuruecksetzen, Anmelde- und Geraetecode-Mails, Spielerboerse-, Beitrags- und Rundmails, die Budget-Warnung, `Mail::raw` und Mails aus der Warteschlange (der Schutz greift beim eigentlichen Versand).

- **Staging-Erkennung:** dieselbe Regel wie beim WhatsApp-Schutz und beim Scrub (`App\Support\StagingDatabase`): Datenbankname endet auf `_staging`. Ein nicht ermittelbarer oder leerer Datenbankname zaehlt als Staging (fail closed).
- **Mit `MAIL_STAGING_REDIRECT`** (genau eine gueltige Adresse): An, Cc und Bcc werden entfernt, die Mail geht nur an diese Adresse. Der Betreff bekommt das Praefix `[STAGING → original@…]` (ab drei Empfaengern gekuerzt, z. B. `a, b +2`), die Originale stehen in den Headern `X-Original-To`, `X-Original-Cc` und `X-Original-Bcc`. Reply-To bleibt unveraendert.
- **Ohne gueltige Adresse und mit echtem Mailer** (alles ausser `log`/`array`, auch `failover` oder `roundrobin`, sobald ein echter Transport darin vorkommt): die Mail wird nicht gesendet, es gibt eine Log-Warnung ohne Empfaengeradressen (nur Mailer-Name und Anzahl). Nie Versand an den Originalempfaenger.
- **Mailer `log`/`array`:** die Weiterleitung wird angewendet, falls gesetzt; nichts wird zusaetzlich blockiert.

## Einrichten (nur in der `.env` der Staging-Instanz)

```
MAIL_STAGING_REDIRECT=<eigene Postfachadresse>
```

Die Adresse und die Zugangsdaten des Mailservers stehen nur in der Staging-`.env`, nie im Repository. Danach `php artisan config:clear` (bzw. Cache neu bauen) und pruefen:

```
php artisan mail:staging-check          # zeigt Mailer und "gesetzt / nicht gesetzt", nie die Adresse
php artisan mail:staging-check --send   # Testmail an einen Fantasie-Empfaenger; kommt im eigenen Postfach an
```

Ohne Weiterleitung meldet `--send` "nicht gesendet". Auf einer Live-Datenbank bricht der Befehl ab und sendet nichts.

## Warnung: gleiche Konten wie Live

Wenn Staging einen echten Mailer nutzt, sendet es ueber **dieselben Absender- und Ratelimit-Konten** wie Live (gleiche Zugangsdaten, gleiches Tageskontingent). Staging-Tests verbrauchen also das Kontingent der Live-Mails und koennen den Ruf des Absenders beeinflussen. Darum:

- fuer Staging nach Moeglichkeit eigene Zugangsdaten bzw. ein eigenes Konto verwenden, sonst nur wenige gezielte Testmails senden;
- Massenversand (Rundmails, Boersen-Mails an alle) auf Staging nur mit bewusst kleiner Testgruppe ausloesen; er wird zwar auf eine Adresse umgeleitet, zaehlt aber pro Nachricht gegen das Kontingent;
- ohne eigenes Konto und ohne Bedarf besser `MAIL_MAILER=log` auf Staging lassen.

## Grenzen

- Der Schutz wirkt nur im Mail-System der App. Andere Wege (z. B. WhatsApp) haben eigene Schutzmechanismen (siehe `docs/whatsapp-runbook.md`).
- Eine Datenbank, die nicht auf `_staging` endet, gilt als Live; dort aendert dieser Schutz nichts. Deshalb darf eine Staging-Instanz nie an die Live-Datenbank angeschlossen werden.
