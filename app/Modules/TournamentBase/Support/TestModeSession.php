<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Session\Session;
use InvalidArgumentException;

/**
 * Admin-only "view as member / player" test mode and the admin's selected tournament,
 * kept in the session under `tournament.test_mode`. This class only stores the state;
 * lowering the access level is the job of TournamentAccess.
 */
final class TestModeSession
{
    public const KEY = 'tournament.test_mode';

    public function __construct(private readonly Session $session) {}

    public function simulate(User $user, string $mode, ?int $tpid = null): void
    {
        $this->authorize($user);

        if (! in_array($mode, ['member', 'player'], true) || ($mode === 'player' && ($tpid === null || $tpid <= 0))) {
            throw new InvalidArgumentException('invalid simulation request');
        }

        $state = $this->state();
        $state['mode'] = $mode;
        $state['tpid'] = $mode === 'player' ? $tpid : null;
        $this->session->put(self::KEY, $state);
    }

    public function stop(): void
    {
        $state = $this->state();
        $state['mode'] = null;
        $state['tpid'] = null;
        $this->session->put(self::KEY, $state);
    }

    /** @return array{mode:string,tpid:?int}|null */
    public function simulation(User $user): ?array
    {
        if (! $this->isAdmin($user)) {
            return null;
        }

        $state = $this->state();

        return $state['mode'] === null ? null : ['mode' => $state['mode'], 'tpid' => $state['tpid']];
    }

    public function selectTid(User $user, ?int $tid): void
    {
        $this->authorize($user);

        $state = $this->state();
        if ($state['tid'] !== $tid) {
            $state['mode'] = null;
            $state['tpid'] = null;
        }
        $state['tid'] = $tid;
        $this->session->put(self::KEY, $state);
    }

    public function selectedTid(User $user): ?int
    {
        return $this->isAdmin($user) ? $this->state()['tid'] : null;
    }

    private function isAdmin(User $user): bool
    {
        return $user->can('admin.tournament');
    }

    private function authorize(User $user): void
    {
        if (! $this->isAdmin($user)) {
            throw new AuthorizationException;
        }
    }

    /** @return array{mode:?string,tpid:?int,tid:?int} */
    private function state(): array
    {
        $raw = $this->session->get(self::KEY);
        $raw = is_array($raw) ? $raw : [];

        return [
            'mode' => isset($raw['mode']) ? (string) $raw['mode'] : null,
            'tpid' => isset($raw['tpid']) ? (int) $raw['tpid'] : null,
            'tid' => isset($raw['tid']) ? (int) $raw['tid'] : null,
        ];
    }
}
