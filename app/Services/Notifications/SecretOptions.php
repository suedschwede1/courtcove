<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Option;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;

/**
 * Secrets in bs_options, stored as `enc:` + Crypt::encryptString(). A value that cannot be decrypted
 * (changed APP_KEY, damaged row) reads as null and never throws; isUnreadable() tells the two cases apart.
 * "Empty input means unchanged" is the controller's job: set(key, null) removes the row.
 */
final class SecretOptions
{
    private const PREFIX = 'enc:';

    public function get(string $key): ?string
    {
        $raw = $this->raw($key);
        if ($raw === null || ! str_starts_with($raw, self::PREFIX)) {
            return null;
        }

        try {
            return Crypt::decryptString(substr($raw, strlen(self::PREFIX)));
        } catch (DecryptException) {
            return null;
        }
    }

    public function set(string $key, ?string $plain): void
    {
        if ($plain === null || $plain === '') {
            Option::where('key', $key)->delete();

            return;
        }

        $value = self::PREFIX.Crypt::encryptString($plain);
        $row = Option::where('key', $key)->first();
        if ($row === null) {
            Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
        } else {
            $row->update(['value' => $value]);
        }
    }

    public function isSet(string $key): bool
    {
        return $this->raw($key) !== null;
    }

    /** A value is stored but cannot be read back. */
    public function isUnreadable(string $key): bool
    {
        return $this->isSet($key) && $this->get($key) === null;
    }

    public static function mask(?string $value): string
    {
        if ($value === null || mb_strlen($value) <= 4) {
            return '****';
        }

        return '****'.mb_substr($value, -4);
    }

    private function raw(string $key): ?string
    {
        try {
            $value = Option::getValue($key);
        } catch (QueryException) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
