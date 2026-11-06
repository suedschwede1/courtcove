<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

/**
 * A list of flash messages per request (ZF2's flashMessenger shows several, e.g. "Erzeugt: ..." plus one
 * note per skipped group). Kept under the session flash key `module.flash`, shown once by
 * `tournament-base::partials.flash`.
 */
final class ModuleFlash
{
    public const KEY = 'module.flash';

    public static function success(string $text): void
    {
        self::add('success', $text);
    }

    public static function error(string $text): void
    {
        self::add('error', $text);
    }

    public static function info(string $text): void
    {
        self::add('info', $text);
    }

    /** @return list<array{type: string, text: string}> */
    public static function all(): array
    {
        $list = session(self::KEY, []);

        return is_array($list) ? array_values($list) : [];
    }

    private static function add(string $type, string $text): void
    {
        $session = session();

        // Messages flashed by the previous request are still readable now; a new list must not carry them on.
        $fresh = in_array(self::KEY, (array) $session->get('_flash.new', []), true);
        $list = $fresh ? self::all() : [];
        $list[] = ['type' => $type, 'text' => $text];

        $session->flash(self::KEY, $list);
    }
}
