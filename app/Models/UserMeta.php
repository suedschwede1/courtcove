<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arbitrary key-value metadata attached to a user.
 *
 * @property int         $umid
 * @property int         $uid
 * @property string      $meta_key
 * @property string|null $meta_value
 */
class UserMeta extends Model
{
    use HasFactory;

    protected $table      = 'bs_users_meta';
    protected $primaryKey = 'umid';
    public $timestamps    = false;
    protected $fillable   = ['uid', 'meta_key', 'meta_value'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }
}
