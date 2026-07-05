<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberChange extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['member_id', 'admin_user_id', 'action', 'before', 'after'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id', 'uid');
    }

    /**
     * Diffs `after` against `before`, skipping unchanged and internal keys.
     *
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    public function changedFields(): array
    {
        if ($this->before === null) {
            return [];
        }

        $ignored = ['id', 'created_at', 'updated_at'];
        $changes = [];

        foreach ($this->after as $field => $newValue) {
            if (in_array($field, $ignored, true)) {
                continue;
            }

            $oldValue = $this->before[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[] = ['field' => $field, 'old' => $oldValue, 'new' => $newValue];
            }
        }

        return $changes;
    }
}
