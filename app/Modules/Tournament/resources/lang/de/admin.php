<?php

return [
    'generate' => [
        'skipped' => 'Gruppe :group: :reason - Partien folgen nach der Auslosung',
    ],
    'apply' => [
        'invalid' => 'Auslosung fehlt, ist noch nicht abgeschlossen, wurde nicht gezogen, ist verworfen oder gehört zu einem anderen Turnier.',
        'already' => 'Diese Auslosung wurde bereits angewendet.',
        'result_not_json' => 'Das Ziehungsergebnis ist kein gültiges JSON.',
        'slot_not_line' => 'Zielslot ":slot" ist keine Rasterlinie.',
        'no_pool_line' => 'Rasterlinie :slot existiert nicht als Poollinie.',
        'token_incomplete' => 'Token :token ist unvollständig oder hat einen ungültigen Slot.',
        'foreign_player' => 'Token :token verweist auf einen fremden Teilnehmer.',
        'unknown_mode' => 'Unbekannter Ziehungsmodus.',
        'parallel' => 'Die Auslosung wurde parallel bereits angewendet.',
        'success' => ':count Zuordnungen atomar angewendet.',
    ],
];
