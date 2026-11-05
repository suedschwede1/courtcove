<?php

declare(strict_types=1);

return [
    'validation' => [
        'end_time_after_start' => 'The end time must be after the start time.',
        'repeat_end_date_after_start' => 'The recurrence end date must be on or after the start date.',
        'no_valid_repeat' => 'No valid recurrence could be generated.',
        'custom_repeat_not_editable' => 'Series with irregular dates cannot be saved unchanged. Please set the repeat to once, weekly or every 2 weeks.',
        'current_password_incorrect' => 'The current password is incorrect.',
    ],
];
