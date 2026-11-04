<?php

declare(strict_types=1);

namespace App\Modules\Draw\Models;

use Database\Factories\Modules\Draw\Models\DrawFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A commit/reveal draw on the legacy bs_draws table.
 *
 * The DATETIME columns are deliberately NOT cast: the old application reads and
 * writes plain "Y-m-d H:i:s" strings in the server's local time, and a cast would
 * shift or reformat them on the way back out.
 *
 * @property int $did
 * @property int|null $tid
 * @property string $slug
 * @property string $title
 * @property string $status committed|picking|closed|executed
 * @property string $mode direct|token
 * @property string $payload
 * @property string $commitment
 * @property string $beacon_source
 * @property string|null $pick_deadline
 * @property string|null $closed
 * @property string|null $server_seed
 * @property string|null $beacon_value
 * @property string|null $final_seed
 * @property string|null $result
 * @property int $created_uid
 * @property string $created
 * @property string|null $executed
 * @property string|null $discarded
 * @property string|null $discarded_reason
 * @property string|null $applied_at
 * @property int|null $applied_uid
 * @property-read Collection<int, DrawToken> $tokens
 * @property-read Collection<int, DrawEligible> $eligible
 */
class Draw extends Model
{
    use HasFactory;

    protected $table = 'bs_draws';

    protected $primaryKey = 'did';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): DrawFactory
    {
        return DrawFactory::new();
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(DrawToken::class, 'did', 'did')->orderBy('position');
    }

    public function eligible(): HasMany
    {
        return $this->hasMany(DrawEligible::class, 'did', 'did')->orderBy('deid');
    }

    /**
     * Whether the server seed and result may be shown publicly. A token draw is
     * revealed when picking closes; a direct draw when it is executed.
     */
    public function isRevealed(): bool
    {
        return $this->status === 'closed'
            || ($this->status === 'executed' && $this->mode !== 'token');
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadArray(): array
    {
        $decoded = json_decode((string) $this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resultArray(): ?array
    {
        if ($this->result === null || $this->result === '') {
            return null;
        }

        $decoded = json_decode($this->result, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
