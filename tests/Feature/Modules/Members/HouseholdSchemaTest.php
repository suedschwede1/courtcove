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
 * Households (family fee) are strictly additive: new tables plus two nullable/defaulted columns,
 * no backfill, nothing created automatically, and every addition is known to the schema checker.
 */
class HouseholdSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** The seven household migrations, in the order they run. */
    private const MIGRATIONS = [
        '2026_11_07_090001_create_member_households_table.php',
        '2026_11_07_090002_add_household_id_to_members_table.php',
        '2026_11_07_090003_add_billing_unit_to_fee_categories_table.php',
        '2026_11_07_090004_create_household_dues_table.php',
        '2026_11_07_090005_create_household_due_member_table.php',
        '2026_11_07_090006_create_household_due_payment_table.php',
        '2026_11_07_090007_create_household_suggestion_dismissals_table.php',
    ];

    #[Test]
    public function the_household_tables_and_columns_exist_after_migrating(): void
    {
        $this->assertTrue(Schema::hasColumns('member_households', ['id', 'name', 'fee_category_id', 'payer_member_id', 'dissolved_at', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumn('members', 'household_id'));
        $this->assertTrue(Schema::hasColumn('fee_categories', 'billing_unit'));
        $this->assertTrue(Schema::hasColumns('household_dues', ['id', 'household_id', 'fiscal_year_id', 'fee_category_id', 'amount', 'status', 'teilbetrag', 'kommentar', 'bezahlt_bis_fiscal_year_id', 'faellig_am', 'bezahlt_am', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('household_due_member', ['id', 'household_due_id', 'member_id', 'fiscal_year_id', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('household_due_payment', ['payment_id', 'household_due_id', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('household_suggestion_dismissals', ['id', 'signature', 'dismissed_by_user_id', 'created_at', 'updated_at']));
    }

    #[Test]
    public function existing_rows_keep_their_values_and_no_household_is_created(): void
    {
        $category = FeeCategory::create(['name' => 'Erwachsene']);
        $member = Member::factory()->create(['fee_category_id' => $category->id]);

        $this->assertSame('member', $category->fresh()?->getAttribute('billing_unit'));
        $this->assertNull($member->fresh()?->getAttribute('household_id'));
        $this->assertSame(0, DB::table('member_households')->count());
        $this->assertSame(0, DB::table('household_dues')->count());
    }

    #[Test]
    public function the_schema_checker_tracks_every_new_table_without_drift(): void
    {
        $checker = app(DatabaseSchemaChecker::class);
        $new = ['member_households', 'household_dues', 'household_due_member', 'household_due_payment', 'household_suggestion_dismissals'];

        foreach ($new as $table) {
            $this->assertTrue($checker->knowsTable($table), $table);
        }
        $this->assertSame([], array_values(array_intersect($new, $checker->untrackedTables())));
        foreach ($checker->checkTables() as $row) {
            $this->assertSame([], $row['missing_columns'], $row['table']);
        }

        $drift = collect($checker->driftReport())
            ->whereIn('table', [...$new, 'members', 'fee_categories'])
            ->values()
            ->all();
        $this->assertSame([], $drift);
    }

    #[Test]
    public function rolling_back_the_household_migrations_removes_only_what_they_added(): void
    {
        // Run the seven down() methods directly (newest first) instead of migrate:rollback --step,
        // because later migrations of other modules sort after these files.
        foreach (array_reverse(self::MIGRATIONS) as $file) {
            $this->migration($file)->down();
        }

        foreach (['member_households', 'household_dues', 'household_due_member', 'household_due_payment', 'household_suggestion_dismissals'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertFalse(Schema::hasColumn('members', 'household_id'));
        $this->assertFalse(Schema::hasColumn('fee_categories', 'billing_unit'));
        $this->assertTrue(Schema::hasTable('member_dues'));
        $this->assertTrue(Schema::hasColumn('members', 'fee_category_id'));
        $this->assertTrue(Schema::hasColumn('fee_categories', 'deactivated_at'));

        foreach (self::MIGRATIONS as $file) {
            $this->migration($file)->up();
        }
        $this->assertTrue(Schema::hasColumn('members', 'household_id'));
        $this->assertTrue(Schema::hasTable('household_suggestion_dismissals'));
    }

    #[Test]
    public function running_the_household_migrations_twice_is_harmless(): void
    {
        foreach (self::MIGRATIONS as $file) {
            $this->migration($file)->up();
        }

        $this->assertTrue(Schema::hasColumn('members', 'household_id'));
        $this->assertTrue(Schema::hasColumn('fee_categories', 'billing_unit'));
    }

    private function migration(string $file): Migration
    {
        /** @var Migration $migration */
        $migration = require base_path('app/Modules/Members/Database/Migrations/'.$file);

        return $migration;
    }
}
