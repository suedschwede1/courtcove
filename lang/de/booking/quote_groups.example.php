<?php

declare(strict_types=1);

// Kopiere diese Datei nach lang/de/booking/quote_groups.php (nicht Teil von
// Git). Jede Gruppe hat ein Label (für das Dropdown in der Benutzerverwaltung)
// und eine Liste von Sprüchen. ':name' und ':names' werden zur Laufzeit durch
// den Vor-/Anzeigenamen des buchenden Mitglieds ersetzt (':names' ergibt die
// Possessivform, z. B. aus 'Anna' wird 'Annas').
//
// Weist man einem Benutzer in der Verwaltung eine dieser Gruppen zu, bekommt
// er nach einer Buchung bevorzugt Sprüche aus seiner Gruppe angezeigt
// (gemischt mit den allgemeinen Sprüchen aus lang/de/booking/public.php).

return [
    // 'beispiel_gruppe' => [
    //     'label' => 'Beispiel-Gruppe',
    //     'quotes' => [
    //         '🎾 :name, heute wird\'s besonders lustig!',
    //         '🏆 :names Aufschlag ist schon legendär.',
    //     ],
    // ],
];
