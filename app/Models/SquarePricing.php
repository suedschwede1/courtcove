<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $sprid @property int $sid @property int $spid @property int $date_start @property int $date_end @property int $time_start @property int $time_end @property int $price @property int $priority */
class SquarePricing extends Model
{
    use HasFactory;

    protected $table      = 'bs_squares_pricing';
    protected $primaryKey = 'sprid';
    public $timestamps    = false;
    protected $fillable   = ['sid', 'spid', 'date_start', 'date_end', 'time_start', 'time_end', 'price', 'priority'];
}
