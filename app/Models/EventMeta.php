<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $emid @property int $eid @property string $meta_key @property string|null $meta_value */
class EventMeta extends Model
{
    use HasFactory;

    protected $table      = 'bs_events_meta';
    protected $primaryKey = 'emid';
    public $timestamps    = false;
    protected $fillable   = ['eid', 'meta_key', 'meta_value'];
}
