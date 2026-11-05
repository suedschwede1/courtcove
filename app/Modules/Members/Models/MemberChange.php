<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $member_id
 * @property int|null $admin_user_id
 * @property string $action
 * @property array<string, mixed>|null $before
 * @property array<string, mixed> $after
 */
class MemberChange extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['member_id', 'admin_user_id', 'action', 'before', 'after'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id', 'uid');
    }

    /**
     * Diffs `after` against `before`, skipping unchanged and internal keys.
     *
     * Expects `before`/`after` to be flat scalar-valued attribute snapshots
     * (e.g. `$member->toArray()` with no relations loaded) — nested arrays
     * are compared as whole values, not diffed field-by-field.
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
