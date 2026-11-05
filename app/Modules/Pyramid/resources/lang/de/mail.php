<?php

return [
    'prefix' => 'Forderungspyramide: ',

    'challenge_created' => [
        'subject' => 'Du wurdest gefordert',
        'body' => ":challenger hat dich in der Forderungspyramide gefordert.\n\nBitte vereinbart möglichst rasch einen Termin. Frist: :deadline.\n",
    ],
    'scheduled' => [
        'subject' => 'Termin für dein Forderungsspiel',
        'body' => "Das Forderungsspiel :challenger gegen :opponent findet am :date Uhr statt.\n",
    ],
    'result_entered' => [
        'subject' => 'Ergebnis eingetragen',
        'body' => "Für dein Forderungsspiel :challenger gegen :opponent wurde das Ergebnis :score eingetragen, Sieger: :winner.\n\nStimmt das nicht, bitte beim Ranglistenverantwortlichen melden.\n",
    ],
    'declined' => [
        'subject' => 'Forderung abgelehnt',
        'body' => ":opponent :reason und hat deine Forderung abgelehnt (Regel 5.2). Du bist wieder frei zu fordern.\n",
        'reason' => [
            'other_match' => 'hat noch ein anderes Forderungsspiel',
            'injury' => 'ist verletzt oder krank',
            'other' => 'hat abgelehnt',
        ],
    ],
    'withdrawn' => [
        'subject' => 'Forderung zurückgezogen',
        'body' => ":challenger hat die Forderung gegen dich zurückgezogen. Du bist wieder frei.\n",
    ],
    'deadline_extended' => [
        'subject' => 'Frist verlängert',
        'body' => "Die Frist für euer Forderungsspiel wurde bis :deadline verlängert.\nGrund: :note\n",
    ],
    'forfeited' => [
        'subject' => 'Kampflos gewertet',
        'body' => "Euer Forderungsspiel wurde kampflos gewertet, Sieger: :winner.\nGrund: :note\n",
    ],
    'expired' => [
        'subject' => 'Forderung verfallen',
        'body' => "Euer Forderungsspiel wurde ohne Wertung geschlossen.\nGrund: :note\n",
    ],
    'result_corrected' => [
        'subject' => 'Ergebnis richtiggestellt',
        'body' => "Das Ergebnis eures Forderungsspiels wurde richtiggestellt.\nGrund: :note\n",
    ],
    'admitted' => [
        'subject' => 'In die Rangliste aufgenommen',
        'body' => "Du wurdest in die Rangliste aufgenommen, auf Platz :position.\n",
    ],
    'released' => [
        'subject' => 'Zum Einfordern freigegeben',
        'body' => "Du darfst jetzt einen aktiven Spieler der Rangliste einfordern (Regel 6.1). Wähle einen Gegner, gegen den du realistische Chancen hast.\n",
    ],
];
