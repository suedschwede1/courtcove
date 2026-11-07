<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A dismissed "possible family" suggestion: signature = sha256 of the sorted member ids. dismissed_by_user_id
 * is a bs_users.uid without a database foreign key.
 *
 * @property int $id
 * @property string $signature
 * @property int|null $dismissed_by_user_id
 * @property Carbon|null $created_at
 */
class HouseholdSuggestionDismissal extends Model
{
    protected $fillable = ['signature', 'dismissed_by_user_id'];
}
