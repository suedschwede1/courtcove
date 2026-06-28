<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global key-value configuration option (optionally per-locale).
 *
 * @property int $oid
 * @property string $key
 * @property string $value
 * @property string|null $locale
 */
class Option extends Model
{
    use HasFactory;

    protected $table = 'bs_options';

    protected $primaryKey = 'oid';

    public $timestamps = false;

    protected $fillable = ['key', 'value', 'locale'];

    /**
     * Get an option value by key, with optional fallback default.
     * Prefers a locale-specific row, falling back to the locale-less default.
     */
    public static function getValue(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $query = static::where('key', $key);

        if ($locale !== null) {
            $localized = (clone $query)->where('locale', $locale)->value('value');
            if ($localized !== null) {
                return $localized;
            }
        }

        return $query->whereNull('locale')->value('value')
            ?? $query->value('value')
            ?? $default;
    }
}
