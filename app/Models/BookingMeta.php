<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $bmid @property int $bid @property string $key @property string $value */
class BookingMeta extends Model
{
    use HasFactory;

    protected $table = 'bs_bookings_meta';

    protected $primaryKey = 'bmid';

    public $timestamps = false;

    protected $fillable = ['bid', 'key', 'value'];
}
