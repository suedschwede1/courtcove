<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Tournament\Models;

use App\Modules\Tournament\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 * The default format is the structure-only reference format of the golden vectors
 * (tests/Fixtures/tournament/formats/reference.json); it is null while that file is absent.
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    public function definition(): array
    {
        $file = base_path('tests/Fixtures/tournament/formats/reference.json');

        return [
            'title' => 'Demo-Meisterschaft',
            'season' => 2027,
            'is_test' => 0,
            'status' => 'setup',
            'active' => 0,
            'format' => is_file($file) ? file_get_contents($file) : null,
            'lot_orders' => null,
            'itn_default' => 10.0,
            'points_per_win' => 2,
            'tiebreak_scope' => 'tied',
            'runnerup_mode' => 'drop_last',
            'created' => '2027-01-15 10:00:00',
        ];
    }

    public function test(): static
    {
        return $this->state(['is_test' => 1]);
    }

    public function active(): static
    {
        return $this->state(['active' => 1]);
    }

    /** @param  array<string, mixed>  $format */
    public function withFormat(array $format): static
    {
        return $this->state(['format' => json_encode($format, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** @param  array<string, list<int>>  $orders */
    public function withLotOrders(array $orders): static
    {
        return $this->state(['lot_orders' => json_encode($orders)]);
    }
}
