<?php

return [
    'round_not_future' => 'drand-Runde :round ist nicht zukünftig (aktuelle Runde: :current).',

    'commit' => [
        'title_missing' => 'Titel fehlt.',
        'rule_missing' => 'Die Zuteilungsregel muss angegeben werden - sie wird mitveröffentlicht.',
        'round_invalid' => 'Beacon-Runde fehlt oder ist ungültig.',
        'min_entries' => 'Mindestens zwei Lose notwendig.',
        'count_mismatch' => 'Lose (:entries) und Slots (:slots) müssen gleich viele sein.',
        'duplicate_entries' => 'Die Lose enthalten Duplikate.',
        'duplicate_slots' => 'Die Slots enthalten Duplikate.',
        'invalid_entry' => 'Los ":entry" ist ungültig - erlaubt sind ganze Zahlen oder Kennungen wie A2 und Q3.',
        'entry_not_canonical' => 'Los ":entry" ist ungültig - Zahlen ohne führende Null und nicht größer als 9223372036854775807 eintragen.',
        'title_too_long' => 'Der Titel darf höchstens :max Zeichen lang sein.',
        'tid_invalid' => 'Die Turnier-ID ist ungültig.',
        'line_separator' => 'Zeilen- und Absatztrennzeichen (U+2028, U+2029) sind in Losen, Slots und Regel nicht erlaubt - das Prüfskript könnte die Ziehung sonst nicht nachrechnen.',
        'numeric_slot' => 'Slots dürfen nicht nur aus Ziffern bestehen (z. B. A2 statt 2) - das Prüfskript könnte die Ziehung sonst nicht nachrechnen.',
        'invalid_utf8' => 'Die Eingabe enthält ungültige Zeichen (kein gültiges UTF-8).',
        'beacon_failed' => 'Die Beacon-Runde konnte nicht geprüft werden: :message',
        'success' => 'Ziehung ":title" festgeschrieben. Jetzt den Protokoll-Link ankündigen, BEVOR die Beacon-Runde :round veröffentlicht wird.',
    ],
];
