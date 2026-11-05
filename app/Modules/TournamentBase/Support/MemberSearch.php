<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use App\Models\User;

/**
 * Member lookup for the player pickers. Exposes uid, alias and email only (never phone numbers).
 */
class MemberSearch
{
    /**
     * @return list<array{uid:int, alias:string, email:?string}>
     */
    public function search(string $q, int $limit = 20): array
    {
        $q = trim($q);

        if (mb_strlen($q) < 2) {
            return [];
        }

        $like = '%'.addcslashes($q, '\\%_').'%';

        $users = User::query()
            ->whereNotIn('status', ['deleted', 'disabled'])
            ->where(function ($query) use ($like, $q): void {
                $query->where('alias', 'like', $like)->orWhere('email', 'like', $like);

                if (ctype_digit($q)) {
                    $query->orWhere('uid', (int) $q);
                }
            })
            ->orderBy('alias')
            ->orderBy('uid')
            ->limit($limit)
            ->get(['uid', 'alias', 'email']);

        return $users->map(fn (User $u): array => [
            'uid' => (int) $u->uid,
            'alias' => (string) $u->alias,
            'email' => $u->email !== null ? (string) $u->email : null,
        ])->all();
    }
}
