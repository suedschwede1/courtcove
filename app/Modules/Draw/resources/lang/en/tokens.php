<?php

return [
    'info_lead' => 'Everybody in the pot picks one token.',
    'info_hidden' => 'The assignment is not public during the picking phase',
    'info_rest' => '; on close the server seed is published and the draw can be fully recomputed. The technical server administration could look at the already computed assignment beforehand; organisationally the tournament director therefore takes the last free token.',
    'info_record' => 'Afterwards anybody can recompute the procedure in the',
    'record_link' => 'record',

    'test_title' => 'Test tournament:',
    'test_as' => 'You pick as :label.',
    'test_note' => 'This is locked in a real tournament – the record would show a name that did not pick.',

    'own_label' => 'Your token',
    'own_claimed' => 'picked on :date · it cannot be changed',
    'own_group' => 'You are in group :slot',

    'not_player' => 'You are not registered as a participant and therefore do not pick.',
    'not_eligible' => 'You are not in the pot of this draw. Seeded group heads are already fixed, and the other competition is drawn separately.',
    'none_yet' => 'You have not picked a token yet.',
    'until' => 'Time until :deadline.',
    'fallback_note' => 'If you do nothing, one of the free tokens is assigned to you after the deadline – that is just as random.',

    'heading' => 'Tokens',
    'selectable' => ':count selectable for you',
    'free_of' => ':free of :total free',
    'taken' => 'taken',
    'assigned' => 'assigned',
    'group' => 'Group :slot',
    'free' => 'free',
    'pick_button' => 'pick',
    'order_note' => 'The tokens are in a fixed order and are not shuffled per person – otherwise it would be unclear afterwards what "the third token" was.',

    'confirm_title' => 'Pick token :token?',
    'confirm_text' => 'This is final and cannot be changed.',
    'confirm_submit' => 'Yes, pick for good',
    'cancel' => 'Cancel',

    'pick' => [
        'discarded' => 'This draw was discarded.',
        'test_mode' => 'Picking a token is locked in the test mode. End it first.',
        'not_open' => 'The picking phase of this draw is not open.',
        'not_player' => 'You are not registered as a participant of this tournament.',
        'not_eligible' => 'You are not in the pot of this draw. Seeded group heads and players of the other competition do not pick.',
        'already' => 'You already picked token :token. It cannot be changed.',
        'unknown' => 'This token does not exist.',
        'closed_meanwhile' => 'This draw is no longer in the picking phase.',
        'blocked' => 'Token :token cannot be picked. Please take another one.',
        'taken' => 'Token :token was taken first. Please pick another one.',
        'group_failed' => 'The token is yours, but the group allocation failed: :message',
        'success_group' => 'Token :token is yours – you are in group :group. Your opponents are listed under "My matches".',
        'success' => 'Token :token is yours. The group appears once the draw is executed.',
    ],
];
