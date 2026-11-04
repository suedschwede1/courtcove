<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrawAdoptionMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MIGRATIONS = [
        'bs_draws' => '2026_11_05_090001_create_bs_draws_table.php',
        'bs_draw_tokens' => '2026_11_05_090002_create_bs_draw_tokens_table.php',
        'bs_draw_eligible' => '2026_11_05_090003_create_bs_draw_eligible_table.php',
    ];

    private function migration(string $table): object
    {
        return require base_path('app/Modules/Draw/Database/Migrations/'.self::MIGRATIONS[$table]);
    }

    #[Test]
    public function fresh_database_has_the_live_columns(): void
    {
        $this->assertSame(
            ['did', 'tid', 'slug', 'title', 'status', 'mode', 'payload', 'commitment', 'beacon_source',
                'pick_deadline', 'closed', 'server_seed', 'beacon_value', 'final_seed', 'result',
                'created_uid', 'created', 'executed', 'discarded', 'discarded_reason', 'applied_at', 'applied_uid'],
            Schema::getColumnListing('bs_draws'),
        );
        $this->assertSame(
            ['dtid', 'did', 'position', 'token', 'slot', 'claimed_tpid', 'claimed_uid', 'claimed', 'assigned_by'],
            Schema::getColumnListing('bs_draw_tokens'),
        );
        $this->assertSame(['deid', 'did', 'tpid'], Schema::getColumnListing('bs_draw_eligible'));
    }

    #[Test]
    public function up_leaves_an_existing_table_untouched(): void
    {
        Schema::drop('bs_draw_eligible');
        Schema::create('bs_draw_eligible', function (Blueprint $table): void {
            $table->increments('deid');
            $table->unsignedInteger('did');
            $table->unsignedInteger('tpid');
            $table->string('legacy_note')->nullable();
        });
        DB::table('bs_draw_eligible')->insert(['did' => 1, 'tpid' => 2, 'legacy_note' => 'keep']);

        $this->migration('bs_draw_eligible')->up();

        $this->assertContains('legacy_note', Schema::getColumnListing('bs_draw_eligible'));
        $this->assertSame(1, DB::table('bs_draw_eligible')->count());
    }

    #[Test]
    public function down_is_a_no_op_and_keeps_rows(): void
    {
        Draw::factory()->create();

        foreach (array_keys(self::MIGRATIONS) as $table) {
            $this->migration($table)->down();
        }

        foreach (array_keys(self::MIGRATIONS) as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertSame(1, Draw::count());
    }

    #[Test]
    public function unique_keys_exist(): void
    {
        $expected = [
            'bs_draws' => [['slug']],
            'bs_draw_tokens' => [['did', 'token'], ['did', 'position'], ['did', 'claimed_tpid']],
            'bs_draw_eligible' => [['did', 'tpid']],
        ];

        foreach ($expected as $table => $keys) {
            $unique = collect(Schema::getIndexes($table))
                ->filter(fn (array $index): bool => $index['unique'] && ! $index['primary'])
                ->map(fn (array $index): array => $index['columns'])
                ->all();

            foreach ($keys as $columns) {
                $this->assertContains($columns, $unique, "{$table} lacks unique ".implode(',', $columns));
            }
        }
    }
}
