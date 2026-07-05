<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberChangeLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function creating_a_member_logs_a_created_change(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/members', [
            'member_number' => 1,
            'firstname' => 'Neu',
            'lastname' => 'Mitglied',
            'joined_at' => '2026-01-01',
        ]);

        $member = Member::where('member_number', 1)->firstOrFail();
        $change = MemberChange::where('member_id', $member->id)->firstOrFail();

        $this->assertSame('created', $change->action);
        $this->assertSame($admin->uid, $change->admin_user_id);
        $this->assertNull($change->before);
        $this->assertSame('Neu', $change->after['firstname']);
    }

    #[Test]
    public function updating_a_member_logs_the_before_and_after_snapshot(): void
    {
        $member = Member::factory()->create(['firstname' => 'Alt']);

        $this->actingAs($this->admin())->put("/members/{$member->id}", [
            'member_number' => $member->member_number,
            'firstname' => 'Neu',
            'lastname' => $member->lastname,
            'joined_at' => $member->joined_at->toDateString(),
        ]);

        $change = MemberChange::where('member_id', $member->id)->where('action', 'updated')->firstOrFail();

        $this->assertSame('Alt', $change->before['firstname']);
        $this->assertSame('Neu', $change->after['firstname']);
    }

    #[Test]
    public function updating_a_single_field_inline_logs_an_updated_change(): void
    {
        $member = Member::factory()->create(['firstname' => 'Alt']);

        $this->actingAs($this->admin())->patch("/members/{$member->id}/field", [
            'field' => 'firstname',
            'value' => 'Neu',
        ]);

        $change = MemberChange::where('member_id', $member->id)->where('action', 'updated')->firstOrFail();

        $this->assertSame('Alt', $change->before['firstname']);
        $this->assertSame('Neu', $change->after['firstname']);
    }

    #[Test]
    public function marking_a_member_departed_logs_a_departed_change(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->admin())->delete("/members/{$member->id}");

        $change = MemberChange::where('member_id', $member->id)->where('action', 'departed')->firstOrFail();

        $this->assertNull($change->before['left_at']);
        $this->assertNotNull($change->after['left_at']);
    }

    #[Test]
    public function csv_import_does_not_write_change_log_rows(): void
    {
        $csv = <<<CSV
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Muster,Anna,Vollmitglied,,,,,,,,,,,,,
        CSV;
        $file = sys_get_temp_dir().'/member-change-log-import-test.csv';
        file_put_contents($file, $csv);

        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();

        $this->assertSame(0, MemberChange::count());
    }

    #[Test]
    public function edit_page_shows_change_history(): void
    {
        $admin = $this->admin();
        $member = Member::factory()->create(['firstname' => 'Alt']);

        MemberChange::create([
            'member_id' => $member->id,
            'admin_user_id' => $admin->uid,
            'action' => 'updated',
            'before' => ['firstname' => 'Alt'],
            'after' => ['firstname' => 'Neu'],
        ]);

        $this->actingAs($admin)->get("/members/{$member->id}/edit")
            ->assertOk()
            ->assertSee($admin->alias)
            ->assertSee('Alt')
            ->assertSee('Neu');
    }
}
