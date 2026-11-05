<?php

return [
    'round_not_future' => 'drand round :round is not in the future (current round: :current).',

    'commit' => [
        'title_missing' => 'Title is missing.',
        'rule_missing' => 'The assignment rule is required - it is published with the draw.',
        'round_invalid' => 'Beacon round is missing or invalid.',
        'min_entries' => 'At least two lots are required.',
        'count_mismatch' => 'Lots (:entries) and slots (:slots) must have the same count.',
        'duplicate_entries' => 'The lots contain duplicates.',
        'duplicate_slots' => 'The slots contain duplicates.',
        'invalid_entry' => 'Lot ":entry" is invalid - allowed are whole numbers or ids such as A2 and Q3.',
        'entry_not_canonical' => 'Lot ":entry" is invalid - enter numbers without a leading zero and not above 9223372036854775807.',
        'title_too_long' => 'The title may be at most :max characters long.',
        'tid_invalid' => 'The tournament id is invalid.',
        'line_separator' => 'Line and paragraph separators (U+2028, U+2029) are not allowed in lots, slots and rule - the verification script could not recompute the draw.',
        'numeric_slot' => 'Slots must not consist of digits only (e.g. A2 instead of 2) - the verification script could not recompute the draw.',
        'invalid_utf8' => 'The input contains invalid characters (not valid UTF-8).',
        'beacon_failed' => 'The beacon round could not be checked: :message',
        'success' => 'Draw ":title" committed. Announce the record link now, BEFORE beacon round :round is published.',
    ],
];
