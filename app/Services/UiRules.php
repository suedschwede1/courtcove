<?php

declare(strict_types=1);

namespace App\Services;

class UiRules
{
    public const ALLOWED_HEX = [
        '#bf4316', '#9e3412', '#fde8e1', '#151515', '#6a6e73', '#b8b8b8',
        '#fafafa', '#ffffff', '#e0e0e0', '#f0f0f0', '#f0faf0', '#3e8635',
        '#f9f0f0', '#c9190b', '#f0f8ff', '#0066cc', '#fff3cd', '#f0ab00',
        '#eff6ff', '#f4f4f4', '#1b1d21', '#a0a0a0', '#eae8e2', '#e8e8e8',
        '#ebebeb', '#f5f5f5', '#c7c7c7', '#d1cbc0', '#8a8d90', '#1a3a6b',
        '#7f2010', '#fff8f6', '#fff3f0', '#a84433', '#c75518',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return [
            // --- Farben ---
            [
                'id'      => 'CLR-01',
                'warn'    => 'Hex-Farbe %s nicht im Design Guide — Palette in docs/DESIGN_GUIDE.md prüfen',
                'pattern' => '/#([0-9a-fA-F]{6})\b/i',
                'exclude' => ['emails/'],
                'context' => false,
                'hex'     => true,
            ],
            [
                'id'      => 'CLR-02',
                'warn'    => 'Fehlertext: text-red-600 verwenden, nicht text-red-500',
                'pattern' => '/\btext-red-500\b/',
                'exclude' => [],
                'context' => false,
            ],
            [
                'id'      => 'CLR-03',
                'warn'    => 'Fehler-Border: border-red-400 verwenden, nicht border-red-500',
                'pattern' => '/\bborder-red-500\b/',
                'exclude' => [],
                'context' => false,
            ],

            // --- Buttons ---
            [
                'id'      => 'BTN-01',
                'warn'    => 'Button: bg-[#bf4316] verwenden, nicht bg-orange-*',
                'pattern' => '/<button[^>]*bg-orange-/i',
                'exclude' => [],
                'context' => false,
            ],
            [
                'id'      => 'BTN-02',
                'warn'    => 'Danger-Button: bg-[#c9190b] verwenden, nicht bg-red-*',
                'pattern' => '/<button[^>]*bg-red-/i',
                'exclude' => [],
                'context' => false,
            ],
            [
                'id'      => 'BTN-03',
                'warn'    => 'Button: rounded (6px) verwenden, nicht rounded-full',
                'pattern' => '/<button[^>]*rounded-full/i',
                'exclude' => [],
                'context' => false,
            ],
            [
                'id'      => 'BTN-04',
                'warn'    => 'Button ohne Höhenklasse — h-9 (36px) oder h-10 (40px) prüfen',
                'pattern' => '/<button(?!.*\bh-(?:9|10)\b)[^>]*>/i',
                'exclude' => [],
                'context' => false,
            ],

            // --- Inputs / Select / Textarea ---
            [
                'id'      => 'INP-01',
                'warn'    => 'Formularfeld: rounded (6px) verwenden, nicht rounded-full',
                'pattern' => '/<(?:input|select|textarea)[^>]*rounded-full/i',
                'exclude' => [],
                'context' => false,
            ],
            [
                'id'      => 'INP-02',
                'warn'    => 'Formularfeld: rounded (6px) verwenden, nicht rounded-lg',
                'pattern' => '/<(?:input|select|textarea)[^>]*rounded-lg/i',
                'exclude' => [],
                'context' => false,
            ],
            [
                'id'      => 'INP-03',
                'warn'    => 'Formularfeld: rounded (6px) verwenden, nicht rounded-xl',
                'pattern' => '/<(?:input|select|textarea)[^>]*rounded-xl/i',
                'exclude' => [],
                'context' => false,
            ],
            [
                'id'      => 'INP-04',
                'warn'    => 'Focus-Ring: focus:ring-[#bf4316] verwenden, nicht focus:ring-blue-*',
                'pattern' => '/<input[^>]*focus:ring-blue-/i',
                'exclude' => [],
                'context' => false,
            ],

            // --- Badges ---
            [
                'id'      => 'BDG-01',
                'warn'    => 'Badge: rounded (4px) verwenden, nicht rounded-full',
                'pattern' => '/(?=.*\bpx-)(?=.*\bpy-)(?=.*(?:text-xs|text-\[11px\])).*\brounded-full\b/',
                'exclude' => [],
                'context' => false,
            ],

            // --- Tabellen (Kontext-Regeln) ---
            [
                'id'      => 'TBL-01',
                'warn'    => 'Tabellenkopf: uppercase-Klasse fehlt',
                'pattern' => '/<thead/i',
                'exclude' => [],
                'context' => true,
                'context_require' => 'uppercase',
            ],
            [
                'id'      => 'TBL-02',
                'warn'    => 'Tabellenkopf: bg-[#fafafa] fehlt',
                'pattern' => '/<thead/i',
                'exclude' => [],
                'context' => true,
                'context_require' => 'bg-[#fafafa]',
            ],

            // --- Typografie ---
            [
                'id'      => 'TYP-01',
                'warn'    => 'Schriftgröße unter 10px — Design-Minimum: 10px (Kalender-Ausnahme)',
                'pattern' => '/text-\[([1-9])px\]/',
                'exclude' => [],
                'context' => false,
            ],
            [
                'id'      => 'TYP-02',
                'warn'    => 'Schriftart: nur Red Hat Display/Text erlaubt, nicht font-serif/font-mono',
                'pattern' => '/\bfont-(?:serif|mono)\b/',
                'exclude' => [],
                'context' => false,
            ],

            // --- Modals ---
            [
                'id'      => 'MOD-01',
                'warn'    => 'Modal-Header: bg-[#1b1d21] prüfen — andere bg-* Klasse gefunden',
                'pattern' => '/modal.*\bbg-(?!\[#1b1d21\])[a-z\[#]/i',
                'exclude' => [],
                'context' => false,
            ],

            // --- Inline Styles ---
            [
                'id'      => 'STY-01',
                'warn'    => 'Inline style-Attribut — Tailwind oder booking.css verwenden',
                'pattern' => '/\bstyle="/',
                'exclude' => ['emails/'],
                'context' => false,
            ],
        ];
    }
}
