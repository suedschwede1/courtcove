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

Geheimnisse in den Optionen (Token, Kennungen) sind mit `APP_KEY` verschluesselt, Telefonnummern-Hashes der Zustimmungen werden mit demselben Schluessel gebildet. Ein Wechsel von `APP_KEY` macht beides unlesbar: Der Token muss neu eingegeben werden, und bestehende Zustimmungen passen praktisch nicht mehr zu den Nummern, sie verfallen also. Den Schluessel nur im Notfall wechseln und danach die Zugangsdaten neu setzen; alle Benutzer muessen erneut zustimmen.

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

Die Konto-Karte mit der Einwilligung (Task 11) ist fertig: Benutzer stimmen unter "Mein Konto" in der Karte "WhatsApp-Benachrichtigungen" zu, schalten die Spielersuche ein oder aus, setzen Filter (Wochentage, Uhrzeit von/bis, Einzel/Doppel) und melden sich dort wieder ab (Widerruf ueber das Konto wird mit `revoked_via = account` gespeichert). Die Karte ist unsichtbar, solange der Hauptschalter aus ist. Zustimmung verlangt das komplette Gate, und das prueft nur das Benutzerkonto: Hauptschalter an, Konto aktiv (nicht gesperrt, deaktiviert oder geloescht), gueltige Mobilnummer im Profil und eingeschaltete Spielerboerse. Ein verknuepfter Mitgliedsdatensatz ist nicht noetig; sein Status (ruhend, ausgetreten, anonymisiert) spielt keine Rolle (Entscheidung 2026-11-08/09). Ist die Spielerboerse aus, erklaert die Karte "Boerse zuerst aktivieren"; Ausschalten der Boerse widerruft eine bestehende Zustimmung nicht, es pausiert nur den Versand. Die Testnachricht an den eigenen Admin braucht die Zustimmung ueber diese Konto-Karte (dafuer: aktives Konto, Mobilnummer, eingeschaltete Spielerboerse; ein Mitgliedsdatensatz ist nicht noetig). Beim Senden der Testnachricht wird die Spielerboerse nicht mehr geprueft. Der Zustimmungstext (`booking.notifications.consent_text_v1`, Version `wa-2026-11-v1`) und der Datenschutz-Satz (`privacy_notice`) stehen in den Sprachdateien; der Datenschutz-Link der Karte kommt aus der Option `client.website.privacy`.

Die Anbindung der Spielerboerse (Task 10) ist fertig. Mail und WhatsApp gehen doppelt: die Boersen-Mails bleiben unveraendert und gehen weiter an alle Boersen-Teilnehmer (aktive Konten mit E-Mail und eingeschalteter Boerse, nur mit eingerichtetem Mailserver), die WhatsApp-Nachricht zusaetzlich an Teilnehmer mit Zustimmung, auch ohne Mailserver.

- Ausloeser: Beim Anlegen einer Buchung mit Spielersuche wird nach der Antwort (im Konsolenlauf sofort) das Ereignis `PlayerSearchStarted` ausgeloest. Der Listener schreibt nur Zeilen ins Versandprotokoll (Status `pending`) und sendet selbst nichts; ein Fehler im Listener wird gemeldet und beruehrt weder Buchung noch Mail.
- Empfaenger: dieselbe Auswahl wie die Boersen-Mail (aktive Konten mit E-Mail und eingeschalteter Boerse, ohne Bucher), zusaetzlich aktive Zustimmung fuer die Spielersuche, bestandenes Gate, Schalter "Neue Spielersuche" in der Konto-Karte an (fehlender Eintrag = aus), passende Filter (Wochentage, Spielbeginn von/bis einschliesslich, Einzel/Doppel) und noch nicht angemeldet. Einen Platz-Filter gibt es nicht (offene Entscheidung). Reihenfolge: wer in diesem Monat die wenigsten Nachrichten bekommen hat, zuerst; die Faelligkeit ist je Empfaenger um eine Sekunde gestaffelt.
- 2-Minuten-Debounce gilt jetzt: die Zeilen werden 2 Minuten nach dem Anlegen faellig. Beim Senden wird geprueft, ob die Suche noch offen ist, noch Plaetze frei sind, die Frist (Ende der Suche, 1 Stunde vor Spielbeginn) mehr als 15 Minuten entfernt ist und die Buchung aktiv ist; sonst `skipped/subject_gone`. Wiederholungen werden nie nach der Frist geplant. Wird die Suche beendet, gefuellt, storniert, geloescht oder verfaellt sie, werden noch wartende Zeilen sofort `skipped/subject_gone`.
- Inhalt: Datum (`Sa, 14.11.`), Uhrzeit, Platz, Anzahl gesucht, Vor- und Nachname der suchenden Person (ersatzweise der Anzeigename) und als Button-Suffix `calendar?date=YYYY-MM-DD&join={bid}`. Die Werte werden beim Senden neu aufgebaut und nie gespeichert; im Sandbox-Modus geht der Freitext aus den Sprachdateien (`booking.notifications.whatsapp.player_search_opened`/`player_search_closed`) hinaus. Hinweis: der Kalender wertet `join` derzeit nicht aus, der Link oeffnet den Tag.
- Folge-Nachricht "Suche abgeschlossen": nur wenn die Nachrichtenart "Suche beendet" auf der Admin-Seite aktiv ist (Option `whatsapp.type.player_search.closed.enabled`, Standard aus; die alte Option `whatsapp.followup_closed` gilt nur noch, solange die neue fehlt, und wird beim Speichern entfernt), nur an Benutzer, deren Spielersuche-Nachricht tatsaechlich gesendet wurde, hoechstens eine je Suche, 5 Minuten verzoegert (oeffnet die Suche in der Zeit wieder, entfaellt sie). Vorlagen-ID unter `whatsapp.template.player_search_closed.<locale>`.

### Widerruf und Konto-Lebenszyklus

Zustimmungen enden automatisch bei Aenderung oder Entfernen der Mobilnummer (`phone_changed`), bei Konto-Status gesperrt, deaktiviert oder geloescht bzw. beim Loeschen ueber die Benutzerliste (`user_status`), per STOP (`stop`) und ueber die Konto-Karte (`account`). Austritt, Ruhendstellung, Entkopplung, Loeschen oder Anonymisieren eines Mitgliedsdatensatzes beenden keine Zustimmung: das Benutzerkonto lebt weiter, und die Anonymisierung betrifft nur die Mitgliedsdaten (sie entkoppelt das Konto, aendert es aber nicht). Aeltere Zeilen koennen noch `member_exit` oder `anonymized` tragen; diese Werte werden nicht mehr geschrieben.

Wichtig: Der Austritt eines Mitglieds deaktiviert oder sperrt das Benutzerkonto nicht. Ein ausgetretenes Mitglied mit weiter aktivem Konto, Mobilnummer, Boerse und Zustimmung bekommt deshalb weiter WhatsApp-Nachrichten (und, unveraendert, die Boersen-Mails). Soll es keine mehr bekommen, das Konto in der Benutzerverwaltung sperren oder deaktivieren; das widerruft die Zustimmung sofort.

### Aufgaben des Vereins

- Konto beim Messaging-Anbieter anlegen, Zahlungsmethode hinterlegen.
- Absender (WhatsApp-Business-Nummer) registrieren; das erfordert eine Unternehmenspruefung beim Anbieter und kann dauern.
- Einen zweiten Admin im Konto des Anbieters anlegen (Vertretung, Zugriff bei Ausfall).
- Nachrichtenvorlagen anlegen und von WhatsApp freigeben lassen; die Vorlagen-IDs in den Admin-Optionen eintragen.
- Auftragsverarbeitungsvertrag mit dem Anbieter abschliessen; Anbieter und Drittlanduebermittlung in der externen Datenschutzseite des Vereins nennen.

## Nachrichtenarten und Zwecke (Registry)

Alle Nachrichtenarten (Typen) und Einwilligungszwecke stehen in einer zentralen Registry (`App\Notifications\Registry\NotificationRegistry`, eingebaute Eintraege in `BuiltInNotifications`). Versand, Gate, Budget, Webhooks, Konto-Karte und Admin-Seite lesen Typen und Zwecke von dort. Bewusste Ausnahmen, die einen eingebauten Eintrag beim Namen kennen: der Admin-Test (Typ `whatsapp.test`, sein Zweck kommt aber aus der Definition), die Konto-Routen ohne Zweck-Parameter (Standard `player_search`, damit die alten Adressen gelten), `ConsentService::gate()/activeConsent()` mit `player_search` als Vorgabe-Zweck und die Spielerboersen-Teile selbst (Empfaengerauswahl, Ausloeser, Listener).

- Je Typ auf der Admin-Seite (Abschnitt "Benachrichtigungstypen"): Schalter aktiv/inaktiv (`whatsapp.type.<key>.enabled`, ausgeschaltete Typen werden nicht eingereiht, wartende Zeilen werden `skipped/type_disabled`), Vorlagen-ID je Sprache (`whatsapp.template.<templateKey>.<locale>`, ohne ID wird `skipped/template_missing`, die Seite zeigt dann einen Hinweis), Variablenliste, optionaler eigener Preis (`whatsapp.price.<key>`, leer = allgemeiner Preis; die Testnachricht rechnet immer mit dem allgemeinen Preis) und die Zahlen des Versands (Monat, letzte 30 Tage, zuletzt gesendet).
- Je Zweck in der Konto-Karte eine eigene Karte mit eigenem Zustimmungstext, eigener Textversion, eigenem Gate (Zweck-Bedingung, z. B. Spielerboerse an) und eigenem Widerruf. Sichtbar und zustimmbar, wenn mindestens ein abonnierbarer Typ des Zwecks (keine Folge-Nachricht, keine Testnachricht) aktiv ist; hat der Benutzer noch eine aktive Zustimmung, bleibt die Karte sichtbar und bietet ohne aktiven Typ nur den Widerruf. Schalter je Typ (nicht fuer Folge-Nachrichten und die Testnachricht), Filter nur wo der Typ welche definiert. Adressen: `/my-account/whatsapp/{zweck}`; ohne Zweck die Spielerboerse (alte Adressen).
- STOP widerruft alle Zwecke der Nummer. Eine Zustimmung fuer einen nicht mehr registrierten Zweck laesst niemanden durchs Gate; ihr Widerruf loescht alle Abos des Benutzers.

### Neuen WhatsApp-Typ hinzufuegen

1. Definition registrieren: in einem Service-Provider (oder Modul-Provider) in `boot()` `app(NotificationRegistry::class)->register(new NotificationTypeDefinition(...), new NotificationPurposeDefinition(...))` aufrufen. Nicht in `register()`: die Registry ist ein Singleton des AppServiceProvider, ein frueherer Zugriff baut sie zu frueh oder ueberschreibt die eingebauten Eintraege. Den Zweck nur mitgeben, wenn er neu ist; ein Typ eines bestehenden Zwecks braucht nur seine Definition. Braucht der Zweck eine eigene Voraussetzung (wie die Spielerboerse "Boerse an"), in der Zweck-Definition `gateCondition` setzen: eine Klasse, die `PurposeGateCondition` implementiert (`failure(User): ?string` liefert den Ablehnungsgrund), oder eine Closure; ohne Bedingung zaehlt nur das allgemeine Gate. Felder: `key` (wird in `notification_log.type` gespeichert, nie umbenennen), `templateKey`, Lang-Keys fuer Bezeichnung/Beschreibung/Variablen, `resolver`, `purpose`, `priority`, `defaultEnabled` (neue Typen am besten `false`, dann bewusst einschalten), `filters` (optional), `followupOf`, `optIn`.
2. Resolver-Klasse anlegen (`SubjectResolver`): `stillRelevant()` prueft beim Senden, ob der Vorgang noch zaehlt, `build()` baut die Variablen aus den IDs in `subject_payload` neu auf (nie Namen oder Texte speichern). Hat der Vorgang eine Frist, zusaetzlich `SubjectDeadline` (keine Wiederholung nach der Frist).
3. Ausloeser: eine Laravel-Notification mit `via() = [WhatsAppChannel::class]` und `toWhatsApp()`, die eine `WhatsAppMessage` mit Typ-Key, eindeutigem Dedupe-Schluessel (z. B. `<key>:<vorgang>:<benutzer>:whatsapp`), Faelligkeit und `subjectPayload` (nur IDs) liefert. Empfaenger waehlt der Ausloeser selbst (Zustimmung, Gate und Abo des Typs pruefen); der Kanal schreibt nur ins Versandprotokoll.
4. Texte in `lang/de` und `lang/en`: Typ (Bezeichnung, Beschreibung, Variablenliste 1..n), bei neuem Zweck Bezeichnung, Beschreibung, Zustimmungstext und Textversion (Version erhoehen, wenn sich der Text wesentlich aendert), bei einer Zweck-Bedingung ein Text unter `booking.notifications.refused.<grund>` (sonst erscheint der allgemeine Text). Freitext fuer den Sandbox-Modus im Resolver aus den Sprachdateien.
5. Vorlagen je Sprache beim Anbieter anlegen und von WhatsApp freigeben lassen, dann die IDs auf der Admin-Seite in der Karte des Typs eintragen; optional einen eigenen Preis setzen.
6. Tests: `tests/Feature/Notifications/DummyNotificationTypeTest.php` (Versand, Budget, Widerruf, STOP) und `DummyNotificationWebTest.php` (Konto-Karte, Admin-Seite) als Vorlage; der neue Typ muss ohne Aenderung an Gate, Dispatcher, Budget, Webhooks, Karte und Admin-Seite durchlaufen.
7. Checkliste vor dem Einschalten: Vorlagen freigegeben und eingetragen (kein Hinweis "Vorlage fehlt"), Texte de/en geprueft, Zustimmungstext vom Verein freigegeben und Datenschutzseite ergaenzt, Preis geprueft, auf Staging mit Treiber `log` ausgeloest und im Versandprotokoll kontrolliert, dann den Typ auf der Admin-Seite aktivieren.

## Go-Live-Checkliste

Vor dem Umschalten auf Treiber `live` und Hauptschalter an:

- [ ] Fehlercode-Tabelle (`ProviderTransport::ERROR_MAP`) und Antwortformat des Webhooks (`WebhookReply`) gegen die aktuelle Dokumentation des Anbieters geprueft.
- [ ] `APP_URL` (bzw. `WHATSAPP_WEBHOOK_BASE_URL`) ohne Pfad und mit `https`.
- [ ] Cache-Store geprueft: Drain-Sperre und Hilfe-Limit nutzen `Cache::add`; beim `file`-Store ist die Atomaritaet nicht strikt garantiert, besser `database` oder `redis`.
- [ ] Datenbank-Version: MySQL 8 oder MariaDB ab 10.6 (wegen `SKIP LOCKED` beim Abholen faelliger Zeilen).
- [ ] Budget-Drift beachtet: `messages_reserved` kann bei einem Prozessabbruch dauerhaft erhoeht bleiben; zum Monatswechsel und bei Auffaelligkeiten mit dem Versandprotokoll und der Anbieter-Abrechnung abgleichen.
- [ ] Cron laeuft (`schedule:run` minuetlich).
- [ ] Nach jedem Deploy `php artisan event:cache` bzw. `php artisan optimize` neu laufen lassen, falls diese Caches genutzt werden: die Listener der Spielersuche werden per Auto-Discovery gefunden, ein alter Event-Cache kennt sie nicht.
- [ ] Button-Suffix `join={bid}`: der Kalender wertet `join` noch nicht aus (der Link oeffnet nur den Tag). Vor der Vorlagen-Freigabe entscheiden: im Kalender auswerten oder den Suffix der Vorlage aendern.
- [ ] Testnachricht aus den Admin-Einstellungen kommt an (Voraussetzung: der Admin hat in seiner Konto-Karte "WhatsApp-Benachrichtigungen" zugestimmt; dafuer braucht er ein aktives Konto, eine Mobilnummer und die eingeschaltete Spielerboerse, aber keinen Mitgliedsdatensatz).
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
- Geleert werden `notification_log`, `notification_usage_monthly`, `notification_subscriptions` und `notification_consents`. Ein Abzug von Live traegt echte Zustimmungen und Versand-Metadaten; ohne Leeren koennten Sandbox-Modus oder `WHATSAPP_ALLOW_LIVE_ON_STAGING` echte Personen erreichen.
- Budget, Preisschaetzung, Vorlagen-IDs und alle uebrigen Optionen bleiben erhalten.
- Die Ausgabe des Befehls nennt nur Options- und Tabellennamen, nie Werte.

### Wann ausfuehren

- Nach jedem Datenbankabzug von Live nach Staging, vor dem ersten Aufruf der Staging-Seite oder eines Cron-Laufs.
- Nur durch die Hauptsession bzw. den Verantwortlichen, nie automatisiert und nie gegen Live.

### Hinweis zu Backups

`DatabaseBackupService` sichert die Tabelle `bs_options` komplett. Backups tragen daher die verschluesselten Zugangsdaten; der passende Schluessel (`APP_KEY`) liegt auf demselben Host. Backups sind entsprechend wie Zugangsdaten zu behandeln, und nach einem Abzug nach Staging muss der Scrub laufen, bevor die Datenbank dort benutzt wird.
