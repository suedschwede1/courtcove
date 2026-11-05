<?php

return [
    'title' => 'Losprotokoll',
    'lead' => 'Öffentlich und nachrechenbar – jeder kann das Ergebnis selbst prüfen.',
    'not_found' => 'Protokoll nicht gefunden',
    'discarded' => 'Diese Ziehung wurde verworfen.',
    'no_reason' => 'Kein Grund angegeben.',
    'status' => 'Status',
    'status_executed' => 'ausgeführt am :date',
    'status_pending' => 'festgeschrieben, noch nicht gezogen',
    'committed_at' => 'Festgeschrieben',
    'commitment' => 'Commitment',
    'beacon_source' => 'Zufallsquelle',
    'entries' => 'Lose',
    'slots' => 'Zielslots',
    'rule' => 'Regel',
    'pending_heading' => 'Noch nicht gezogen',
    'pending_text' => 'Die oben genannten Angaben sind durch das Commitment festgelegt und können nicht mehr geändert werden. Sobald die angegebene drand-Runde veröffentlicht ist, wird die Ziehung ausgeführt und der Server-Seed hier offengelegt.',
    'result' => 'Ergebnis',
    'slot' => 'Slot',
    'entry' => 'Los',
    'verify_heading' => 'Nachrechnen',
    'server_seed' => 'Server-Seed',
    'beacon_value' => 'Beacon-Wert',
    'final_seed' => 'Finaler Seed',
    'json_hint' => 'Alle Angaben in maschinenlesbarer Form: :link. Damit und mit dem Skript <code>tools/verify_draw.py</code> lässt sich die Ziehung ohne diesen Server nachrechnen.',
    'json_link' => 'JSON',
    'method' => 'Verfahren',
    'method_text' => <<<'TXT'
commitment = SHA256( canonical(payload) + "|" + serverSeed )
finalSeed  = HMAC-SHA256( key = serverSeed, message = beaconValue )

Fisher-Yates, absteigend von i = n-1 bis 1:
    j = ( erste 8 Bytes von SHA256( finalSeed + "|" + i ) als 63-Bit-Zahl ) mod (i+1)
    tausche Element i und j

Die gemischte Liste wird der Slot-Liste von oben nach unten zugeteilt.

canonical() ist JSON ohne Leerzeichen, ohne Unicode-Escapes und ohne
escapte Schrägstriche - in Python:
    json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
TXT,
    'beacon_note' => 'Der Beacon-Wert stammt aus dem drand-Netzwerk und existierte zum Zeitpunkt des Commitments noch nicht. Damit konnte weder der Betreiber das Ergebnis vorherberechnen noch die Lose nachträglich umsortieren.',
];
