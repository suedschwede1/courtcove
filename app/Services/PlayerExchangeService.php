<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;

final class PlayerExchangeService
{
    public const META_KEY = 'player-exchange';

    public function optIn(User $user): void
    {
        $user->setMeta(self::META_KEY, '1');
    }

    public function optOut(User $user): void
    {
        $user->setMeta(self::META_KEY, null);
    }

    public function isOptedIn(User $user): bool
    {
        return $user->meta()->where('key', self::META_KEY)->value('value') === '1';
    }

    /** Signed one-click unsubscribe link (no login, no expiry) for the e-mails. */
    public function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('player-exchange.unsubscribe', ['user' => $user->uid]);
    }

    /**
     * Active (not blocked) opted-in users with an e-mail address.
     *
     * @return Collection<int, User>
     */
    public function recipients(?User $exclude = null): Collection
    {
        return User::query()
            ->active()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('meta', fn ($q) => $q->where('key', self::META_KEY)->where('value', '1'))
            ->when($exclude, fn ($q) => $q->where('uid', '!=', $exclude->uid))
            ->orderBy('uid')
            ->get();
    }
}
