<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

/**
 * German templates for texts that are STORED (feed events, forfeit score).
 *
 * The feed is shared with the old application, which shows these strings as-is
 * after a rollback, so they must never follow the viewer's locale and must not
 * go through __().
 */
final class EventText
{
    public const DECLINED = 'Eine Forderung wurde mit Grund abgelehnt.';

    public const WITHDRAWN = 'Eine Forderung wurde zurückgezogen.';

    public const SEEDED = 'Das Startfeld wurde nach ITN gereiht, die Pyramide läuft.';

    public const RELEASED = ':name ist zum Einfordern freigegeben.';

    public const EXTENDED = 'Frist verlängert bis :date: :note';

    public const EXPIRED = 'Eine Forderung ist ohne Spiel verfallen: :note';

    public const CORRECTED = 'Ein verbuchtes Ergebnis wurde richtiggestellt: :note';

    public const CLOSED = 'Die Saison ist abgeschlossen.';

    public const NORMALIZED = 'Rangliste normalisiert. Vorher: :problems';

    public const FORFEIT_SCORE = 'nicht angetreten';

    /**
     * @param  array<string, scalar|null>  $replace  keys include the colon, e.g. ':name'
     */
    public static function make(string $template, array $replace = []): string
    {
        return strtr($template, array_map(fn ($value): string => (string) $value, $replace));
    }
}
