<?php

declare(strict_types=1);

namespace Modules\Api\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'member_name' => $this->member?->fullName(),
            'fiscal_year_id' => $this->fiscal_year_id,
            'fee_category_id' => $this->fee_category_id,
            'amount' => $this->amount,
            'teilbetrag' => $this->teilbetrag,
            'status' => $this->displayStatus(),
            'kommentar' => $this->kommentar,
            'faellig_am' => $this->faellig_am?->format('Y-m-d'),
        ];
    }
}
