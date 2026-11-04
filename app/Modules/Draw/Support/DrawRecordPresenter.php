<?php

declare(strict_types=1);

namespace App\Modules\Draw\Support;

use App\Modules\Draw\Models\Draw;

/**
 * Public view of a draw. Values are the raw stored strings (datetimes are not
 * reformatted); payload and assignment are decoded from their stored JSON.
 */
final class DrawRecordPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function json(Draw $draw): array
    {
        $record = [
            'title' => $draw->title,
            'status' => $draw->status,
            'payload' => json_decode((string) $draw->payload, true),
            'commitment' => $draw->commitment,
            'beacon_source' => $draw->beacon_source,
            'created' => $draw->created,
            'discarded' => $draw->discarded,
            'discarded_reason' => $draw->discarded_reason,
        ];

        if ($draw->isRevealed()) {
            $record['server_seed'] = $draw->server_seed;
            $record['beacon_value'] = $draw->beacon_value;
            $record['final_seed'] = $draw->final_seed;
            $record['assignment'] = $draw->result === null ? null : json_decode($draw->result, true);
            $record['executed'] = $draw->executed;
        }

        return $record;
    }
}
