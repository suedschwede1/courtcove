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
        foreach (array_keys(self::MIGRATIONS) as $name) {
            Schema::drop($name);
            Schema::create($name, function (Blueprint $table): void {
                $table->increments('id');
                $table->string('legacy_note')->nullable();
            });
            DB::table($name)->insert(['legacy_note' => 'keep']);

            $this->migration($name)->up();

            $this->assertSame(['id', 'legacy_note'], Schema::getColumnListing($name), $name);
            $this->assertSame(1, DB::table($name)->count(), $name);
        }
    }

    #[Test]
    public function column_types_and_plain_indexes_match_the_legacy_sql(): void
    {
        $types = [];
        foreach (Schema::getColumns('bs_draws') as $column) {
            $types['bs_draws.'.$column['name']] = $column;
        }
        foreach (Schema::getColumns('bs_draw_tokens') as $column) {
            $types['bs_draw_tokens.'.$column['name']] = $column;
        }

        foreach (['bs_draws.pick_deadline', 'bs_draws.closed', 'bs_draws.created', 'bs_draws.executed',
            'bs_draws.discarded', 'bs_draws.applied_at', 'bs_draw_tokens.claimed'] as $key) {
            $this->assertStringContainsString('datetime', strtolower($types[$key]['type_name']), $key);
        }
        foreach (['bs_draws.payload', 'bs_draws.result'] as $key) {
            $this->assertStringContainsString('text', strtolower($types[$key]['type_name']), $key);
        }

        $nullable = ['tid', 'pick_deadline', 'closed', 'server_seed', 'beacon_value', 'final_seed', 'result',
            'executed', 'discarded', 'discarded_reason', 'applied_at', 'applied_uid'];
        foreach (Schema::getColumns('bs_draws') as $column) {
            $this->assertSame(in_array($column['name'], $nullable, true), (bool) $column['nullable'], 'bs_draws.'.$column['name']);
        }
        $this->assertTrue($types['bs_draw_tokens.slot']['nullable']);
        $this->assertTrue($types['bs_draw_tokens.assigned_by']['nullable']);
        $this->assertFalse($types['bs_draw_tokens.token']['nullable']);

        $plain = fn (string $table): array => collect(Schema::getIndexes($table))
            ->filter(fn (array $i): bool => ! $i['unique'] && ! $i['primary'])
            ->map(fn (array $i): array => $i['columns'])
            ->all();

        $this->assertContains(['tid'], $plain('bs_draws'));
        $this->assertContains(['did', 'claimed_tpid'], $plain('bs_draw_tokens'));
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
