<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-ladder administration grant on the legacy bs_pyramid_grants table.
 *
 * Datetime columns are deliberately NOT cast: the old application reads and
 * writes plain strings.
 *
 * @property int $id
 * @property int $ladder_id
 * @property int $uid
 * @property string $role admin
 * @property string $created
 */
class PyramidGrant extends Model
{
    protected $table = 'bs_pyramid_grants';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}
