<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global key-value configuration option.
 *
 * @property int         $oid
 * @property string      $option_key
 * @property string|null $option_value
 */
class Option extends Model
{
    use HasFactory;

    protected $table      = 'bs_options';
    protected $primaryKey = 'oid';
    public $timestamps    = false;
    protected $fillable   = ['option_key', 'option_value'];

    /** Get an option value by key, with optional fallback default. */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::where('option_key', $key)->value('option_value') ?? $default;
    }
}
