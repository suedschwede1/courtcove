<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use App\Models\User;

/**
 * Member lookup for the player pickers. Exposes uid, alias and email only (never phone numbers).
 *
 * Accounts with status deleted, disabled, placeholder or blocked are never offered (placeholders are not
 * real members, blocked ones are barred from the club).
 *
 * Because emails are returned, callers MUST sit behind login/admin authorisation; never expose
 * this to guests.
 */
class MemberSearch
{
    private const EXCLUDED_STATUSES = ['deleted', 'disabled', 'placeholder', 'blocked'];

    /**
     * @return list<array{uid:int, alias:string, email:?string}>
     */
    public function search(string $q, int $limit = 20): array
    {
        $q = trim($q);

        if (mb_strlen($q) < 2) {
            return [];
        }

        // '!' is the explicit ESCAPE char so wildcards stay literal on MySQL and SQLite alike.
        $like = '%'.preg_replace('/[!%_]/', '!$0', $q).'%';

        $users = User::query()
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->where(function ($query) use ($like, $q): void {
                $query->whereRaw("alias like ? escape '!'", [$like])->orWhereRaw("email like ? escape '!'", [$like]);

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
