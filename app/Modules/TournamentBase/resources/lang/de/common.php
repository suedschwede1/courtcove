<?php

declare(strict_types=1);

return [
    'area' => 'Module',
    'csrf_expired' => 'Ungültiger oder abgelaufener Formularschlüssel. Bitte Seite neu laden.',
    'match' => [
        'no_date' => 'noch kein Termin',
    ],
    'login_required' => 'Für die Module musst du angemeldet sein.',
    'nav' => [
        'settings' => 'Einstellungen',
        'pyramid' => 'Forderungspyramide',
        'championship' => 'Meisterschaft',
        'mixed' => 'Mixed-/Fun-Turnier',
        'draw' => 'Auslosung',
        'my_profile' => 'Mein Profil',
        'current_round' => 'Aktuelle Runde',
        'standings' => 'Wertung',
        'events' => 'Veranstaltungen',
        'info' => 'Info',
        'admin' => 'Verwaltung',
        'import_members' => 'Mitglieder importieren',
        'record' => 'Losprotokoll',
        'records' => 'Losprotokolle',
        'token_pick' => 'Auslosung',
        'token_pick_of' => 'Auslosung: :title',
        'draw_admin' => 'Auslosungen verwalten',
    ],
    'module_description' => [
        'pyramid' => 'Ranglisten-Wettbewerb: Spieler fordern Mitspieler über ihnen heraus.',
        'championship' => 'Gruppen, KO-Runde, Spielplan und Ergebnisse der Vereinsmeisterschaft.',
        'draw' => 'Auslosungen mit nachvollziehbarem Losprotokoll.',
        'mixed' => 'Mixed- und Fun-Turniere mit wechselnden Paarungen.',
    ],
    'privilege' => [
        'championship' => 'Darf die Meisterschaft verwalten',
        'mixed' => 'Darf das Mixed-/Fun-Turnier verwalten',
        'pyramid' => 'Darf die Forderungspyramide verwalten',
        'draw' => 'Darf Auslosungen verwalten',
    ],
    'page_of' => 'Seite :page von :last',
    'test_mode' => [
        'title' => 'Testmodus',
        'view_of' => 'Ansicht von :label',
        'read_only' => 'nur lesend – Eintragen ist gesperrt',
        'stop' => 'Testmodus beenden',
    ],
    'test_tournament' => [
        'title' => 'Testturnier',
        'text' => ':title · Mitglieder sehen das echte Turnier',
    ],
];
