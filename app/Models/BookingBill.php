<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $bbid
 * @property int $bid
 * @property string $description
 * @property int|null $quantity
 * @property int|null $time
 * @property int $price
 * @property int $rate
 * @property bool $gross
 */
class BookingBill extends Model
{
    use HasFactory;

    protected $table = 'bs_bookings_bills';

    protected $primaryKey = 'bbid';

    public $timestamps = false;

    protected $fillable = ['bid', 'description', 'quantity', 'time', 'price', 'rate', 'gross'];

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'bid', 'bid');
    }
}
