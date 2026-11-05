<?php

return [
    'generate' => [
        'skipped' => 'Group :group: :reason - matches follow after the draw',
    ],
    'apply' => [
        'invalid' => 'The draw is missing, not finished, not executed, discarded or belongs to another tournament.',
        'already' => 'This draw has already been applied.',
        'result_not_json' => 'The draw result is not valid JSON.',
        'slot_not_line' => 'Target slot ":slot" is not a bracket line.',
        'no_pool_line' => 'Bracket line :slot does not exist as a pool line.',
        'token_incomplete' => 'Token :token is incomplete or has an invalid slot.',
        'foreign_player' => 'Token :token refers to a participant of another tournament.',
        'unknown_mode' => 'Unknown draw mode.',
        'parallel' => 'The draw has already been applied in parallel.',
        'success' => ':count assignments applied atomically.',
    ],
];
