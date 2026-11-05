<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

use Illuminate\Support\Facades\Route;

/**
 * URLs the Pyramid views link to.
 *
 * The module's routes are registered task by task; a view must not fail while the route it
 * links to is not registered yet. Each link therefore uses the named route when it exists and
 * otherwise the same path through url(), which honours the /booking base path just like route().
 */
final class PyramidLinks
{
    public static function ladder(int $ladderId): string
    {
        return self::to('pyramide.ladder', ['ladderId' => $ladderId], 'pyramide/'.$ladderId);
    }

    public static function rules(): string
    {
        return self::to('pyramide.rules', [], 'pyramide/regeln');
    }

    public static function challenges(): string
    {
        return self::to('pyramide.challenges', [], 'pyramide/forderungen');
    }

    public static function player(int $playerId): string
    {
        return self::to('pyramide.player', ['playerId' => $playerId], 'pyramide/spieler/'.$playerId);
    }

    public static function challengeConfirm(int $playerId): string
    {
        return self::to('pyramide.challenge.confirm', ['playerId' => $playerId], 'pyramide/fordern/'.$playerId);
    }

    public static function challenge(int $challengeId): string
    {
        return self::to('pyramide.challenge.show', ['challengeId' => $challengeId], 'pyramide/forderung/'.$challengeId);
    }

    public static function join(): string
    {
        return self::to('pyramide.join', [], 'pyramide/anmelden');
    }

    public static function adminIndex(): string
    {
        return self::to('pyramide.admin.index', [], 'pyramide/admin');
    }

    public static function adminCreate(): string
    {
        return self::to('pyramide.admin.create', [], 'pyramide/admin/create');
    }

    public static function adminLadder(int $ladderId): string
    {
        return self::to('pyramide.admin.ladder', ['ladderId' => $ladderId], 'pyramide/admin/ladder/'.$ladderId);
    }

    public static function adminMembers(int $ladderId): string
    {
        return self::to('pyramide.admin.members', ['ladderId' => $ladderId], 'pyramide/admin/ladder/'.$ladderId.'/mitglieder');
    }

    /**
     * Target of a ladder-level admin POST (seed, add-player, config, copy-players, normalize, close, grant, revoke).
     * The routes arrive with the player actions; until then the same path is built through url().
     */
    public static function adminLadderAction(string $action, int $ladderId): string
    {
        return self::to('pyramide.admin.'.$action, ['ladderId' => $ladderId], 'pyramide/admin/ladder/'.$ladderId.'/'.$action);
    }

    /**
     * Target of a player-level admin POST: route name suffix => path segment.
     */
    public static function adminPlayerAction(string $routeSuffix, string $segment, int $playerId): string
    {
        return self::to('pyramide.admin.'.$routeSuffix, ['playerId' => $playerId], 'pyramide/admin/player/'.$playerId.'/'.$segment);
    }

    public static function adminChallengeAction(string $routeSuffix, string $segment, int $challengeId): string
    {
        return self::to('pyramide.admin.'.$routeSuffix, ['challengeId' => $challengeId], 'pyramide/admin/challenge/'.$challengeId.'/'.$segment);
    }

    /**
     * @param  array<string, int|string>  $parameters
     */
    private static function to(string $name, array $parameters, string $path): string
    {
        return Route::has($name) ? route($name, $parameters) : url($path);
    }
}
