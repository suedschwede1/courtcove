<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

/**
 * Roles of the Mixed tournament area, ascending: none < read < score < admin.
 */
enum MixedRole: string
{
    case None = 'none';
    case Read = 'read';
    case Score = 'score';
    case Admin = 'admin';

    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Read => 1,
            self::Score => 2,
            self::Admin => 3,
        };
    }
}
