<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $emid @property int $eid @property string $key @property string $value @property string|null $locale */
class EventMeta extends Model
{
    use HasFactory;

    protected $table = 'bs_events_meta';

    protected $primaryKey = 'emid';

    public $timestamps = false;

    protected $fillable = ['eid', 'key', 'value', 'locale'];
}
