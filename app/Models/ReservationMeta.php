<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $rmid @property int $rid @property string $key @property string $value */
class ReservationMeta extends Model
{
    use HasFactory;

    protected $table = 'bs_reservations_meta';

    protected $primaryKey = 'rmid';

    public $timestamps = false;

    protected $fillable = ['rid', 'key', 'value'];
}
