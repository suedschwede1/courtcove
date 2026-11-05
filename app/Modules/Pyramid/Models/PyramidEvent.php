<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A feed entry on the legacy bs_pyramid_events table.
 *
 * Datetime columns are deliberately NOT cast: the old application reads and
 * writes plain strings.
 *
 * @property int $id
 * @property int $ladder_id
 * @property int|null $challenge_id
 * @property string $type challenge|schedule|result|join|admin
 * @property int|null $actor_uid
 * @property string $payload JSON text
 * @property string $created
 */
class PyramidEvent extends Model
{
    protected $table = 'bs_pyramid_events';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return json_decode((string) $this->payload, true) ?: [];
    }
}
