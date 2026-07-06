<?php

declare(strict_types=1);

namespace Modules\Api\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'nuliga_id' => $this->nuliga_id,
            'user_id' => $this->user_id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'gender' => $this->gender,
            'email' => $this->email,
            'phone' => $this->phone,
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'fee_category_id' => $this->fee_category_id,
            'joined_at' => $this->joined_at?->format('Y-m-d'),
            'left_at' => $this->left_at?->format('Y-m-d'),
            'status' => $this->status,
            'notiz' => $this->notiz,
        ];
    }
}
