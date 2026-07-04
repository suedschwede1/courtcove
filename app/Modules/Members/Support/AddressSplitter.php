<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

/**
 * Zerlegt den Alt-Adresswert "Straße, PLZ Ort" (Format des CSV-Imports)
 * in Straße, PLZ und Ort. Nicht parsebare Werte landen komplett in street.
 *
 * Wird von der Migration 2026_07_04_090001_extend_members_master_data referenziert — nicht löschen/umbenennen.
 */
class AddressSplitter
{
    /** @return array{street: ?string, zip: ?string, city: ?string} */
    public static function split(?string $address): array
    {
        $address = trim((string) $address);

        if ($address === '') {
            return ['street' => null, 'zip' => null, 'city' => null];
        }

        if (preg_match('/^(?:(.*),\s*)?(\d{4,5})\s+(.+)$/s', $address, $matches)) {
            $street = trim($matches[1]);

            return [
                'street' => $street === '' ? null : $street,
                'zip' => $matches[2],
                'city' => trim($matches[3]),
            ];
        }

        return ['street' => $address, 'zip' => null, 'city' => null];
    }
}
