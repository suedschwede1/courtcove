<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\MemberConnectionException;
use App\Modules\Members\Support\MemberConnectionService;
use App\Modules\Members\Support\MemberConnectionWarnings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decisions on Anschlussmitglieder (2026-11-08):
 * 1. the due is the rate of the Anschlussmitglied's own category;
 * 2. no category checks: any current member can be the Vollzahler (Pensionisten too), any current member the
 *    Anschlussmitglied; only integrity rules stay (no self link, no chain/cycle, one partner, no Familientarif);
 * 3. the Vollzahler departs: the link is removed, logged on both, shown on the member page, categories unchanged;
 * 4. "Rollen tauschen": one transaction, logged on both, categories and open dues unchanged (only reported);
 * 5. old data with two Anschlussmitglieder: reported only; departure removes both links, swapping is refused.
 */
class MemberConnectionDecisionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $full;

    private FeeCategory $partner;

    private FeeCategory $pension;

    private FeeCategory $youth;

    private MemberConnectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-08 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->full = FeeCategory::create(['name' => 'Vollzahler', 'connection_role' => 'vollzahler']);
        $this->partner = FeeCategory::create(['name' => 'Ehepartner/in, Lebensgefährte/in', 'connection_role' => 'anschluss']);
        $this->pension = FeeCategory::create(['name' => 'Pensionisten']);
        $this->youth = FeeCategory::create(['name' => 'Jugend']);
        foreach ([[$this->full, 180], [$this->partner, 90], [$this->pension, 150], [$this->youth, 60]] as [$category, $amount]) {
            FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $this->year->id, 'amount' => $amount]);
        }
        $this->service = app(MemberConnectionService::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function member(string $first, FeeCategory $category, array $extra = []): Member
    {
        return Member::factory()->create([...['firstname' => $first, 'lastname' => 'Muster', 'fee_category_id' => $category->id], ...$extra]);
    }

    private function openDue(Member $member, float $amount): MemberDue
    {
        return MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $member->fee_category_id, 'amount' => $amount, 'status' => 'ausstehend']);
    }

    // 1 + 2 -----------------------------------------------------------------------------------------------------

    #[Test]
    public function a_pensioner_can_be_the_vollzahler_and_each_pays_the_own_category(): void
    {
        $paul = $this->member('Paul', $this->pension);
        $helga = $this->member('Helga', $this->partner);

        $this->service->connect($helga, $paul);

        $this->assertSame($paul->id, $helga->fresh()?->connected_to_member_id);
        $this->assertSame([], MemberConnectionWarnings::forMember($helga->fresh() ?? $helga));
        $this->assertSame([], MemberConnectionWarnings::forMember($paul->fresh() ?? $paul));

        $this->actingAs($this->admin())->post('/members/dues/generate', ['fiscal_year_id' => $this->year->id])
            ->assertSessionHas('dues_report', fn (array $r) => $r['skipped']['connection_warning'] === []);
        $this->assertSame('150.00', MemberDue::where('member_id', $paul->id)->sole()->amount);
        $this->assertSame('90.00', MemberDue::where('member_id', $helga->id)->sole()->amount, 'the Anschlussmitglied pays its own category rate');
    }

    #[Test]
    public function no_category_or_role_is_checked_any_more(): void
    {
        $kid = $this->member('Kim', $this->youth);
        $noCategory = $this->member('Nora', $this->youth, ['fee_category_id' => null]);

        $this->service->connect($kid, $noCategory);

        $this->assertSame($noCategory->id, $kid->fresh()?->connected_to_member_id);
        $this->assertSame([], MemberConnectionWarnings::forMember($kid->fresh() ?? $kid), 'no category warning on a connected member');
        $this->assertSame([], MemberConnectionWarnings::forMember($noCategory->fresh() ?? $noCategory));
    }

    #[Test]
    public function the_integrity_rules_stay(): void
    {
        $heinz = $this->member('Heinz', $this->full);
        $helga = $this->member('Helga', $this->partner);
        $this->service->connect($helga, $heinz);

        // self link, a second Anschlussmitglied, a chain in both directions, a departed member
        $this->assertThrows(fn () => $this->service->connect($heinz->fresh() ?? $heinz, $heinz->fresh() ?? $heinz), MemberConnectionException::class);
        $this->assertThrows(fn () => $this->service->connect($this->member('Hanna', $this->partner), $heinz->fresh() ?? $heinz), MemberConnectionException::class);
        $this->assertThrows(fn () => $this->service->connect($this->member('Ida', $this->partner), $helga->fresh() ?? $helga), MemberConnectionException::class);
        $this->assertThrows(fn () => $this->service->connect($heinz->fresh() ?? $heinz, $this->member('Otto', $this->full)), MemberConnectionException::class);
        $this->assertThrows(fn () => $this->service->connect($this->member('Uta', $this->partner), $this->member('Gone', $this->full, ['status' => 'ausgetreten', 'left_at' => '2025-12-31'])), MemberConnectionException::class);

        // Familientarif and a connection still exclude each other
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $inFamily = $this->member('Fritz', $this->full);
        app(HouseholdService::class)->create([$inFamily->id], $family->id, null);
        $this->assertThrows(fn () => $this->service->connect($this->member('Vera', $this->partner), $inFamily->fresh() ?? $inFamily), MemberConnectionException::class);

        $this->assertSame(1, Member::whereNotNull('connected_to_member_id')->count());
    }

    #[Test]
    public function the_unconnected_anschluss_hint_stays_as_information_and_the_page_offers_every_current_member(): void
    {
        $helga = $this->member('Helga', $this->partner);
        $paul = $this->member('Paul', $this->pension);
        $kim = $this->member('Kim', $this->youth);

        $this->assertSame([__('members::admin.connection.warnings.anschluss_without_connection')], MemberConnectionWarnings::forMember($helga));

        // Any member without a link gets the select, with every current unconnected member (category shown).
        $this->actingAs($this->admin())->get("/members/{$kim->id}/edit")
            ->assertOk()
            ->assertSee('<option value="'.$paul->id.'">', false)
            ->assertSee('<option value="'.$helga->id.'">', false)
            ->assertSeeText('Pensionisten')
            ->assertSeeText(__('members::admin.connection.connect'));
        $this->actingAs($this->admin())->post("/members/{$kim->id}/connection", ['vollzahler_id' => $paul->id])->assertSessionHasNoErrors();
        $this->assertSame($paul->id, $kim->fresh()?->connected_to_member_id);
    }

    #[Test]
    public function a_category_role_can_change_while_members_are_connected(): void
    {
        $this->service->connect($this->member('Helga', $this->partner), $this->member('Heinz', $this->full));

        $this->actingAs($this->admin())->put("/members/fee-categories/{$this->partner->id}", ['name' => $this->partner->name, 'connection_role' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->partner->fresh()?->connection_role);
    }

    // 3 ---------------------------------------------------------------------------------------------------------

    #[Test]
    public function the_vollzahler_departing_on_the_form_removes_the_link_and_shows_it(): void
    {
        $admin = $this->admin();
        $heinz = $this->member('Heinz', $this->full);
        $helga = $this->member('Helga', $this->partner);
        $this->service->connect($helga, $heinz);
        $due = $this->openDue($helga, 90);

        $this->actingAs($admin)->put("/members/{$heinz->id}", [
            'member_number' => $heinz->member_number, 'firstname' => 'Heinz', 'lastname' => 'Muster', 'joined_at' => '2010-01-01', 'left_at' => '2026-11-08', 'fee_category_id' => $this->full->id,
        ])->assertSessionHasNoErrors();

        $helga = $helga->fresh() ?? $helga;
        $this->assertNull($helga->connected_to_member_id);
        $this->assertSame($this->partner->id, $helga->fee_category_id, 'no automatic category change');
        $this->assertSame(['ausstehend', '90.00'], [$due->fresh()?->status, $due->fresh()?->amount], 'open dues untouched');
        $this->assertSame(1, MemberChange::where('member_id', $helga->id)->where('action', MemberConnectionService::ENDED_ACTION)->count());
        $this->assertSame(2, MemberChange::where('member_id', $heinz->id)->where('action', 'connection')->count(), 'connect + end on the Vollzahler');

        $notice = __('members::admin.connection.ended_notice', ['name' => 'Heinz Muster', 'date' => '08.11.2026']);
        $this->actingAs($admin)->get("/members/{$helga->id}/edit")
            ->assertOk()
            ->assertSeeText($notice)
            ->assertSeeText(__('members::admin.connection.history_ended', ['name' => 'Heinz Muster']));
        $this->assertSame([__('members::admin.connection.warnings.anschluss_without_connection')], MemberConnectionWarnings::forMember($helga));

        // Connected again later: the notice is gone.
        $this->service->connect($helga, $this->member('Otto', $this->full));
        $this->actingAs($admin)->get("/members/{$helga->id}/edit")->assertDontSeeText($notice);
    }

    #[Test]
    public function the_vollzahler_departing_inline_removes_every_link_including_old_double_ones(): void
    {
        $heinz = $this->member('Heinz', $this->full);
        $partners = collect(['Helga', 'Hanna'])->map(fn (string $first) => tap($this->member($first, $this->partner), fn (Member $m) => $m->forceFill(['connected_to_member_id' => $heinz->id])->save()));

        $this->actingAs($this->admin())->patchJson("/members/{$heinz->id}/field", ['field' => 'left_at', 'value' => '2026-11-08'])->assertOk();

        $this->assertSame(0, Member::whereNotNull('connected_to_member_id')->count());
        foreach ($partners as $partner) {
            $this->assertSame(1, MemberChange::where('member_id', $partner->id)->where('action', MemberConnectionService::ENDED_ACTION)->count());
        }
    }

    #[Test]
    public function an_anschluss_member_departing_keeps_its_own_link_reported(): void
    {
        $heinz = $this->member('Heinz', $this->full);
        $helga = $this->member('Helga', $this->partner);
        $this->service->connect($helga, $heinz);

        $this->actingAs($this->admin())->put("/members/{$helga->id}", [
            'member_number' => $helga->member_number, 'firstname' => 'Helga', 'lastname' => 'Muster', 'joined_at' => '2010-01-01', 'left_at' => '2026-11-08', 'fee_category_id' => $this->partner->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($heinz->id, $helga->fresh()?->connected_to_member_id, 'only the Vollzahler departing ends the link');
        $this->assertContains(__('members::admin.connection.warnings.anschluss_departed', ['name' => 'Helga Muster']), MemberConnectionWarnings::forMember($heinz->fresh() ?? $heinz));
        $this->actingAs($this->admin())->get("/members/{$heinz->id}/edit")->assertDontSee('id="connection-swap"', false);
    }

    #[Test]
    public function the_ended_notice_goes_once_the_member_gets_an_anschluss_of_its_own(): void
    {
        $paul = $this->member('Paul', $this->pension);
        $petra = $this->member('Petra', $this->partner);
        $this->service->connect($petra, $paul);
        $paul->setLeftAt('2026-11-08');
        $this->service->memberDeparted($paul->fresh() ?? $paul);
        $this->assertNotNull(MemberConnectionWarnings::endedNotice($petra->fresh() ?? $petra));

        $this->service->connect($this->member('Carl', $this->youth), $petra->fresh() ?? $petra);

        $this->assertNull(MemberConnectionWarnings::endedNotice($petra->fresh() ?? $petra));
    }

    // 4 ---------------------------------------------------------------------------------------------------------

    #[Test]
    public function swapping_roles_from_either_side_keeps_categories_and_dues(): void
    {
        $admin = $this->admin();
        $heinz = $this->member('Heinz', $this->full);
        $helga = $this->member('Helga', $this->partner);
        $this->service->connect($helga, $heinz);
        $dues = [$this->openDue($heinz, 180), $this->openDue($helga, 90)];

        $this->actingAs($admin)->get("/members/{$heinz->id}/edit")
            ->assertSee('id="connection-swap"', false)
            ->assertSeeText(__('members::admin.connection.swap'));

        $this->actingAs($admin)->post("/members/{$heinz->id}/connection/swap")
            ->assertRedirect(route('members.edit', $heinz))
            ->assertSessionHas('status', __('members::admin.connection.swapped', ['open' => 2]));

        $this->assertSame($helga->id, $heinz->fresh()?->connected_to_member_id);
        $this->assertNull($helga->fresh()?->connected_to_member_id);
        $this->assertSame([$this->full->id, $this->partner->id], [$heinz->fresh()?->fee_category_id, $helga->fresh()?->fee_category_id]);
        foreach ($dues as $due) {
            $this->assertSame('ausstehend', $due->fresh()?->status);
        }
        foreach ([$heinz, $helga] as $member) {
            $this->assertSame(1, MemberChange::where('member_id', $member->id)->where('action', MemberConnectionService::SWAP_ACTION)->count());
        }
        $this->actingAs($admin)->get("/members/{$helga->id}/edit")
            ->assertSeeText(__('members::admin.connection.history_swapped_vollzahler', ['name' => 'Heinz Muster']));

        // And back, from the other side.
        $this->actingAs($admin)->post("/members/{$helga->id}/connection/swap")->assertSessionHasNoErrors();
        $this->assertSame($heinz->id, $helga->fresh()?->connected_to_member_id);
        $this->assertNull($heinz->fresh()?->connected_to_member_id);
    }

    #[Test]
    public function swapping_is_refused_without_exactly_one_partner_and_needs_the_members_right(): void
    {
        $admin = $this->admin();
        $alone = $this->member('Alois', $this->full);
        $this->actingAs($admin)->post("/members/{$alone->id}/connection/swap")->assertSessionHasErrors('connection');

        $heinz = $this->member('Heinz', $this->full);
        foreach (['Helga', 'Hanna'] as $first) {
            $this->member($first, $this->partner)->forceFill(['connected_to_member_id' => $heinz->id])->save();
        }
        $this->actingAs($admin)->post("/members/{$heinz->id}/connection/swap")->assertSessionHasErrors('connection');
        $this->assertNull($heinz->fresh()?->connected_to_member_id);
        $this->assertSame(2, Member::where('connected_to_member_id', $heinz->id)->count());

        $user = User::factory()->create(['status' => 'enabled']);
        $this->actingAs($user)->post("/members/{$heinz->id}/connection/swap")->assertForbidden();
        $this->actingAs($admin)->post('/members/999999/connection/swap')->assertNotFound();
        $this->post('/logout');
        $this->post("/members/{$heinz->id}/connection/swap")->assertRedirect('/login');
    }

    // 5 ---------------------------------------------------------------------------------------------------------

    #[Test]
    public function two_anschluss_members_from_old_data_stay_reported_only(): void
    {
        $heinz = $this->member('Heinz', $this->full);
        foreach (['Helga', 'Hanna'] as $first) {
            $this->member($first, $this->partner)->forceFill(['connected_to_member_id' => $heinz->id])->save();
        }

        $this->assertContains(__('members::admin.connection.warnings.too_many_anschluss', ['names' => 'Hanna Muster, Helga Muster']), MemberConnectionWarnings::forMember($heinz->fresh() ?? $heinz));
        $this->assertSame(2, Member::where('connected_to_member_id', $heinz->id)->count(), 'nothing is changed automatically');
    }
}
