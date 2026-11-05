<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

/**
 * wa.me link with a prefilled message. Phone numbers are stripped of separators and a
 * leading 0 becomes the Austrian country code 43. Visibility checks belong in the views.
 */
final class WhatsApp
{
    public static function url(?string $phone, string $text = ''): string
    {
        $number = self::normalize($phone);

        if ($number === null) {
            return '';
        }

        $url = 'https://wa.me/'.$number;

        if ($text !== '') {
            $url .= '?text='.rawurlencode($text);
        }

        return $url;
    }

    /**
     * @return string|null international number without plus, or null when unusable
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+')) {
            return substr($digits, 1);
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '43'.substr($digits, 1);
        }

        // Without a leading zero or country code the number is ambiguous: no link beats a wrong one.
        if (strlen($digits) < 9) {
            return null;
        }

        return $digits;
    }
}
