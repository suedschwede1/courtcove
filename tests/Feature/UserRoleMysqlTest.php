<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * The additive role column on a legacy-style (utf8mb3) bs_users table: the old
 * system's INSERT without the column keeps working, and the admin user list query
 * (grouped last-booking join) runs under MySQL's ONLY_FULL_GROUP_BY.
 */
#[Group('mysql')]
class UserRoleMysqlTest extends MysqlTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        DB::table('bs_users')->delete();
    }

    #[Test]
    public function the_role_column_is_a_not_null_varchar_with_member_default(): void
    {
        $column = collect(Schema::getColumns('bs_users'))->firstWhere('name', 'role');

        $this->assertNotNull($column);
        $this->assertSame('varchar(16)', $column['type']);
        $this->assertFalse($column['nullable']);
        $this->assertContains((string) $column['default'], ['member', "'member'"]);
    }

    #[Test]
    public function a_legacy_insert_without_the_role_column_gets_the_default(): void
    {
        DB::table('bs_users')->insert(['alias' => 'Legacy', 'status' => 'admin', 'email' => 'legacy@example.com', 'created' => now()]);

        $this->assertSame('member', DB::table('bs_users')->where('email', 'legacy@example.com')->value('role'));
    }

    #[Test]
    public function the_migration_is_idempotent_on_a_table_that_already_has_the_column(): void
    {
        $migration = require base_path('database/migrations/2026_11_05_000001_add_role_to_bs_users_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('bs_users', 'role'));
    }

    #[Test]
    public function the_user_list_with_last_booking_runs_on_mysql(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $bid = DB::table('bs_bookings')->insertGetId([
            'uid' => $admin->uid, 'sid' => 1, 'status' => 'single', 'status_billing' => 'pending', 'visibility' => 'public', 'quantity' => 2, 'created' => now(),
        ]);
        DB::table('bs_reservations')->insert(['bid' => $bid, 'date' => '2026-09-14', 'time_start' => '10:00:00', 'time_end' => '11:00:00']);

        $this->actingAs($admin)->get('/admin/users?q=')->assertOk()->assertSee('14.09.2026');
    }

    #[Test]
    public function the_last_booking_filter_and_its_page_count_run_on_mysql(): void
    {
        $admin = User::factory()->create(['status' => 'admin', 'alias' => 'Zed Admin']);
        $booked = User::factory()->create(['status' => 'enabled', 'alias' => 'Ben Booked']);
        User::factory()->create(['status' => 'placeholder', 'alias' => 'Pia Placeholder']);
        $bid = DB::table('bs_bookings')->insertGetId([
            'uid' => $booked->uid, 'sid' => 1, 'status' => 'single', 'status_billing' => 'pending', 'visibility' => 'public', 'quantity' => 2, 'created' => now(),
        ]);
        DB::table('bs_reservations')->insert(['bid' => $bid, 'date' => '2026-06-15', 'time_start' => '10:00:00', 'time_end' => '11:00:00']);

        // Inclusive bounds against the DATE column, the paginator's count over the grouped join.
        // (The signed-in admin's name is always in the shell, so the counts tell who is listed.)
        $count = fn (int $n): string => __('booking.admin.users.results_count', ['count' => $n]);
        $this->actingAs($admin)->get('/admin/users?from=2026-06-15&to=2026-06-15&role=member')->assertOk()
            ->assertSee('Ben Booked')->assertSee($count(1));
        $this->actingAs($admin)->get('/admin/users?from=2026-06-16')->assertOk()
            ->assertDontSee('Ben Booked')->assertSee(__('booking.admin.no_results'));
        $this->actingAs($admin)->get('/admin/users?no_booking=1')->assertOk()
            ->assertDontSee('Ben Booked')->assertDontSee('Pia Placeholder')->assertSee($count(1));
        $this->actingAs($admin)->get('/admin/users?no_booking=1&placeholders=1')->assertOk()
            ->assertSee('Pia Placeholder')->assertSee($count(2));
    }
}
