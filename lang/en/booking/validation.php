<?php

declare(strict_types=1);

return [
    'validation' => [
        'event_booking_conflicts' => 'This event overlaps :count booking(s). Please move or cancel them first, then save the event again.',
        'occurrence_missing' => 'This occurrence no longer exists. Please reload the calendar.',
        'end_time_after_start' => 'The end time must be after the start time.',
        'repeat_end_date_after_start' => 'The recurrence end date must be on or after the start date.',
        'no_valid_repeat' => 'No valid recurrence could be generated.',
        'custom_repeat_not_editable' => 'For irregular series, dates and times must stay unchanged. Edit a single occurrence to move a date or time.',
        'current_password_incorrect' => 'The current password is incorrect.',
    ],
];
