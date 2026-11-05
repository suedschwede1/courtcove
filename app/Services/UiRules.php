<?php

declare(strict_types=1);

namespace App\Services;

class UiRules
{
    /** Every light and dark token value from app.css, plus plain white and black (lowercase). */
    public const TOKEN_HEX = [
        '#0f0e0d', '#171513', '#1b1917', '#1d1b18', '#1e3329', '#1f1c19', '#1f5a44', '#211e1b',
        '#252220', '#2c2824', '#34302b', '#3a2419', '#3a2e12', '#3a3631', '#3d1c15', '#4a443d',
        '#5fae87', '#6a6259', '#6b6359', '#7a4e00', '#8f3412', '#8fd0ae', '#9a2b17', '#a79d8f',
        '#b5441a', '#bdb2a2', '#cfc5b5', '#cfc6b8', '#e07a4f', '#e3dcd0', '#e3f0e8', '#e6dfd3',
        '#e7e0d5', '#ede8df', '#efe9df', '#f08a72', '#f0936c', '#f0c46b', '#f1ece3', '#f3eee6',
        '#f5f1ea', '#f7f4ee', '#f8e1dc', '#faf8f4', '#fbefd5', '#fbf1eb', '#fff', '#000', '#ffffff',
    ];

    private const COLOUR_HEX = '/(?<![\w&])#(?:[0-9a-f]{6}|[0-9a-f]{3})\b/i';

    private const SMALL_CONTROL = '(?:6|7|8|9|10)';

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return [
            // --- Colours ---
            [
                'id' => 'CLR-01',
                'warn' => 'Hex colour %s in a view: use a token class (bg-clay, text-ink-2) from docs/DESIGN.md',
                'pattern' => self::COLOUR_HEX,
                'exclude' => ['emails/'],
                'hex' => true,
                'allowed' => [],
            ],
            [
                'id' => 'CLR-02',
                'warn' => 'Tailwind palette colour: use a token class (text-ink, bg-clay, text-danger) from docs/DESIGN.md',
                'pattern' => '/\b(?:bg|text|border|ring|divide|from|to)-(?:red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone)-\d{2,3}\b/i',
                'exclude' => ['emails/'],
            ],
            [
                'id' => 'CSS-01',
                'warn' => 'Hex colour %s in CSS outside the token definitions: use var(--color-*) or a token',
                'pattern' => self::COLOUR_HEX,
                'files' => 'css',
                'hex' => true,
                'allowed' => self::TOKEN_HEX,
                'allowed_only_in' => ['app.css'],
            ],

            // --- Buttons ---
            [
                'id' => 'BTN-01',
                'warn' => 'Styled raw <button>: use <x-ui.button>',
                'pattern' => '/<button\b[^>]*\b(?:bg|border)-/i',
                'exclude' => ['components/ui/', 'components/layout/'],
            ],
            [
                'id' => 'BTN-02',
                'warn' => 'Button below the 46 px control height: use <x-ui.button> (h-control)',
                'pattern' => '/<button\b[^>]*\bh-'.self::SMALL_CONTROL.'\b/i',
            ],
            [
                'id' => 'BTN-03',
                'warn' => 'Button inside a link: use <x-ui.button href="..."> instead of nesting',
                'pattern' => '/<a\b(?:(?!<\/a>).)*<button\b/i',
            ],

            // --- Inputs / Select / Textarea ---
            [
                'id' => 'INP-01',
                'warn' => 'Form field below the 46 px control height: use <x-ui.input> or <x-ui.select>',
                'pattern' => '/<(?:input|select|textarea)\b[^>]*\bh-'.self::SMALL_CONTROL.'\b/i',
            ],
            [
                'id' => 'INP-02',
                'warn' => 'Form field with rounded-full: use <x-ui.input> or <x-ui.select> (rounded-sm)',
                'pattern' => '/<(?:input|select|textarea)\b[^>]*\brounded-full\b/i',
            ],
            [
                'id' => 'INP-03',
                'warn' => 'Custom focus ring on a form field: use <x-ui.input> (focus ring comes from base styles)',
                'pattern' => '/<(?:input|select|textarea)\b[^>]*\bfocus:ring-/i',
            ],

            // --- Badges ---
            [
                'id' => 'BDG-01',
                'warn' => 'Square badge: use <x-ui.badge> (pill shape)',
                'pattern' => '/(?=.*\bpx-)(?=.*\bpy-)(?=.*\btext-(?:xs|\[1[0-3]px\]))(?=.*\brounded\b(?!-))/',
            ],

            // --- Tables (context rule) ---
            [
                'id' => 'TBL-01',
                'warn' => 'Raw <table>: use <x-ui.table>',
                'pattern' => '/<table\b/i',
                'exclude' => ['emails/', 'components/ui/'],
                'context' => true,
                'context_require' => 'ds-table',
            ],

            // --- Typography ---
            [
                'id' => 'TYP-01',
                'warn' => 'Font size below 11 px: use a text-* token (text-label is 12 px)',
                'pattern' => '/\btext-\[(?:[1-9]|10)px\]/',
            ],
            [
                'id' => 'TYP-02',
                'warn' => 'Font family outside the design system: use font-display or font-body (font-mono for code)',
                'pattern' => '/\bfont-serif\b|Red Hat/i',
            ],

            // --- Inline styles / handlers ---
            [
                'id' => 'STY-01',
                'warn' => 'Inline style attribute: use a token class or app.css',
                'pattern' => '/\bstyle="/',
                'exclude' => ['emails/'],
            ],
            [
                'id' => 'STY-02',
                'warn' => 'Inline hover handler: use hover: classes',
                'pattern' => '/\bon(?:mouseover|mouseout)=/i',
            ],

            // --- Emoji and character icons ---
            [
                'id' => 'EMO-01',
                'warn' => 'Emoji in a view: use <x-ui.icon>',
                'pattern' => '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{26FF}]/u',
            ],
            [
                'id' => 'ICN-01',
                'warn' => 'Character used as icon: use <x-ui.icon>',
                'pattern' => '/&#8249;|&#8250;|&#x2715;|&times;|[←→‹›☰✕]|>\s*\.\.\.\s*<\/button>/iu',
            ],
        ];
    }
}
