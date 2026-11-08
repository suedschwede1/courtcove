<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Change log of admin configuration pages: one line on the default log channel (who, when, area, field
 * names), never a value. There is no separate channel or table; with LOG_STACK=single the file is not rotated.
 */
final class AdminConfigAudit
{
    /** @param list<string> $changedFieldNames names or short texts such as "Auth-Token geändert", never values */
    public static function record(User $admin, string $area, array $changedFieldNames): void
    {
        if ($changedFieldNames === []) {
            return;
        }

        Log::info('admin.'.$area.'.config_changed', [
            'wer' => $admin->id,
            'wann' => now()->toIso8601String(),
            'area' => $area,
            'fields' => $changedFieldNames,
        ]);
    }
}
