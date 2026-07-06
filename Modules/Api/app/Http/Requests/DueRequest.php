<?php

declare(strict_types=1);

namespace Modules\Api\Http\Requests;

use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'member_id' => [
                    'required',
                    'exists:members,id',
                    Rule::unique('member_dues', 'member_id')->where('fiscal_year_id', (int) $this->input('fiscal_year_id')),
                ],
                'fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
                'fee_category_id' => ['required', 'exists:fee_categories,id'],
                'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
                'teilbetrag' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
                'kommentar' => ['nullable', 'string', 'max:1000'],
            ];
        }

        return [
            'fee_category_id' => ['sometimes', 'exists:fee_categories,id'],
            'amount' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'teilbetrag' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'kommentar' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(MemberDue::STATUSES)],
        ];
    }
}
