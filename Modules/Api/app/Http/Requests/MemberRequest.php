<?php

declare(strict_types=1);

namespace Modules\Api\Http\Requests;

use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $memberId = $this->route('member')?->id;

        return [
            'member_number' => ['required', 'integer', 'min:1', Rule::unique('members', 'member_number')->ignore($memberId)],
            'nuliga_id' => ['nullable', 'integer', 'min:1', Rule::unique('members', 'nuliga_id')->ignore($memberId)],
            'user_id' => ['nullable', 'integer', 'exists:bs_users,uid', Rule::unique('members', 'user_id')->ignore($memberId)],
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
            'birthdate' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(Member::GENDERS)],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:128'],
            'fee_category_id' => ['nullable', 'exists:fee_categories,id'],
            'joined_at' => ['required', 'date'],
            'left_at' => ['nullable', 'date'],
            'notiz' => ['nullable', 'string', 'max:5000'],
            'club_roles' => ['array'],
            'club_roles.*' => ['integer', 'exists:club_roles,id'],
        ];
    }
}
