<?php

// English reference texts. The mails themselves are always sent in German.
return [
    'prefix' => 'Challenge ladder: ',

    'challenge_created' => [
        'subject' => 'You have been challenged',
        'body' => ":challenger has challenged you in the challenge ladder.\n\nPlease arrange a date as soon as possible. Deadline: :deadline.\n",
    ],
    'scheduled' => [
        'subject' => 'Date for your challenge match',
        'body' => "The challenge match :challenger vs :opponent takes place on :date.\n",
    ],
    'result_entered' => [
        'subject' => 'Result entered',
        'body' => "The result :score was entered for your challenge match :challenger vs :opponent, winner: :winner.\n\nIf this is wrong, please tell the ladder manager.\n",
    ],
    'declined' => [
        'subject' => 'Challenge declined',
        'body' => ":opponent :reason and declined your challenge (rule 5.2). You are free to challenge again.\n",
        'reason' => [
            'other_match' => 'has another challenge match',
            'injury' => 'is injured or ill',
            'other' => 'has declined',
        ],
    ],
    'withdrawn' => [
        'subject' => 'Challenge withdrawn',
        'body' => ":challenger has withdrawn the challenge against you. You are free again.\n",
    ],
    'deadline_extended' => [
        'subject' => 'Deadline extended',
        'body' => "The deadline for your challenge match was extended to :deadline.\nReason: :note\n",
    ],
    'forfeited' => [
        'subject' => 'Awarded by forfeit',
        'body' => "Your challenge match was awarded by forfeit, winner: :winner.\nReason: :note\n",
    ],
    'expired' => [
        'subject' => 'Challenge expired',
        'body' => "Your challenge match was closed without a result.\nReason: :note\n",
    ],
    'result_corrected' => [
        'subject' => 'Result corrected',
        'body' => "The result of your challenge match was corrected.\nReason: :note\n",
    ],
    'admitted' => [
        'subject' => 'Admitted to the ladder',
        'body' => "You have been admitted to the ladder, at position :position.\n",
    ],
    'released' => [
        'subject' => 'Released to enter',
        'body' => "You may now challenge an active player of the ladder (rule 6.1). Choose an opponent you have realistic chances against.\n",
    ],
];
