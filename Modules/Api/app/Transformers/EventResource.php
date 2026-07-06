<?php

declare(strict_types=1);

namespace Modules\Api\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metaMap = $this->meta->pluck('value', 'key');

        return [
            'id' => $this->eid,
            'square_id' => $this->sid,
            'status' => $this->status,
            'capacity' => $this->capacity,
            'datetime_start' => $this->datetime_start?->format('Y-m-d H:i:s'),
            'datetime_end' => $this->datetime_end?->format('Y-m-d H:i:s'),
            'name' => $metaMap['name'] ?? null,
            'description' => $metaMap['description'] ?? null,
            'notes' => $metaMap['notes'] ?? null,
        ];
    }
}
