<?php

declare(strict_types=1);

return [
    'validation' => [
        'end_time_after_start' => 'Die Endzeit muss nach der Startzeit liegen.',
        'repeat_end_date_after_start' => 'Das Enddatum der Wiederholung muss am oder nach dem Startdatum liegen.',
        'no_valid_repeat' => 'Es konnte keine gültige Wiederholung erzeugt werden.',
        'custom_repeat_not_editable' => 'Serien mit unregelmäßigen Terminen können nicht unverändert gespeichert werden. Bitte Wiederholung auf einmalig, wöchentlich oder alle 2 Wochen setzen.',
        'current_password_incorrect' => 'Das aktuelle Passwort ist nicht korrekt.',
    ],
];
