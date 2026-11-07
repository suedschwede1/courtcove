<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Services\DatabaseSchemaChecker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * The household constraints on a real MySQL server (InnoDB foreign keys and unique indexes that
 * SQLite only approximates): one due per household and year, one coverage per member and year,
 * restrict on household delete, null on category/payer delete, same collation as members, and no
 * drift between the migrations and the schema checker manifest. Runs with composer test:mysql.
 */
#[Group('mysql')]
class HouseholdMysqlSchemaTest extends MysqlTestCase
{
    private const NEW_TABLES = ['member_households', 'household_dues', 'household_due_member', 'household_due_payment', 'household_suggestion_dismissals'];

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        DB::table('household_due_payment')->delete();
        DB::table('household_due_member')->delete();
        DB::table('household_dues')->delete();
        DB::table('members')->update(['household_id' => null]);
        DB::table('member_households')->delete();
        DB::table('payments')->delete();
        DB::table('members')->delete();
        DB::table('fee_category_rates')->delete();
        DB::table('fee_categories')->delete();
        DB::table('fiscal_years')->delete();
    }

    #[Test]
    public function a_household_cannot_have_two_dues_in_one_fiscal_year(): void
    {
        [$household, $year, $category] = $this->seedHouseholdYearCategory();
        $this->insertDue($household, $year, $category);

        $this->expectException(QueryException::class);
        $this->insertDue($household, $year, $category);
    }

    #[Test]
    public function a_member_cannot_be_covered_twice_in_one_fiscal_year(): void
    {
        [$household, $year, $category] = $this->seedHouseholdYearCategory();
        $other = DB::table('member_households')->insertGetId(['name' => null]);
        $first = $this->insertDue($household, $year, $category);
        $second = $this->insertDue($other, $year, $category);
        $anna = $this->insertMember('Anna', 'Muster');
        DB::table('household_due_member')->insert(['household_due_id' => $first, 'member_id' => $anna, 'fiscal_year_id' => $year]);

        $this->expectException(QueryException::class);
        DB::table('household_due_member')->insert(['household_due_id' => $second, 'member_id' => $anna, 'fiscal_year_id' => $year]);
    }

    #[Test]
    public function the_same_member_cannot_be_attached_twice_to_one_household_due(): void
    {
        [$household, $year, $category] = $this->seedHouseholdYearCategory();
        $due = $this->insertDue($household, $year, $category);
        $anna = $this->insertMember('Anna', 'Muster');
        DB::table('household_due_member')->insert(['household_due_id' => $due, 'member_id' => $anna, 'fiscal_year_id' => $year]);

        $this->expectException(QueryException::class);
        DB::table('household_due_member')->insert(['household_due_id' => $due, 'member_id' => $anna, 'fiscal_year_id' => $year]);
    }

    #[Test]
    public function deleting_a_household_with_dues_is_restricted(): void
    {
        [$household, $year, $category] = $this->seedHouseholdYearCategory();
        $this->insertDue($household, $year, $category);

        try {
            DB::table('member_households')->where('id', $household)->delete();
            $this->fail('Deleting a household with dues must be refused.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('member_households')->where('id', $household)->count());
            $this->assertSame(1, DB::table('household_dues')->count());
        }
    }

    #[Test]
    public function deleting_a_fee_category_nulls_the_household_tariff(): void
    {
        [$household, , $category] = $this->seedHouseholdYearCategory();

        DB::table('fee_categories')->where('id', $category)->delete();

        $this->assertNull(DB::table('member_households')->where('id', $household)->value('fee_category_id'));
    }

    #[Test]
    public function deleting_a_household_without_dues_or_its_payer_nulls_the_references(): void
    {
        [$household] = $this->seedHouseholdYearCategory();
        $anna = $this->insertMember('Anna', 'Muster', $household);
        $ben = $this->insertMember('Ben', 'Muster', $household);
        DB::table('member_households')->where('id', $household)->update(['payer_member_id' => $anna]);

        DB::table('members')->where('id', $anna)->delete();
        $this->assertNull(DB::table('member_households')->where('id', $household)->value('payer_member_id'));

        DB::table('member_households')->where('id', $household)->delete();
        $this->assertNull(DB::table('members')->where('id', $ben)->value('household_id'));
    }

    #[Test]
    public function a_payment_cannot_be_linked_twice_to_one_household_due(): void
    {
        [$household, $year, $category] = $this->seedHouseholdYearCategory();
        $due = $this->insertDue($household, $year, $category);
        $anna = $this->insertMember('Anna', 'Muster', $household);
        $payment = DB::table('payments')->insertGetId(['member_id' => $anna, 'amount' => '235.00', 'paid_at' => '2026-04-01']);
        DB::table('household_due_member')->insert(['household_due_id' => $due, 'member_id' => $anna, 'fiscal_year_id' => $year]);
        DB::table('household_due_payment')->insert(['payment_id' => $payment, 'household_due_id' => $due]);

        $this->expectException(QueryException::class);
        DB::table('household_due_payment')->insert(['payment_id' => $payment, 'household_due_id' => $due]);
    }

    #[Test]
    public function a_household_due_cascades_to_coverage_and_payment_links(): void
    {
        [$household, $year, $category] = $this->seedHouseholdYearCategory();
        $due = $this->insertDue($household, $year, $category);
        $anna = $this->insertMember('Anna', 'Muster', $household);
        $payment = DB::table('payments')->insertGetId(['member_id' => $anna, 'amount' => '235.00', 'paid_at' => '2026-04-01']);
        DB::table('household_due_member')->insert(['household_due_id' => $due, 'member_id' => $anna, 'fiscal_year_id' => $year]);
        DB::table('household_due_payment')->insert(['payment_id' => $payment, 'household_due_id' => $due]);

        DB::table('household_dues')->where('id', $due)->delete();

        $this->assertSame(0, DB::table('household_due_member')->count());
        $this->assertSame(0, DB::table('household_due_payment')->count());
        $this->assertSame(1, DB::table('payments')->count());
    }

    #[Test]
    public function a_dismissal_signature_is_unique(): void
    {
        $signature = hash('sha256', '1,2');
        DB::table('household_suggestion_dismissals')->insert(['signature' => $signature]);

        $this->expectException(QueryException::class);
        DB::table('household_suggestion_dismissals')->insert(['signature' => $signature]);
    }

    #[Test]
    public function a_legacy_insert_into_fee_categories_gets_the_member_billing_unit(): void
    {
        $id = DB::table('fee_categories')->insertGetId(['name' => 'Erwachsene']);

        $this->assertSame('member', DB::table('fee_categories')->where('id', $id)->value('billing_unit'));
    }

    #[Test]
    public function new_tables_use_the_collation_of_members(): void
    {
        $expected = $this->tableCollation('members');

        foreach (self::NEW_TABLES as $table) {
            $this->assertSame($expected, $this->tableCollation($table), $table);
        }
    }

    #[Test]
    public function the_schema_checker_reports_no_drift_for_the_household_schema(): void
    {
        $drift = collect(app(DatabaseSchemaChecker::class)->driftReport())
            ->whereIn('table', [...self::NEW_TABLES, 'members', 'fee_categories'])
            ->values()
            ->all();

        $this->assertSame([], $drift);
    }

    /** @return array{int, int, int} household id, fiscal year id, household fee category id */
    private function seedHouseholdYearCategory(): array
    {
        $category = DB::table('fee_categories')->insertGetId(['name' => 'Familie', 'billing_unit' => 'household']);
        $year = DB::table('fiscal_years')->insertGetId(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $household = DB::table('member_households')->insertGetId(['fee_category_id' => $category]);

        return [$household, $year, $category];
    }

    private function insertDue(int $household, int $year, int $category): int
    {
        return DB::table('household_dues')->insertGetId([
            'household_id' => $household, 'fiscal_year_id' => $year, 'fee_category_id' => $category,
            'amount' => '235.00', 'status' => 'ausstehend',
        ]);
    }

    private function insertMember(string $firstname, string $lastname, ?int $household = null): int
    {
        static $number = 0;
        $number++;

        return DB::table('members')->insertGetId([
            'member_number' => 9000 + $number, 'firstname' => $firstname, 'lastname' => $lastname,
            'joined_at' => '2020-01-01', 'status' => 'aktiv', 'household_id' => $household,
        ]);
    }

    private function tableCollation(string $table): string
    {
        return (string) DB::table('information_schema.TABLES')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->value('TABLE_COLLATION');
    }
}
