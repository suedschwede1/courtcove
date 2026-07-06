<?php

declare(strict_types=1);

namespace Modules\Api\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->bid,
            'square_id' => $this->sid,
            'owner' => $this->owner_label,
            'status' => $this->status,
            'status_billing' => $this->status_billing,
            'quantity' => $this->quantity,
            'player_names' => $this->player_names,
            'admin_note' => $this->meta->firstWhere('key', 'admin-note')?->value,
            'reservations' => $this->reservations->map(fn ($reservation) => [
                'date' => $reservation->date,
                'time_start' => $reservation->time_start,
                'time_end' => $reservation->time_end,
            ])->values(),
        ];
    }
}
