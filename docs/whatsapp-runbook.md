# WhatsApp-Benachrichtigungen: Runbook

Betriebsanleitung fuer den WhatsApp-Kanal. Dieses Dokument ist anbieterneutral; Zugangsdaten und Anbieter-Adressen stehen nie hier, sondern in `.env` bzw. in den Admin-Optionen (`whatsapp.*`).

Weitere Abschnitte (Inbetriebnahme, Betrieb, Budget, Fehlerbilder, Rollback) folgen mit Task 16.

## Staging-Scrub

`php artisan phase2:scrub-staging --force` bereinigt die Staging-Datenbank. Nach den Modul-Scrubs laufen alle mit `phase2.config-scrub` getaggten Config-Scrubs, darunter der WhatsApp-Scrub. Die bestehenden Schutzschranken gelten unveraendert: Datenbankname endet auf `_staging`, `PHASE2_LIVE_DB_DATABASE` ist gesetzt und weicht ab, `--force` ist angegeben.

### Was der WhatsApp-Scrub entfernt oder zuruecksetzt

- Geloescht werden die Optionen `whatsapp.account_sid`, `whatsapp.auth_token`, `whatsapp.messaging_service_sid`, `whatsapp.sender` und `whatsapp.sandbox_sender`.
- `whatsapp.driver` wird auf `log` gesetzt, `whatsapp.enabled` auf `0` (Hauptschalter aus).
- Geleert werden `notification_log`, `notification_usage_monthly`, `notification_subscriptions` und `notification_consents`. Ein Abzug von Live traegt echte Zustimmungen und Versand-Metadaten; ohne Leeren koennten Sandbox-Modus oder `WHATSAPP_ALLOW_LIVE_ON_STAGING` echte Mitglieder erreichen.
- Budget, Preisschaetzung, Vorlagen-IDs und alle uebrigen Optionen bleiben erhalten.
- Die Ausgabe des Befehls nennt nur Options- und Tabellennamen, nie Werte.

### Wann ausfuehren

- Nach jedem Datenbankabzug von Live nach Staging, vor dem ersten Aufruf der Staging-Seite oder eines Cron-Laufs.
- Nur durch die Hauptsession bzw. den Verantwortlichen, nie automatisiert und nie gegen Live.

### Hinweis zu Backups

`DatabaseBackupService` sichert die Tabelle `bs_options` komplett. Backups tragen daher die verschluesselten Zugangsdaten; der passende Schluessel (`APP_KEY`) liegt auf demselben Host. Backups sind entsprechend wie Zugangsdaten zu behandeln, und nach einem Abzug nach Staging muss der Scrub laufen, bevor die Datenbank dort benutzt wird.
