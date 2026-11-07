<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use App\Services\DatabaseSchemaChecker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Anschlussmitglied: additive columns members.connected_to_member_id and fee_categories.connection_role; the
 * migration only marks the two existing import category names, never creates a category or a link.
 */
class MemberConnectionSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATIONS = [
        '2026_11_08_090001_add_connected_to_member_id_to_members_table.php',
        '2026_11_08_090002_add_connection_role_to_fee_categories_table.php',
    ];

    private function migration(string $file): Migration
    {
        /** @var Migration $migration */
        $migration = require base_path('app/Modules/Members/Database/Migrations/'.$file);

        return $migration;
    }

    #[Test]
    public function the_columns_exist_and_existing_rows_keep_their_values(): void
    {
        $this->assertTrue(Schema::hasColumn('members', 'connected_to_member_id'));
        $this->assertTrue(Schema::hasColumn('fee_categories', 'connection_role'));

        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Erwachsene']);
        $this->assertNull($member->fresh()?->connected_to_member_id);
        $this->assertNull($category->fresh()?->connection_role);
    }

    #[Test]
    public function the_migration_marks_only_the_two_import_category_names_once(): void
    {
        $full = FeeCategory::create(['name' => 'Vollzahler']);
        $partner = FeeCategory::create(['name' => 'Ehepartner/in, Lebensgefährte/in']);
        $other = FeeCategory::create(['name' => 'Jugend']);
        $manual = FeeCategory::create(['name' => 'Vollzahler alt']);
        DB::table('fee_categories')->where('id', $manual->id)->update(['connection_role' => 'vollzahler']);
        $categories = FeeCategory::count();

        $this->migration(self::MIGRATIONS[1])->down();
        $this->migration(self::MIGRATIONS[1])->up();

        $this->assertSame('vollzahler', $full->fresh()?->connection_role);
        $this->assertSame('anschluss', $partner->fresh()?->connection_role);
        $this->assertNull($other->fresh()?->connection_role);
        $this->assertSame($categories, FeeCategory::count());
    }

    #[Test]
    public function the_schema_checker_tracks_both_columns_without_drift(): void
    {
        $checker = app(DatabaseSchemaChecker::class);
        foreach ($checker->checkTables() as $row) {
            $this->assertSame([], $row['missing_columns'], $row['table']);
        }
        $this->assertSame([], collect($checker->driftReport())->whereIn('table', ['members', 'fee_categories'])->values()->all());
    }

    #[Test]
    public function rolling_back_removes_only_what_was_added_and_up_is_repeatable(): void
    {
        foreach (array_reverse(self::MIGRATIONS) as $file) {
            $this->migration($file)->down();
        }
        $this->assertFalse(Schema::hasColumn('members', 'connected_to_member_id'));
        $this->assertFalse(Schema::hasColumn('fee_categories', 'connection_role'));
        $this->assertTrue(Schema::hasColumn('members', 'household_id'));

        foreach ([...self::MIGRATIONS, ...self::MIGRATIONS] as $file) {
            $this->migration($file)->up();
        }
        $this->assertTrue(Schema::hasColumn('members', 'connected_to_member_id'));
        $this->assertTrue(Schema::hasColumn('fee_categories', 'connection_role'));
    }
}
