<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tab "Protokoll": every change to members, newest first. Personal data never shows up as old or new
 * values here, and anonymised members keep only their anonymised name (GDPR).
 */
class MemberChangesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin', 'alias' => 'Pruefer Eins']);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function change(Member $member, string $action, ?array $before, array $after, string $at, ?User $by = null): MemberChange
    {
        $change = MemberChange::create([
            'member_id' => $member->id,
            'admin_user_id' => $by?->uid,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
        $change->forceFill(['created_at' => Carbon::parse($at)])->save();

        return $change;
    }

    #[Test]
    public function a_non_admin_gets_403(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->get(route('members.changes.index'))
            ->assertForbidden();
    }

    #[Test]
    public function changes_are_listed_newest_first_with_member_who_and_when(): void
    {
        $admin = $this->admin();
        $older = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Frueher']);
        $newer = Member::factory()->create(['firstname' => 'Bert', 'lastname' => 'Spaeter', 'status' => 'ruhend']);
        $this->change($older, 'created', null, ['firstname' => 'Anna'], '2026-10-01 09:00', $admin);
        $this->change($newer, 'updated', ['status' => 'aktiv'], ['status' => 'ruhend'], '2026-10-20 18:30', $admin);

        $response = $this->actingAs($admin)->get(route('members.changes.index'))->assertOk();

        $response->assertSeeInOrder([
            'Spaeter, Bert', 'Status: Aktiv → Ruhend', '20.10.2026 18:30', 'Pruefer Eins',
            'Frueher, Anna', 'Angelegt', '01.10.2026 09:00',
        ]);
        $response->assertSee('href="'.route('members.edit', $newer).'"', false);
    }

    #[Test]
    public function personal_fields_show_only_that_they_changed(): void
    {
        $admin = $this->admin();
        $member = Member::factory()->create(['firstname' => 'Clara', 'lastname' => 'Neu', 'phone' => '0699 222']);
        $this->change($member, 'updated', ['phone' => '0699 111', 'email' => 'alt@example.com'], ['phone' => '0699 222', 'email' => 'neu@example.com'], '2026-10-02 10:00', $admin);

        $html = (string) $this->actingAs($admin)->get(route('members.changes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Telefon geändert', $html);
        $this->assertStringContainsString('E-Mail geändert', $html);
        foreach (['0699 111', '0699 222', 'alt@example.com', 'neu@example.com'] as $value) {
            $this->assertStringNotContainsString($value, $html);
        }
    }

    #[Test]
    public function an_anonymised_member_shows_no_old_personal_data(): void
    {
        $admin = $this->admin();
        $member = Member::factory()->create([
            'member_number' => 77,
            'firstname' => 'Ehemaliges',
            'lastname' => 'Mitglied #77',
            'email' => null,
            'anonymized_at' => '2026-10-05 12:00:00',
        ]);
        $this->change($member, 'updated', ['firstname' => 'Doris', 'city' => 'Altstadt'], ['firstname' => 'Dora', 'city' => 'Neustadt'], '2026-09-01 08:00', $admin);
        $this->change($member, 'anonymized',
            ['firstname' => 'Dora', 'lastname' => 'Vorher', 'email' => 'dora@example.com', 'street' => 'Gasse 1'],
            ['firstname' => 'Ehemaliges', 'lastname' => 'Mitglied #77', 'email' => null, 'street' => null],
            '2026-10-05 12:00', $admin);

        $html = (string) $this->actingAs($admin)->get(route('members.changes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Mitglied #77, Ehemaliges', $html);
        $this->assertStringContainsString(e(__('members::admin.history.anonymized')), $html);
        foreach (['Doris', 'Dora', 'Vorher', 'dora@example.com', 'Gasse 1', 'Altstadt', 'Neustadt'] as $old) {
            $this->assertStringNotContainsString($old, $html);
        }

        // The detail page of an anonymised member hides the old values too.
        $html = (string) $this->actingAs($admin)->get(route('members.edit', $member))->assertOk()->getContent();
        foreach (['Doris', 'Dora', 'Vorher', 'dora@example.com', 'Gasse 1', 'Altstadt', 'Neustadt'] as $old) {
            $this->assertStringNotContainsString($old, $html);
        }
    }

    #[Test]
    public function the_list_filters_by_member_name_and_period(): void
    {
        $admin = $this->admin();
        $one = Member::factory()->create(['firstname' => 'Emil', 'lastname' => 'Gesucht']);
        $two = Member::factory()->create(['firstname' => 'Fritz', 'lastname' => 'Anders']);
        $this->change($one, 'created', null, [], '2026-08-15 10:00', $admin);
        $this->change($two, 'created', null, [], '2026-10-15 10:00', $admin);

        $this->actingAs($admin)->get(route('members.changes.index', ['search' => 'gesucht']))
            ->assertOk()->assertSee('Gesucht, Emil')->assertDontSee('Anders, Fritz');

        $this->actingAs($admin)->get(route('members.changes.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSee('Anders, Fritz')->assertDontSee('Gesucht, Emil');

        $this->actingAs($admin)->get(route('members.changes.index', ['from' => 'kein-datum']))
            ->assertSessionHasErrors('from');
    }

    #[Test]
    public function the_page_is_paged(): void
    {
        $admin = $this->admin();
        $member = Member::factory()->create();
        for ($i = 0; $i < 35; $i++) {
            $this->change($member, 'updated', ['status' => 'aktiv'], ['status' => 'ruhend'], '2026-10-01 10:00', $admin);
        }

        $html = (string) $this->actingAs($admin)->get(route('members.changes.index'))->assertOk()->getContent();

        $this->assertSame(30, substr_count($html, 'class="mbr-change"'));
        $this->assertStringContainsString('page=2', $html);
    }
}
