<?php

return [
    'module' => 'Forderungspyramide',

    'no_ladder' => 'Es ist noch keine Pyramide angelegt.',
    'login_to_play' => 'Zum Fordern musst du angemeldet sein.',
    'unknown' => 'Unbekannt',
    'you' => 'du',
    'paused' => 'pausiert',

    'status' => [
        'setup' => 'Aufbau',
        'running' => 'Laufend',
        'closed' => 'Abgeschlossen',
    ],
    'meta' => 'Saison :season · :count Spieler · :open offene Forderungen',
    'meta_season' => 'Saison :season',
    'ladders_label' => 'Pyramide wählen',

    'tabs_label' => 'Pyramide',
    'tab_ranking' => 'Rangliste',
    'tab_challenges' => 'Forderungen',
    'tab_rules' => 'Regeln',
    'tab_admin' => 'Verwaltung',

    'notice_setup' => 'Diese Pyramide ist noch im Aufbau — gefordert werden kann noch nicht.',
    'notice_closed' => 'Diese Saison ist abgeschlossen.',
    'notice_championship' => 'Meisterschaftszeitraum: Meisterschaftsspieler bitte nur nach Absprache fordern (Regel 2.2).',

    'ranking' => 'Rangliste',
    'step' => 'Stufe :row',
    'challenge_button' => 'Fordern',
    'legend' => [
        'challengeable' => 'forderbar',
        'you' => 'du',
        'blocked' => 'derzeit nicht forderbar',
    ],
    'challengeable' => 'Fordern möglich',

    'mine' => [
        'title' => 'Meine Forderungen',
        'eyebrow' => 'Meine Forderung',
        'login' => 'Zum Mitspielen bitte anmelden.',
        'none' => 'Du hast derzeit keine offene Forderung.',
        'against' => 'gegen :name',
        'against_pos' => 'gegen :name (#:position)',
        'date_open' => 'Termin offen',
        'deadline' => 'Frist :date',
        'state_open' => 'Offen',
        'state_scheduled' => 'Termin vereinbart',
        'all' => 'Alle Forderungen',
    ],

    'waiting' => [
        'title' => 'Warteliste',
        'none' => 'Niemand wartet auf Aufnahme.',
        'join' => 'Nachmelden',
        'join_intro' => 'Du meldest dich für :title an. Die Verwaltung nimmt dich anschließend in die Rangliste auf.',
        'itn_label' => 'ITN',
        'itn_help' => 'Leer lassen, wenn keine Einstufung vorhanden ist.',
        'submit' => 'Anmelden',
    ],

    'feed' => [
        'title' => 'Ereignisse',
        'none' => 'Noch keine Ereignisse.',
        'challenge' => ':challenger hat :opponent (:position) gefordert.',
        'schedule' => 'Das Spiel zwischen :challenger und :opponent findet am :day um :time Uhr statt.',
        'result' => ':winner gewinnt gegen :loser :score.',
        'join_position' => ':player steigt auf Platz :position ein.',
        'join' => ':player hat sich angemeldet.',
        'admin' => 'Änderung durch die Verwaltung.',
        'forfeit_score' => 'nicht angetreten',
        'when' => ':date Uhr',
    ],
];
