<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use Illuminate\Support\Facades\Log;

/**
 * Translates the texts of the ported array services before they reach the admin UI. The services keep their
 * ZF2 strings byte-identical (ASCII transliterations; golden vectors and tests pin them), so the known
 * messages are matched by their sprintf pattern and mapped to literal translation keys. Anything unknown
 * (an English internal message, SQL) is logged raw and shown as a generic note.
 */
final class ServiceMessages
{
    /** A ResolverService exception message (bracket refresh). */
    public static function resolver(string $message): string
    {
        if (preg_match('/^Losentscheidung in Gruppe (\S+) fuer Platz (\d+) erforderlich\.$/', $message, $m) === 1) {
            return (string) __('tournament::admin.service.lot_required', ['group' => $m[1], 'place' => $m[2]]);
        }

        if ($message === 'Losentscheidung erforderlich; die vorlaeufige Setzplatzreihenfolge darf nicht verwendet werden.') {
            return (string) __('tournament::admin.service.lot_required_order');
        }

        return self::unknown($message);
    }

    /** A GeneratorService skip reason of an incomplete group (generation). */
    public static function skippedReason(string $reason): string
    {
        if (preg_match('/^(\d+) von (\d+) Plaetzen besetzt, (\d+) Partie\(n\) noch offen$/', $reason, $m) === 1) {
            return (string) __('tournament::admin.service.seats_open', ['filled' => $m[1], 'size' => $m[2], 'open' => $m[3]]);
        }

        return self::unknown($reason);
    }

    private static function unknown(string $message): string
    {
        Log::warning('Untranslated tournament service message', ['message' => $message]);

        return (string) __('tournament::admin.service.internal');
    }
}
