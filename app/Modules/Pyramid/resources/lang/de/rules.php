<?php

return [
    'title' => 'Regeln der Forderungspyramide',
    'toc' => 'Inhalt',

    'right' => [
        'title' => 'Forderungsrecht',
        'text' => 'Gefordert werden kann jeder, der in der Pyramide in der gleichen Reihe links oder eine Reihe darüber steht. Wer auf Platz 3 steht, kann also Platz 1 und Platz 2 fordern.',
    ],
    'mode' => [
        'title' => 'Spielmodus',
        'text' => 'Zwei Gewinnsätze inklusive Tie-Break, dritter Satz als Champions-Tie-Break bis 10. Sind sich beide einig, kann auch ein voller dritter Satz gespielt werden.',
    ],
    'flow' => [
        'title' => 'Ablauf',
        'item_1' => 'Der Herausforderer vereinbart den Termin selbst und so schnell wie möglich.',
        'item_2' => 'Zwischen Forderung und Spiel sollen nicht mehr als :days_to_play Tage liegen.',
        'item_3' => 'Solange eine Forderung offen ist, dürfen beide Beteiligten keine weitere Forderung aussprechen oder annehmen.',
        'item_4' => 'Abgebrochene oder verregnete Spiele werden innerhalb einer Woche nachgeholt, ein abgebrochenes Spiel beim letzten Spielstand fortgesetzt.',
    ],
    'after' => [
        'title' => 'Nach dem Spiel',
        'item_1' => 'Das Ergebnis trägt einer der beiden Beteiligten hier ein.',
        'item_2' => 'Gewinnt der Herausforderer, rückt er auf den Platz des Verlierers. Der Verlierer und alle dazwischen liegenden Spieler fallen einen Platz zurück.',
        'item_3' => 'Verliert der Herausforderer, ändert sich nichts. Er darf erst nach :loser_cooldown_days Tagen wieder fordern.',
        'item_4' => 'Der Sieger einer Forderung darf :winner_protected_days Tage lang nicht gefordert werden.',
    ],
    'decline' => [
        'title' => 'Ablehnung',
        'text' => 'Abgelehnt werden darf, wenn noch ein anderes Forderungsspiel offen ist oder wenn Verletzung beziehungsweise Krankheit vorliegt. Wer ohne triftigen Grund ablehnt oder nicht zum Termin erscheint (15 Minuten Wartezeit), hat das Spiel verloren.',
    ],
    'entry' => [
        'title' => 'Neueintritt',
        'text' => 'Neue Spieler melden sich an und werden von der Verwaltung aufgenommen — entweder am Ende der Rangliste oder über ein Einforderungsspiel gegen einen Spieler der Wahl. Wird dieses gewonnen, rückt der Neueinsteiger auf dessen Platz; wird es verloren, reiht er sich am Ende ein.',
    ],
    'fair' => [
        'title' => 'Fair Play',
        'text' => 'Das oberste Prinzip lautet Fair Play, auf dem Platz und außerhalb. Bei Fragen, Missverständnissen oder Streitfällen entscheidet der Ranglistenverantwortliche.',
    ],
];
