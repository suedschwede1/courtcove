<?php

declare(strict_types=1);

return [
    'area' => 'Modules',
    'csrf_expired' => 'Invalid or expired form key. Please reload the page.',
    'match' => [
        'no_date' => 'no date yet',
    ],
    'login_required' => 'Please log in to see the modules.',
    'nav' => [
        'settings' => 'Settings',
        'pyramid' => 'Challenge pyramid',
        'championship' => 'Championship',
        'mixed' => 'Mixed/fun tournament',
        'draw' => 'Draws',
        'my_profile' => 'My profile',
        'current_round' => 'Current round',
        'standings' => 'Standings',
        'events' => 'Events',
        'info' => 'Info',
        'admin' => 'Administration',
        'import_members' => 'Import members',
        'record' => 'Draw record',
        'records' => 'Draw records',
        'token_pick' => 'Draw',
        'token_pick_of' => 'Draw: :title',
        'draw_admin' => 'Manage draws',
    ],
    'module_description' => [
        'pyramid' => 'Ranking competition: players challenge opponents ranked above them.',
        'championship' => 'Groups, knockout round, schedule and results of the club championship.',
        'draw' => 'Draws with a verifiable draw record.',
        'mixed' => 'Mixed and fun tournaments with changing pairings.',
    ],
    'privilege' => [
        'championship' => 'Can manage the championship',
        'mixed' => 'Can manage the mixed/fun tournament',
        'pyramid' => 'Can manage the challenge pyramid',
        'draw' => 'Can manage draws',
    ],
    'page_of' => 'Page :page of :last',
    'test_mode' => [
        'title' => 'Test mode',
        'view_of' => 'Viewing as :label',
        'read_only' => 'read-only – entering results is locked',
        'stop' => 'Stop test mode',
    ],
    'test_tournament' => [
        'title' => 'Test tournament',
        'text' => ':title · members see the real tournament',
    ],
];
