<?php

return [
    'module' => 'Challenge ladder',

    'no_ladder' => 'No ladder has been set up yet.',
    'login_to_play' => 'You need to be logged in to challenge.',
    'unknown' => 'Unknown',
    'you' => 'you',
    'paused' => 'paused',

    'status' => [
        'setup' => 'Setup',
        'running' => 'Running',
        'closed' => 'Closed',
    ],
    'meta' => 'Season :season · :count players · :open open challenges',
    'meta_season' => 'Season :season',
    'ladders_label' => 'Choose ladder',

    'tabs_label' => 'Ladder',
    'tab_ranking' => 'Ranking',
    'tab_challenges' => 'Challenges',
    'tab_rules' => 'Rules',
    'tab_admin' => 'Administration',

    'notice_setup' => 'This ladder is still being set up — challenges are not possible yet.',
    'notice_closed' => 'This season is over.',
    'notice_championship' => 'Championship period: please challenge championship players only after agreeing with them (rule 2.2).',

    'ranking' => 'Ranking',
    'step' => 'Level :row',
    'challenge_button' => 'Challenge',
    'legend' => [
        'challengeable' => 'can be challenged',
        'you' => 'you',
        'blocked' => 'not challengeable right now',
    ],
    'challengeable' => 'Challenge possible',

    'mine' => [
        'title' => 'My challenges',
        'eyebrow' => 'My challenge',
        'login' => 'Please log in to take part.',
        'none' => 'You have no open challenge right now.',
        'against' => 'against :name',
        'against_pos' => 'against :name (#:position)',
        'date_open' => 'Date open',
        'deadline' => 'Deadline :date',
        'state_open' => 'Open',
        'state_scheduled' => 'Date agreed',
        'all' => 'All challenges',
    ],

    'waiting' => [
        'title' => 'Waiting list',
        'none' => 'Nobody is waiting for admission.',
        'join' => 'Sign up late',
        'join_intro' => 'You are signing up for :title. The administration will then admit you to the ranking.',
        'itn_label' => 'ITN',
        'itn_help' => 'Leave empty if you have no rating.',
        'submit' => 'Sign up',
    ],

    'feed' => [
        'title' => 'Events',
        'none' => 'No events yet.',
        'challenge' => ':challenger challenged :opponent (:position).',
        'schedule' => 'The match between :challenger and :opponent takes place on :day at :time.',
        'result' => ':winner beats :loser :score.',
        'join_position' => ':player enters at position :position.',
        'join' => ':player has signed up.',
        'admin' => 'Change by the administration.',
        'forfeit_score' => 'did not show up',
        'when' => ':date',
    ],
];
