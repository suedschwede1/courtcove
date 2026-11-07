<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Support\MemberConnectionException;
use App\Modules\Members\Support\MemberConnectionService;
use App\Services\DatabaseSchemaChecker;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;
use Tests\Support\SecondConnection;

/**
 * Anschlussmitglied on a real MySQL server: the self foreign key (null on delete), no schema drift, and two sessions
 * connecting two Anschlussmitglieder to the same Vollzahler serialise on the member rows, so only one link survives.
 */
#[Group('mysql')]
class MemberConnectionMysqlTest extends MysqlTestCase
{
    private static bool $migrated = false;

    private FeeCategory $full;

    private FeeCategory $partner;

    private ?Connection $second = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        foreach (['household_due_payment', 'household_due_member', 'payment_dues', 'household_dues', 'member_dues', 'payments', 'member_changes'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('members')->update(['household_id' => null, 'connected_to_member_id' => null]);
        DB::table('member_households')->delete();
        foreach (['members', 'fee_category_rates', 'fee_categories'] as $table) {
            DB::table($table)->delete();
        }

        $this->full = FeeCategory::create(['name' => 'Vollzahler', 'connection_role' => 'vollzahler']);
        $this->partner = FeeCategory::create(['name' => 'Ehepartner/in, Lebensgefährte/in', 'connection_role' => 'anschluss']);
    }

    protected function tearDown(): void
    {
        SecondConnection::release();
        $this->second = null;
        parent::tearDown();
    }

    private function member(string $first, FeeCategory $category): Member
    {
        return Member::factory()->create(['firstname' => $first, 'lastname' => 'Muster', 'fee_category_id' => $category->id]);
    }

    #[Test]
    public function deleting_the_vollzahler_row_nulls_the_link(): void
    {
        $heinz = $this->member('Heinz', $this->full);
        $helga = $this->member('Helga', $this->partner);
        app(MemberConnectionService::class)->connect($helga, $heinz);

        DB::table('member_changes')->where('member_id', $heinz->id)->delete();
        DB::table('members')->where('id', $heinz->id)->delete();

        $this->assertNull(DB::table('members')->where('id', $helga->id)->value('connected_to_member_id'));
    }

    #[Test]
    public function the_schema_checker_reports_no_drift_for_members_and_fee_categories(): void
    {
        $drift = collect(app(DatabaseSchemaChecker::class)->driftReport())->whereIn('table', ['members', 'fee_categories'])->values()->all();

        $this->assertSame([], $drift);
    }

    #[Test]
    public function a_second_anschluss_waits_for_a_running_connect_and_is_then_refused(): void
    {
        $heinz = $this->member('Heinz', $this->full);
        $helga = $this->member('Helga', $this->partner);
        $hanna = $this->member('Hanna', $this->partner);
        SecondConnection::waitAtMost(1);

        // The other request: connecting Hanna holds both member rows and has written the link, not committed.
        $this->second = SecondConnection::make();
        $this->second->beginTransaction();
        $this->second->select('SELECT id FROM members WHERE id IN (?, ?) ORDER BY id FOR UPDATE', [$heinz->id, $hanna->id]);
        $this->second->update('UPDATE members SET connected_to_member_id = ? WHERE id = ?', [$heinz->id, $hanna->id]);

        try {
            app(MemberConnectionService::class)->connect($helga, $heinz);
            $this->fail('expected a lock wait timeout');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1]);
        }

        $this->second->commit();
        $this->assertThrows(fn () => app(MemberConnectionService::class)->connect($helga->fresh() ?? $helga, $heinz->fresh() ?? $heinz), MemberConnectionException::class);
        $this->assertSame(1, Member::query()->where('connected_to_member_id', $heinz->id)->count());
    }
}
