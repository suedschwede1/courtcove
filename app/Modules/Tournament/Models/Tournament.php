<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use App\Modules\Draw\Models\Draw;
use Database\Factories\Modules\Tournament\Models\TournamentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A club championship on the legacy bs_tournaments table.
 *
 * Datetime and decimal columns are deliberately NOT cast: the old application reads
 * and writes plain strings, and a cast would shift or reformat them. `format` and
 * `lot_orders` hold JSON in ZF2's shapes and are never rewritten by the port.
 *
 * @property int $tid
 * @property string $title
 * @property int $season
 * @property int $is_test
 * @property int|null $cloned_from
 * @property string $status setup|drawn|running|finished (only setup is written)
 * @property int $active
 * @property string|null $format JSON
 * @property string|null $lot_orders JSON
 * @property string $itn_default
 * @property int $points_per_win
 * @property string $tiebreak_scope tied|all
 * @property string $runnerup_mode drop_last|points|per_match|lot
 * @property string $created
 */
class Tournament extends Model
{
    use HasFactory;

    protected $table = 'bs_tournaments';

    protected $primaryKey = 'tid';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): TournamentFactory
    {
        return TournamentFactory::new();
    }

    /** @return HasMany<TournamentPlayer, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(TournamentPlayer::class, 'tid', 'tid')->orderBy('seed')->orderBy('tpid');
    }

    /** @return HasMany<TournamentMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'tid', 'tid')
            ->orderBy('phase')->orderBy('grp')->orderBy('slot')->orderBy('tmid');
    }

    /** @return HasMany<TournamentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TournamentLine::class, 'tid', 'tid')->orderBy('phase')->orderBy('line');
    }

    /** @return HasMany<Draw, $this> */
    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class, 'tid', 'tid')->orderBy('did');
    }

    /**
     * Manual lot results by scope, as ZF2's TournamentManager::lotOrders(): anything that
     * does not decode to an array gives an empty list.
     *
     * @return array<string, mixed>
     */
    public function lotOrdersArray(): array
    {
        $raw = $this->lot_orders;
        $orders = $raw ? json_decode($raw, true) : [];

        return is_array($orders) ? $orders : [];
    }

    /**
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_test', 0);
    }
}
