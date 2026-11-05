<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Policies;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Services\PyramidRepository;

/**
 * Who may administer a pyramid ladder: the global right 'admin.pyramid'
 * (status admin, or assist with the flag) or a per-ladder grant row.
 *
 * Reach it only through the Gate (Gate::forUser($user)->allows('administer', ...),
 * authorize(), @can). User::can() is the privilege check and never hits this policy.
 */
final class PyramidLadderPolicy
{
    public const ROLE_NONE = 'none';

    public const ROLE_READ = 'read';

    public const ROLE_ADMIN = 'admin';

    public function __construct(private readonly PyramidRepository $repo) {}

    public function roleFor(?User $user, ?PyramidLadder $ladder = null): string
    {
        if ($user === null) {
            return self::ROLE_NONE;
        }

        if ($user->can('admin.pyramid')) {
            return self::ROLE_ADMIN;
        }

        if ($ladder !== null) {
            $grant = $this->repo->grant((int) $ladder->id, (int) $user->uid);

            if ($grant !== null && $grant->role === 'admin') {
                return self::ROLE_ADMIN;
            }
        }

        return self::ROLE_READ;
    }

    /**
     * The Gate passes the class name when a page has no ladder (global pages):
     * only the global right counts there.
     */
    public function administer(User $user, PyramidLadder|string|null $ladder = null): bool
    {
        return $this->roleFor($user, $ladder instanceof PyramidLadder ? $ladder : null) === self::ROLE_ADMIN;
    }
}
