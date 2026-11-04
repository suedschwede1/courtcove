<?php

declare(strict_types=1);

return [
    'sets_incomplete' => 'Set 1 and set 2 must be filled in completely.',
    'mtb_after_two_sets' => 'The match was decided after two sets - the match tie-break must stay empty.',
    'mtb_required' => 'Sets level at 1:1 - the match tie-break must be entered.',
    'mtb_both_scores' => 'The match tie-break needs both scores.',
    'set_range' => 'Set :number: games must be between 0 and 7.',
    'set_invalid' => 'Set :number: :score is not a valid set score. Valid are 6:0 to 6:4, 7:5 and 7:6.',
    'mtb_negative' => 'Match tie-break: negative points are not possible.',
    'mtb_min_ten' => 'Match tie-break :score - the winner needs at least 10 points.',
    'mtb_ten_nine' => 'Match tie-break :score - at 10:9 play must continue.',
    'mtb_two_apart' => 'Match tie-break :score - beyond 10 points exactly a two-point lead is required.',
    'mtb_draw' => 'Match tie-break: a draw is not possible.',
    'not_integer' => '":field": whole numbers only.',
    'describe' => [
        'schedule_only' => 'Date will be saved - the result can be added later.',
        'enter_sets' => 'Enter set results - e.g. 6 : 4 and 6 : 3.',
        'set1_invalid' => 'Set 1 is not a valid set score (6:0-6:4, 7:5 or 7:6).',
        'set2_invalid' => 'Set 2 is not a valid set score (6:0-6:4, 7:5 or 7:6).',
        'mtb_must_be_empty' => 'The match is decided after two sets - please leave the match tie-break empty.',
        'mtb_needed' => 'Sets level at 1:1 - enter the match tie-break (to 10, at least a 2-point lead).',
        'mtb_invalid' => 'Match tie-break invalid: played to 10, at least a 2-point lead.',
    ],
];
