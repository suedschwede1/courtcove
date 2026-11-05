<?php

declare(strict_types=1);

namespace Modules\Api\Http\Requests;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Legacy repeat types of the booking being updated (derived from its stored
     * reservations, never from the request). Empty on create.
     *
     * @return list<string>
     */
    private function allowedLegacyRepeat(): array
    {
        $booking = $this->route('booking');
        if (! $booking instanceof Booking) {
            return [];
        }

        $occurrence = $this->input('occurrence');

        return app(BookingService::class)->legacyRepeatTypesOf($booking, is_string($occurrence) && $occurrence !== '' ? $occurrence : null);
    }

    public function rules(): array
    {
        return [
            'booked_for' => ['required', 'string', 'max:120'],
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['nullable', 'date_format:Y-m-d'],
            'repeat_type' => ['required', 'in:'.implode(',', array_merge(BookingService::REPEAT_KEYS, $this->allowedLegacyRepeat()))],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'quantity' => ['required', 'integer', 'in:2,4'],
            'status' => ['nullable', 'in:single,subscription,cancelled'],
            'mitspieler' => ['required', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'occurrence' => ['nullable', 'date_format:Y-m-d'],
            'edit_scope' => ['nullable', 'in:single_occurrence,this_and_following'],
        ];
    }
}
