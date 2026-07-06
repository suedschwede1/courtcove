<?php

declare(strict_types=1);

namespace Modules\Api\Http\Requests;

use App\Services\BookingService;
use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booked_for' => ['required', 'string', 'max:120'],
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['nullable', 'date_format:Y-m-d'],
            'repeat_type' => ['required', 'in:'.implode(',', BookingService::REPEAT_KEYS)],
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
