<?php

declare(strict_types=1);

return [
    'sets_incomplete' => 'Satz 1 und Satz 2 müssen vollständig eingetragen werden.',
    'mtb_after_two_sets' => 'Das Match war nach zwei Sätzen entschieden - der Match-Tiebreak muss leer bleiben.',
    'mtb_required' => 'Satzgleichstand 1:1 - der Match-Tiebreak muss eingetragen werden.',
    'mtb_both_scores' => 'Der Match-Tiebreak braucht beide Punktestände.',
    'set_range' => 'Satz :number: Gamestände müssen zwischen 0 und 7 liegen.',
    'set_invalid' => 'Satz :number: :score ist kein gültiges Satzergebnis. Möglich sind 6:0 bis 6:4, 7:5 und 7:6.',
    'mtb_negative' => 'Match-Tiebreak: negative Punkte sind nicht möglich.',
    'mtb_min_ten' => 'Match-Tiebreak :score - der Sieger braucht mindestens 10 Punkte.',
    'mtb_ten_nine' => 'Match-Tiebreak :score - bei 10:9 muss weitergespielt werden.',
    'mtb_two_apart' => 'Match-Tiebreak :score - über 10 Punkte hinaus sind genau zwei Punkte Unterschied notwendig.',
    'mtb_draw' => 'Match-Tiebreak: unentschieden ist nicht möglich.',
    'not_integer' => '":field": nur ganze Zahlen erlaubt.',
    'describe' => [
        'schedule_only' => 'Termin wird gespeichert – Ergebnis kann später nachgetragen werden.',
        'enter_sets' => 'Satzergebnisse eintragen – z. B. 6 : 4 und 6 : 3.',
        'set1_invalid' => 'Satz 1 ist kein gültiges Satzergebnis (6:0–6:4, 7:5 oder 7:6).',
        'set2_invalid' => 'Satz 2 ist kein gültiges Satzergebnis (6:0–6:4, 7:5 oder 7:6).',
        'mtb_must_be_empty' => 'Match ist nach zwei Sätzen entschieden – Match-Tiebreak bitte leer lassen.',
        'mtb_needed' => 'Satzstand 1:1 – Match-Tiebreak eintragen (auf 10, mindestens 2 Punkte Differenz).',
        'mtb_invalid' => 'Match-Tiebreak ungültig: auf 10 gespielt, mindestens 2 Punkte Differenz.',
    ],
];
