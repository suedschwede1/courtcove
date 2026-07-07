<?php

declare(strict_types=1);

namespace Modules\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sid' => ['nullable', 'integer', 'exists:bs_squares,sid'],
            'name' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:2048'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'date_start' => ['required', 'date_format:Y-m-d'],
            'time_start' => ['required', 'date_format:H:i'],
            'date_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_start'],
            'time_end' => ['required', 'date_format:H:i'],
        ];
    }
}
