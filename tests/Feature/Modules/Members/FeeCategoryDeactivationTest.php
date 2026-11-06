<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decision: fee categories (Beitragskategorien) are deactivated, never deleted. A deactivated
 * category keeps its dues, rates and members, but cannot be chosen for anything new.
 */
class FeeCategoryDeactivationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FeeCategory $active;

    private FeeCategory $old;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->active = FeeCategory::create(['name' => 'Erwachsene']);
        $this->old = FeeCategory::create(['name' => 'Altkategorie']);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        foreach ([$this->active, $this->old] as $category) {
            FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $this->year->id, 'amount' => 100]);
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function deactivateOld(): void
    {
        $this->old->forceFill(['deactivated_at' => now()])->save();
    }

    #[Test]
    public function the_migration_adds_deactivated_at_and_can_run_twice(): void
    {
        $this->assertTrue(Schema::hasColumn('fee_categories', 'deactivated_at'));

        $migration = require base_path('app/Modules/Members/Database/Migrations/2026_11_06_090003_add_deactivated_at_to_fee_categories_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('fee_categories', 'deactivated_at'));
        $this->assertFalse($this->active->fresh()->isDeactivated());
    }

    #[Test]
    public function a_category_is_deactivated_and_reactivated_and_nothing_else_changes(): void
    {
        $member = Member::factory()->create(['fee_category_id' => $this->old->id]);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->old->id, 'amount' => 100, 'status' => 'bezahlt']);
        $payment = Payment::create(['member_id' => $member->id, 'amount' => 100, 'paid_at' => '2026-02-01']);
        $due->payments()->attach($payment);
        $this->actingAs($this->admin());

        $this->post("/members/fee-categories/{$this->old->id}/deactivate")
            ->assertRedirect()
            ->assertSessionHas('status', 'Beitragskategorie „Altkategorie“ deaktiviert.');
        $this->assertTrue($this->old->fresh()->isDeactivated());

        $this->assertSame($this->old->id, $member->fresh()->fee_category_id);
        $this->assertSame($this->old->id, $due->fresh()->fee_category_id);
        $this->assertSame('bezahlt', $due->fresh()->status);
        $this->assertSame(1, $due->fresh()->payments()->count());
        $this->assertSame('100.00', FeeCategoryRate::where('fee_category_id', $this->old->id)->value('amount'));
        $this->assertSame(2, FeeCategory::count());

        $this->post("/members/fee-categories/{$this->old->id}/reactivate")->assertSessionHas('status');
        $this->assertFalse($this->old->fresh()->isDeactivated());
    }

    #[Test]
    public function the_list_and_edit_page_show_the_state_and_the_matching_action(): void
    {
        $this->deactivateOld();
        $this->actingAs($this->admin());

        $html = $this->get('/members/fee-categories')->assertOk()->getContent();
        $this->assertStringContainsString('Deaktiviert', $html);
        $this->assertStringContainsString('action="'.route('members.fee-categories.reactivate', $this->old->id).'"', $html);
        $this->assertStringContainsString('action="'.route('members.fee-categories.deactivate', $this->active->id).'"', $html);

        $this->get("/members/fee-categories/{$this->old->id}/edit")->assertOk()->assertSee('Reaktivieren');
        $this->withUnencryptedCookie('locale', 'en')->get('/members/fee-categories')->assertOk()->assertSee('Deactivated')->assertSee('Reactivate');
    }

    #[Test]
    public function a_closed_year_does_not_block_deactivation(): void
    {
        $this->year->forceFill(['abgeschlossen_am' => now()])->save();

        $this->actingAs($this->admin())->post("/members/fee-categories/{$this->old->id}/deactivate")->assertSessionHasNoErrors();

        $this->assertTrue($this->old->fresh()->isDeactivated());
    }

    #[Test]
    public function only_users_with_the_members_privilege_may_toggle_and_ids_are_safe(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $this->post("/members/fee-categories/{$this->old->id}/deactivate")->assertForbidden();
        $this->post('/members/fee-categories/999999/deactivate')->assertForbidden();
        $this->assertFalse($this->old->fresh()->isDeactivated());

        $this->actingAs($this->admin());
        $this->post('/members/fee-categories/999999/deactivate')->assertNotFound();
        $this->post('/members/fee-categories/abc/deactivate')->assertNotFound();
        $this->post("/members/fee-categories/{$this->old->id}/deactivate", ['x' => ['y']])->assertRedirect();
    }

    #[Test]
    public function the_manual_due_form_offers_only_active_categories_and_refuses_a_deactivated_one(): void
    {
        $this->deactivateOld();
        $member = Member::factory()->create();
        $this->actingAs($this->admin());

        $html = $this->get("/members/dues?fiscal_year_id={$this->year->id}")->assertOk()->getContent();
        preg_match('/<select name="fee_category_id".*?<\/select>/s', $html, $m);
        $this->assertStringContainsString('>Erwachsene</option>', $m[0] ?? '');
        $this->assertStringNotContainsString('Altkategorie', $m[0] ?? '');

        $this->post('/members/dues', ['member_id' => $member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->old->id])
            ->assertSessionHasErrors('fee_category_id');
        $this->post('/members/dues', ['member_id' => $member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => [$this->active->id]])
            ->assertSessionHasErrors('fee_category_id');
        $this->assertSame(0, MemberDue::count());
    }

    #[Test]
    public function generation_skips_members_of_a_deactivated_category_and_says_so(): void
    {
        $next = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        foreach ([$this->active, $this->old] as $category) {
            FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $next->id, 'amount' => 110]);
        }
        $keep = Member::factory()->create(['fee_category_id' => $this->active->id]);
        $skip = Member::factory()->create(['fee_category_id' => $this->old->id]);
        foreach ([[$keep, $this->active], [$skip, $this->old]] as [$member, $category]) {
            MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $category->id, 'amount' => 100]);
        }
        $this->deactivateOld();

        $this->actingAs($this->admin())
            ->post('/members/dues/generate', ['fiscal_year_id' => $next->id])
            ->assertSessionHas('status', '1 Beitrag/Beiträge erzeugt. 1 Mitglied(er) übersprungen, weil die Beitragskategorie deaktiviert ist.');

        $this->assertSame(1, MemberDue::where('fiscal_year_id', $next->id)->where('member_id', $keep->id)->count());
        $this->assertSame(0, MemberDue::where('fiscal_year_id', $next->id)->where('member_id', $skip->id)->count());
    }

    #[Test]
    public function a_new_member_cannot_get_a_deactivated_category(): void
    {
        $this->deactivateOld();
        $this->actingAs($this->admin());

        $html = $this->get('/members/create')->assertOk()->getContent();
        preg_match('/<select name="fee_category_id".*?<\/select>/s', $html, $m);
        $this->assertStringNotContainsString('Altkategorie', $m[0] ?? '');

        $this->post('/members', [
            'member_number' => 77, 'firstname' => 'Neu', 'lastname' => 'Beispiel', 'joined_at' => '2026-01-01',
            'fee_category_id' => $this->old->id,
        ])->assertSessionHasErrors('fee_category_id');
        $this->assertSame(0, Member::where('member_number', 77)->count());
    }

    #[Test]
    public function an_existing_member_keeps_and_shows_a_deactivated_category_but_cannot_newly_get_one(): void
    {
        $this->deactivateOld();
        $kept = Member::factory()->create(['fee_category_id' => $this->old->id]);
        $other = Member::factory()->create(['fee_category_id' => $this->active->id]);
        $this->actingAs($this->admin());

        // Edit page of the member in the deactivated category: it is listed as the current value, marked.
        $html = $this->get("/members/{$kept->id}/edit")->assertOk()->getContent();
        preg_match('/<select name="fee_category_id".*?<\/select>/s', $html, $m);
        $this->assertStringContainsString('<option value="'.$this->old->id.'" selected>Altkategorie (deaktiviert)</option>', $m[0] ?? '');

        // Edit page of another member: the deactivated category is not offered.
        $html = $this->get("/members/{$other->id}/edit")->assertOk()->getContent();
        preg_match('/<select name="fee_category_id".*?<\/select>/s', $html, $m);
        $this->assertStringNotContainsString('Altkategorie', $m[0] ?? '');

        $form = fn (Member $member, int $categoryId) => [
            'member_number' => $member->member_number, 'firstname' => $member->firstname, 'lastname' => $member->lastname,
            'joined_at' => $member->joined_at->toDateString(), 'fee_category_id' => $categoryId,
        ];
        $this->put("/members/{$kept->id}", $form($kept, $this->old->id))->assertSessionHasNoErrors();
        $this->assertSame($this->old->id, $kept->fresh()->fee_category_id);

        $this->put("/members/{$other->id}", $form($other, $this->old->id))->assertSessionHasErrors('fee_category_id');
        $this->assertSame($this->active->id, $other->fresh()->fee_category_id);

        // Inline edit in the members list follows the same rule.
        $this->patchJson("/members/{$other->id}/field", ['field' => 'fee_category_id', 'value' => $this->old->id])->assertStatus(422);
        $this->patchJson("/members/{$kept->id}/field", ['field' => 'fee_category_id', 'value' => $this->old->id])->assertOk();
        $this->patchJson("/members/{$kept->id}/field", ['field' => 'fee_category_id', 'value' => [$this->old->id]])->assertStatus(422);
        $this->assertSame($this->active->id, $other->fresh()->fee_category_id);
    }

    #[Test]
    public function the_members_list_marks_deactivated_categories_in_the_inline_select(): void
    {
        $this->deactivateOld();
        Member::factory()->create(['fee_category_id' => $this->old->id]);

        $this->actingAs($this->admin())->get('/members')
            ->assertOk()
            ->assertSee('<option value="'.$this->old->id.'" disabled>Altkategorie (deaktiviert)</option>', false);
    }

    #[Test]
    public function the_category_list_does_not_query_per_row(): void
    {
        $admin = $this->admin();
        $count = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get('/members/fee-categories')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $few = $count();
        for ($i = 0; $i < 5; $i++) {
            FeeCategory::create(['name' => 'Kategorie '.$i])->forceFill(['deactivated_at' => $i % 2 ? now() : null])->save();
        }

        $this->assertSame($few, $count());
    }
}
