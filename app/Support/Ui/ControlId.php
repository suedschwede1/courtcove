<?php

declare(strict_types=1);

namespace App\Support\Ui;

use Illuminate\View\Factory;

/**
 * Unique form control ids within one rendered page.
 *
 * Ids are tracked in the view factory's "rendered once" registry (the one behind
 * Blade's @once), which Laravel flushes after every top-level render, so each page
 * starts fresh while repeated field names on the same page get -2, -3 suffixes.
 */
final class ControlId
{
    private const PREFIX = 'ui-control-id:';

    /** The first free id for $base on the current page; it is reserved at once. */
    public static function claim(string $base): string
    {
        $views = self::views();
        $id = $base;
        for ($n = 2; $views->hasRenderedOnce(self::PREFIX.$id); $n++) {
            $id = $base.'-'.$n;
        }
        $views->markAsRenderedOnce(self::PREFIX.$id);

        return $id;
    }

    /** Records an explicitly given id so automatic ids never reuse it. */
    public static function reserve(string $id): string
    {
        self::views()->markAsRenderedOnce(self::PREFIX.$id);

        return $id;
    }

    /**
     * The id of the first visible form control in rendered slot markup, if any.
     * A control that brings its own id (explicit or claimed) is what the label
     * and help/error texts must point at.
     */
    public static function fromMarkup(string $html): ?string
    {
        preg_match_all('/<(?:input|select|textarea)\b[^>]*>/i', $html, $tags);
        foreach ($tags[0] as $tag) {
            if (preg_match('/(?<![\w:-])type\s*=\s*"hidden"/i', $tag)) {
                continue;
            }
            if (preg_match('/(?<![\w:-])id\s*=\s*"([^"]+)"/i', $tag, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private static function views(): Factory
    {
        return app(Factory::class);
    }
}
