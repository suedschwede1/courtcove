<?php

return [
    'title' => 'Draw record',
    'lead' => 'Public and verifiable – anyone can check the result themselves.',
    'not_found' => 'Record not found',
    'discarded' => 'This draw was discarded.',
    'no_reason' => 'No reason given.',
    'status' => 'Status',
    'status_executed' => 'executed on :date',
    'status_pending' => 'committed, not drawn yet',
    'committed_at' => 'Committed',
    'commitment' => 'Commitment',
    'beacon_source' => 'Randomness source',
    'entries' => 'Entries',
    'slots' => 'Target slots',
    'rule' => 'Rule',
    'pending_heading' => 'Not drawn yet',
    'pending_text' => 'The details above are fixed by the commitment and can no longer be changed. As soon as the given drand round is published, the draw is executed and the server seed is revealed here.',
    'result' => 'Result',
    'slot' => 'Slot',
    'entry' => 'Entry',
    'verify_heading' => 'Verify',
    'server_seed' => 'Server seed',
    'beacon_value' => 'Beacon value',
    'final_seed' => 'Final seed',
    'json_hint' => 'All details in machine-readable form: :link. With it and the script <code>tools/verify_draw.py</code> the draw can be recomputed without this server.',
    'json_link' => 'JSON',
    'method' => 'Method',
    'method_text' => <<<'TXT'
commitment = SHA256( canonical(payload) + "|" + serverSeed )
finalSeed  = HMAC-SHA256( key = serverSeed, message = beaconValue )

Fisher-Yates, descending from i = n-1 to 1:
    j = ( first 8 bytes of SHA256( finalSeed + "|" + i ) as a 63-bit number ) mod (i+1)
    swap elements i and j

The shuffled list is assigned to the slot list from top to bottom.

canonical() is JSON without spaces, without Unicode escapes and without
escaped slashes - in Python:
    json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
TXT,
    'beacon_note' => 'The beacon value comes from the drand network and did not exist yet when the commitment was made. So the operator could neither compute the result in advance nor reorder the entries afterwards.',
];
