# WhatsApp-Benachrichtigungen: Runbook

Betriebsanleitung fuer den WhatsApp-Kanal. Dieses Dokument ist anbieterneutral; Zugangsdaten und Anbieter-Adressen stehen nie hier, sondern in `.env` bzw. in den Admin-Optionen (`whatsapp.*`).

## Voraussetzungen und Betrieb

### Cron (Voraussetzung fuer Live)

Im Hosting-Panel muss ein Cron-Eintrag minuetlich `php artisan schedule:run` im Projektverzeichnis ausfuehren. Ohne Cron verzoegert sich der Versand: Zeilen im Versandprotokoll werden erst nach 2 Minuten Debounce faellig und vom geplanten Dispatch-Lauf abgeholt. Das gilt fuer die Spielersuchen-Nachrichten (seit Task 10 eingereiht, siehe "Stand der Anbindung"); die Testnachricht aus dem Admin geht direkt hinaus. Auch Aufraeumen (Prune) und Budget-Alarme haengen am Scheduler.

### `.env`-Schluessel (nur Namen, keine Werte)

Zugangsdaten kommen per `.env` bzw. Admin-Option, nie in getrackte Dateien. Die Anbieter-Literale (Basis-Adresse, Endpunkt-Pfade, Name des Signatur-Headers) und `WHATSAPP_WEBHOOK_BASE_URL` gibt es nur per `.env`: Die Admin-Seite hat dafuer kein Feld, und es gibt keinen getrackten Default.

- `WHATSAPP_DRIVER` (`log`, `fake` oder `live`; ohne Wert gilt `log`)
- `WHATSAPP_ACCOUNT_SID`, `WHATSAPP_AUTH_TOKEN`, `WHATSAPP_MESSAGING_SERVICE_SID`, `WHATSAPP_SENDER`
- `WHATSAPP_SANDBOX`, `WHATSAPP_SANDBOX_SENDER`
- `WHATSAPP_API_BASE`, `WHATSAPP_API_MESSAGES_PATH`, `WHATSAPP_API_ACCOUNT_PATH`, `WHATSAPP_API_SERVICE_PATH` (nur `.env`; Basis-Adresse und Endpunkt-Pfade des Anbieters; fehlt ein Wert bei Treiber `live`, meldet das Gate `config_missing`)
- `WHATSAPP_SIGNATURE_HEADER` (nur `.env`; Name des Signatur-Headers; ohne Wert oder ohne Token antwortet der Webhook mit 403)
- `WHATSAPP_WEBHOOK_BASE_URL` (nur `.env`; Basis-Adresse der Webhooks, siehe Webhook-Adressen)
- `WHATSAPP_ALLOW_LIVE_ON_STAGING` (nur bewusst und nur kurzzeitig; siehe Staging-Ablauf)

Der Hauptschalter und das Budget stehen in den Admin-Optionen (Einstellungen fuer WhatsApp-Benachrichtigungen), nicht in der `.env`.

### `APP_KEY`-Wechsel

Geheimnisse in den Optionen (Token, Kennungen) sind mit `APP_KEY` verschluesselt, Telefonnummern-Hashes der Zustimmungen werden mit demselben Schluessel gebildet. Ein Wechsel von `APP_KEY` macht beides unlesbar: Der Token muss neu eingegeben werden, und bestehende Zustimmungen passen praktisch nicht mehr zu den Nummern, sie verfallen also. Den Schluessel nur im Notfall wechseln und danach die Zugangsdaten neu setzen; die Mitglieder muessen erneut zustimmen.

### Webhook-Adressen

Zwei Adressen sind beim Anbieter zu hinterlegen:

- `/webhooks/whatsapp/status` (Zustellstatus)
- `/webhooks/whatsapp/inbound` (eingehende Nachrichten, z. B. STOP)

Die Basis ist `WHATSAPP_WEBHOOK_BASE_URL`, sonst `APP_URL`; beide ohne Pfad, sonst stimmt die Signaturpruefung nicht (die Signatur deckt die vollstaendige Adresse ab).

### Protokollierung

Aenderungen an den Einstellungen werden als Standard-Logzeile ohne Werte geschrieben. Mit `LOG_STACK=single` gibt es keine Rotation; die Zeilen landen im Standard-Log, das entsprechend gross werden kann und vom Betrieb gelegentlich zu leeren oder zu rotieren ist.

### Staging-Ablauf

1. Auf Staging bleibt der Treiber `log` (nur Protokoll, kein Netzwerk) und der Hauptschalter aus.
2. Fuer echte Tests Sandbox verwenden (`WHATSAPP_SANDBOX`, Sandbox-Absender): Nachrichten gehen nur an Nummern, die sich selbst fuer die Sandbox angemeldet haben.
3. Treiber `live` auf einem Host, dessen Datenbankname auf `_staging` endet, wird ohne `WHATSAPP_ALLOW_LIVE_ON_STAGING` auf `log` herabgestuft. Das Flag nur fuer einen kurzen, abgestimmten Test setzen und danach wieder entfernen.
4. Nach jedem Datenbankabzug von Live den Scrub ausfuehren (Abschnitt Staging-Scrub unten).

### Budget

Das Monatsbudget begrenzt den Versand; bei 80 % und bei 100 % geht eine E-Mail an die Admins, bei 100 % stoppt der Versand bis zum Monatsende oder bis zur Erhoehung. Die Kosten sind eine Schaetzung auf Basis der hinterlegten Preise, nicht die Abrechnung des Anbieters; die Rechnung des Anbieters ist massgeblich.

### Stand der Anbindung

Die Konto-Karte mit der Einwilligung (Task 11) ist fertig: Mitglieder stimmen unter "Mein Konto" in der Karte "WhatsApp-Benachrichtigungen" zu, schalten die Spielersuche ein oder aus, setzen Filter (Wochentage, Uhrzeit von/bis, Einzel/Doppel) und melden sich dort wieder ab (Widerruf ueber das Konto wird mit `revoked_via = account` gespeichert). Die Karte ist unsichtbar, solange der Hauptschalter aus ist. Zustimmung verlangt das komplette Gate: aktives Konto, verknuepftes aktives Mitglied, Mobilnummer im Profil und eingeschaltete Spielerboerse. Ist die Spielerboerse aus, erklaert die Karte "Boerse zuerst aktivieren"; Ausschalten der Boerse widerruft eine bestehende Zustimmung nicht, es pausiert nur den Versand. Die Testnachricht an den eigenen Admin braucht jetzt die Zustimmung ueber diese Konto-Karte; der Admin braucht trotzdem ein aktives, verknuepftes Mitglied, eine Mobilnummer und die eingeschaltete Spielerboerse, sonst verweigert die Karte die Zustimmung. Der Zustimmungstext (`booking.notifications.consent_text_v1`, Version `wa-2026-11-v1`) und der Datenschutz-Satz (`privacy_notice`) stehen in den Sprachdateien; der Datenschutz-Link der Karte kommt aus der Option `client.website.privacy`.

Die Anbindung der Spielerboerse (Task 10) ist fertig. Mail und WhatsApp gehen doppelt: die Boersen-Mails bleiben unveraendert und gehen weiter an alle Boersen-Mitglieder (nur mit eingerichtetem Mailserver), die WhatsApp-Nachricht zusaetzlich an Mitglieder mit Zustimmung, auch ohne Mailserver.

- Ausloeser: Beim Anlegen einer Buchung mit Spielersuche wird nach der Antwort (im Konsolenlauf sofort) das Ereignis `PlayerSearchStarted` ausgeloest. Der Listener schreibt nur Zeilen ins Versandprotokoll (Status `pending`) und sendet selbst nichts; ein Fehler im Listener wird gemeldet und beruehrt weder Buchung noch Mail.
- Empfaenger: dieselbe Auswahl wie die Boersen-Mail (aktive Konten mit E-Mail und eingeschalteter Boerse, ohne Bucher), zusaetzlich aktive Zustimmung fuer die Spielersuche, bestandenes Gate, Schalter "Bei Spielersuchen benachrichtigen" an (fehlender Eintrag = aus), passende Filter (Wochentage, Spielbeginn von/bis einschliesslich, Einzel/Doppel) und noch nicht angemeldet. Einen Platz-Filter gibt es nicht (offene Entscheidung). Reihenfolge: wer in diesem Monat die wenigsten Nachrichten bekommen hat, zuerst; die Faelligkeit ist je Empfaenger um eine Sekunde gestaffelt.
- 2-Minuten-Debounce gilt jetzt: die Zeilen werden 2 Minuten nach dem Anlegen faellig. Beim Senden wird geprueft, ob die Suche noch offen ist, noch Plaetze frei sind, die Frist (Ende der Suche, 1 Stunde vor Spielbeginn) mehr als 15 Minuten entfernt ist und die Buchung aktiv ist; sonst `skipped/subject_gone`. Wiederholungen werden nie nach der Frist geplant. Wird die Suche beendet, gefuellt, storniert, geloescht oder verfaellt sie, werden noch wartende Zeilen sofort `skipped/subject_gone`.
- Inhalt: Datum (`Sa, 14.11.`), Uhrzeit, Platz, Anzahl gesucht, Vor- und Nachname der suchenden Person (ersatzweise der Anzeigename) und als Button-Suffix `calendar?date=YYYY-MM-DD&join={bid}`. Die Werte werden beim Senden neu aufgebaut und nie gespeichert; im Sandbox-Modus geht der Freitext aus den Sprachdateien (`booking.notifications.whatsapp.player_search_opened`/`player_search_closed`) hinaus. Hinweis: der Kalender wertet `join` derzeit nicht aus, der Link oeffnet den Tag.
- Folge-Nachricht "Suche abgeschlossen": nur bei Option `whatsapp.followup_closed = 1` (Standard aus), nur an Mitglieder, deren Spielersuche-Nachricht tatsaechlich gesendet wurde, hoechstens eine je Suche, 5 Minuten verzoegert (oeffnet die Suche in der Zeit wieder, entfaellt sie). Vorlagen-ID unter `whatsapp.template.player_search_closed.<locale>`.

### Aufgaben des Vereins

- Konto beim Messaging-Anbieter anlegen, Zahlungsmethode hinterlegen.
- Absender (WhatsApp-Business-Nummer) registrieren; das erfordert eine Unternehmenspruefung beim Anbieter und kann dauern.
- Einen zweiten Admin im Konto des Anbieters anlegen (Vertretung, Zugriff bei Ausfall).
- Nachrichtenvorlagen anlegen und von WhatsApp freigeben lassen; die Vorlagen-IDs in den Admin-Optionen eintragen.
- Auftragsverarbeitungsvertrag mit dem Anbieter abschliessen; Anbieter und Drittlanduebermittlung in der externen Datenschutzseite des Vereins nennen.

## Go-Live-Checkliste

Vor dem Umschalten auf Treiber `live` und Hauptschalter an:

- [ ] Fehlercode-Tabelle (`ProviderTransport::ERROR_MAP`) und Antwortformat des Webhooks (`WebhookReply`) gegen die aktuelle Dokumentation des Anbieters geprueft.
- [ ] `APP_URL` (bzw. `WHATSAPP_WEBHOOK_BASE_URL`) ohne Pfad und mit `https`.
- [ ] Cache-Store geprueft: Drain-Sperre und Hilfe-Limit nutzen `Cache::add`; beim `file`-Store ist die Atomaritaet nicht strikt garantiert, besser `database` oder `redis`.
- [ ] Datenbank-Version: MySQL 8 oder MariaDB ab 10.6 (wegen `SKIP LOCKED` beim Abholen faelliger Zeilen).
- [ ] Budget-Drift beachtet: `messages_reserved` kann bei einem Prozessabbruch dauerhaft erhoeht bleiben; zum Monatswechsel und bei Auffaelligkeiten mit dem Versandprotokoll und der Anbieter-Abrechnung abgleichen.
- [ ] Cron laeuft (`schedule:run` minuetlich).
- [ ] Testnachricht aus den Admin-Einstellungen kommt an (Voraussetzung: der Admin hat in seiner Konto-Karte "WhatsApp-Benachrichtigungen" zugestimmt; dafuer braucht er aktives Mitglied, Mobilnummer und eingeschaltete Spielerboerse).
- [ ] Leitet der Live-Host `/webhooks/*` an Laravel weiter (Bruecke/`.htaccess`), und ist die Webhook-Basis der Booking-Host, nicht der Turnier-Host?
- [ ] Anbieter-Literale und `WHATSAPP_WEBHOOK_BASE_URL` stehen in der Live-`.env` (nicht in den Admin-Optionen).
- [ ] Sandbox-Test mit einer eigenen Nummer erfolgreich.
- [ ] STOP-Test: Antwort mit STOP meldet ab, Bestaetigung kommt, danach kein Versand mehr.
- [ ] Hauptschalter bewusst gesetzt; zum Abschalten genuegt es, ihn auszuschalten (Rollback ohne Deploy).

## Vom Auftraggeber zu bestaetigen

- Zustimmungstext (`consent_text_v1`, de/en) und Versionsschluessel `wa-2026-11-v1`.
- Datenschutz-Satz (`privacy_notice`, de/en); Namen der Dienstleister und Drittlanduebermittlung gehoeren in die externe Datenschutzseite.
- Vorlagentexte: Spielersuche (de/en) einschliesslich Vor- und Nachname der suchenden Person; ob WhatsApp sie als Utility-Vorlage einstuft, ist offen. Dazu die Folge-Nachricht und die Testnachricht.
- Ob der Hinweis auf der Registrierungsseite (Option `registration_privacy`, im Admin unter Einstellungen editierbar) den neuen Zweck nennen soll. Die Registrierungsseite wurde nicht geaendert; die Einwilligung wird getrennt im Konto eingeholt.

## Staging-Scrub

`php artisan phase2:scrub-staging --force` bereinigt die Staging-Datenbank. Nach den Modul-Scrubs laufen alle mit `phase2.config-scrub` getaggten Config-Scrubs, darunter der WhatsApp-Scrub. Die bestehenden Schutzschranken gelten unveraendert: Datenbankname endet auf `_staging`, `PHASE2_LIVE_DB_DATABASE` ist gesetzt und weicht ab, `--force` ist angegeben.

### Was der WhatsApp-Scrub entfernt oder zuruecksetzt

- Geloescht werden die Optionen `whatsapp.account_sid`, `whatsapp.auth_token`, `whatsapp.messaging_service_sid`, `whatsapp.sender`, `whatsapp.sandbox_sender`, `whatsapp.sandbox`, `whatsapp.webhook_base_url`, `whatsapp.api_base`, `whatsapp.api_messages_path`, `whatsapp.api_account_path`, `whatsapp.api_service_path`, `whatsapp.signature_header` (sofern als Option gesetzt), `whatsapp.budget_alert_email` (Admin-Adresse aus dem Abzug) und `whatsapp.paused_reason`. Werte aus der `.env` der Staging-Instanz bleiben davon unberuehrt.
- `whatsapp.driver` wird auf `log` gesetzt, `whatsapp.enabled` auf `0` (Hauptschalter aus).
- Geleert werden `notification_log`, `notification_usage_monthly`, `notification_subscriptions` und `notification_consents`. Ein Abzug von Live traegt echte Zustimmungen und Versand-Metadaten; ohne Leeren koennten Sandbox-Modus oder `WHATSAPP_ALLOW_LIVE_ON_STAGING` echte Mitglieder erreichen.
- Budget, Preisschaetzung, Vorlagen-IDs und alle uebrigen Optionen bleiben erhalten.
- Die Ausgabe des Befehls nennt nur Options- und Tabellennamen, nie Werte.

### Wann ausfuehren

- Nach jedem Datenbankabzug von Live nach Staging, vor dem ersten Aufruf der Staging-Seite oder eines Cron-Laufs.
- Nur durch die Hauptsession bzw. den Verantwortlichen, nie automatisiert und nie gegen Live.

### Hinweis zu Backups

`DatabaseBackupService` sichert die Tabelle `bs_options` komplett. Backups tragen daher die verschluesselten Zugangsdaten; der passende Schluessel (`APP_KEY`) liegt auf demselben Host. Backups sind entsprechend wie Zugangsdaten zu behandeln, und nach einem Abzug nach Staging muss der Scrub laufen, bevor die Datenbank dort benutzt wird.
