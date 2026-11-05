<?php

return [
    'info_lead' => 'Jeder im Lostopf wählt einen Token.',
    'info_hidden' => 'Die Zuordnung ist während der Wahlphase nicht öffentlich',
    'info_rest' => '; nach dem Schließen wird der Server-Seed veröffentlicht und die Ziehung vollständig nachrechenbar. Die technische Administration des Servers könnte die bereits berechnete Zuordnung vorher einsehen; organisatorisch nimmt die Turnierleitung deshalb den letzten freien Token.',
    'info_record' => 'Nachrechnen kann den Ablauf anschließend jeder im',
    'record_link' => 'Protokoll',

    'test_title' => 'Testturnier:',
    'test_as' => 'Du wählst als :label.',
    'test_note' => 'Im echten Turnier ist das gesperrt – dort würde im Protokoll ein Name stehen, der nicht gewählt hat.',

    'own_label' => 'Dein Token',
    'own_claimed' => 'gewählt am :date · eine Änderung ist nicht möglich',
    'own_group' => 'Du bist in Gruppe :slot',

    'not_player' => 'Du bist nicht als Teilnehmer gemeldet und wählst deshalb nicht mit.',
    'not_eligible' => 'Du bist bei dieser Auslosung nicht im Lostopf. Gesetzte Gruppenköpfe stehen bereits fest, und der andere Bewerb wird getrennt gelost.',
    'none_yet' => 'Du hast noch keinen Token gewählt.',
    'until' => 'Zeit bis :deadline.',
    'fallback_note' => 'Wenn du nichts tust, wird dir nach Ablauf einer der freien Tokens zugeteilt – das ist genauso zufällig.',

    'heading' => 'Tokens',
    'selectable' => ':count für dich wählbar',
    'free_of' => ':free von :total frei',
    'taken' => 'belegt',
    'assigned' => 'zugeteilt',
    'group' => 'Gruppe :slot',
    'free' => 'frei',
    'pick_button' => 'wählen',
    'order_note' => 'Die Tokens stehen in fester Reihenfolge und werden nicht pro Person gemischt – sonst wäre hinterher unklar, was „der dritte Token" war.',

    'confirm_title' => 'Token :token wählen?',
    'confirm_text' => 'Das ist endgültig und kann nicht geändert werden.',
    'confirm_submit' => 'Ja, endgültig wählen',
    'cancel' => 'Abbrechen',

    'pick' => [
        'discarded' => 'Diese Auslosung wurde verworfen.',
        'test_mode' => 'Im Testmodus ist die Tokenwahl gesperrt. Beende ihn zuerst.',
        'not_open' => 'Die Wahlphase dieser Auslosung ist nicht offen.',
        'not_player' => 'Du bist nicht als Teilnehmer dieses Turniers gemeldet.',
        'not_eligible' => 'Du bist bei dieser Auslosung nicht im Lostopf. Gesetzte Gruppenköpfe und Spieler des anderen Bewerbs wählen nicht mit.',
        'already' => 'Du hast bereits Token :token gewählt. Eine Änderung ist nicht möglich.',
        'unknown' => 'Diesen Token gibt es nicht.',
        'closed_meanwhile' => 'Diese Auslosung ist nicht mehr in der Wahlphase.',
        'blocked' => 'Token :token ist nicht wählbar. Bitte einen anderen nehmen.',
        'taken' => 'Token :token war schneller weg. Bitte einen anderen wählen.',
        'group_failed' => 'Token ist belegt, die Gruppenzuteilung hat aber nicht geklappt: :message',
        'success_group' => 'Token :token ist deiner – du bist in Gruppe :group. Deine Gegner stehen unter "Meine Spiele".',
        'success' => 'Token :token ist deiner. Die Gruppe erscheint, sobald die Ziehung ausgeführt ist.',
    ],
];
