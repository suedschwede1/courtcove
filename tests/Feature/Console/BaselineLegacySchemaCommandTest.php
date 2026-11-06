<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaselineLegacySchemaCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CREATE_BS_COUNT = 15;

    // The baseline covers the 15 core tables only; the guarded module adoption migrations (draw*, pyramid*, mixed*) run on their own.
    private function forgetCreateBsMigrations(): void
    {
        foreach (DB::table('migrations')->pluck('migration') as $name) {
            if (preg_match('/create_bs_(?!draw|pyramid|mixed|tournament)[a-z_]+_table$/', $name)) {
                DB::table('migrations')->where('migration', $name)->delete();
            }
        }
    }

    private function createBsNames(): array
    {
        return DB::table('migrations')->pluck('migration')
            ->filter(fn ($n) => preg_match('/create_bs_(?!draw|pyramid|mixed|tournament)[a-z_]+_table$/', $n) === 1)
            ->sort()->values()->all();
    }

    #[Test]
    public function records_create_migrations_for_existing_legacy_tables(): void
    {
        $expected = $this->createBsNames();
        $this->assertCount(self::CREATE_BS_COUNT, $expected);

        $this->forgetCreateBsMigrations();
        $this->assertSame([], $this->createBsNames());

        $this->artisan('db:baseline-legacy')->assertExitCode(0);

        $this->assertSame($expected, $this->createBsNames());

        Artisan::call('migrate', ['--pretend' => true]);
        $this->assertStringNotContainsString('create_bs_', Artisan::output());
    }

    #[Test]
    public function is_idempotent(): void
    {
        $this->forgetCreateBsMigrations();
        $this->artisan('db:baseline-legacy')->assertExitCode(0);

        $count = DB::table('migrations')->count();
        $snapshot = DB::table('migrations')->orderBy('id')->get()->toArray();

        $this->artisan('db:baseline-legacy')
            ->expectsOutputToContain('Recorded 0')
            ->assertExitCode(0);

        $this->assertSame($count, DB::table('migrations')->count());
        $this->assertEquals($snapshot, DB::table('migrations')->orderBy('id')->get()->toArray());
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $this->forgetCreateBsMigrations();
        $count = DB::table('migrations')->count();

        $this->artisan('db:baseline-legacy', ['--dry-run' => true])
            ->expectsOutputToContain('create_bs_users_table')
            ->expectsOutputToContain('Would record '.self::CREATE_BS_COUNT)
            ->assertExitCode(0);

        $this->assertSame($count, DB::table('migrations')->count());
        $this->assertSame([], $this->createBsNames());
    }

    #[Test]
    public function skips_tables_that_do_not_exist(): void
    {
        $this->forgetCreateBsMigrations();
        Schema::drop('bs_squares_coupons');

        $this->artisan('db:baseline-legacy')->assertExitCode(0);

        $names = $this->createBsNames();
        $this->assertCount(self::CREATE_BS_COUNT - 1, $names);
        $this->assertEmpty(array_filter($names, fn ($n) => str_contains($n, 'bs_squares_coupons')));
        $this->assertFalse(Schema::hasTable('bs_squares_coupons'));
    }

    #[Test]
    public function uses_the_highest_existing_batch_and_leaves_other_rows_untouched(): void
    {
        $this->forgetCreateBsMigrations();
        DB::table('migrations')->insert(['migration' => '2099_01_01_000000_other_thing', 'batch' => 7]);
        $others = DB::table('migrations')->orderBy('id')->get()->toArray();

        $this->artisan('db:baseline-legacy')->assertExitCode(0);

        $this->assertEquals(
            $others,
            DB::table('migrations')->whereNotIn('migration', $this->createBsNames())->orderBy('id')->get()->toArray()
        );
        $this->assertSame(
            [7],
            DB::table('migrations')->whereIn('migration', $this->createBsNames())->distinct()->pluck('batch')->map(fn ($b) => (int) $b)->all()
        );
    }
}
