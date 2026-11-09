<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EventBookingConflictException extends ValidationException
{
    /** @param list<array{bid: int, date: string, time_start: string, time_end: string, sid: int}> $conflicts */
    public function __construct(public readonly array $conflicts)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('time_start', __('booking.validation.event_booking_conflicts', ['count' => count($conflicts)]));
        parent::__construct($validator);
    }
}
