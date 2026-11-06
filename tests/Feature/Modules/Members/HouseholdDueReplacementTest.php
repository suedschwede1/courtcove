<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Models\Payment;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Durch Haushaltsbeitrag ersetzen" (decision 1): a preview first, then on confirmation one transaction
 * deletes only unpaid own dues (no payment, no partial amount) and covers those members; paid or partly
 * paid own dues are never touched.
 */
class HouseholdDueReplacementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $family;

    private FeeCategory $adults;

    private MemberHousehold $h;

    private Member $anna;

    private Member $ben;

    private Member $clara;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $this->adults = FeeCategory::create(['name' => 'Erwachsene']);
        FeeCategoryRate::create(['fee_category_id' => $this->family->id, 'fiscal_year_id' => $this->year->id, 'amount' => 235]);
        $this->anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $this->ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        $this->clara = Member::factory()->create(['firstname' => 'Clara', 'lastname' => 'Muster']);
        $this->h = app(HouseholdService::class)->create([$this->anna->id, $this->ben->id, $this->clara->id], $this->family->id, 'Familie Muster');
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function own(Member $member, array $extra = []): MemberDue
    {
        return MemberDue::create([...['member_id' => $member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150, 'status' => 'ausstehend'], ...$extra]);
    }

    private function replace(array $payload = []): TestResponse
    {
        return $this->actingAs($this->admin())->post("/members/households/{$this->h->id}/replace-dues", [...['fiscal_year_id' => $this->year->id, 'confirm' => 1], ...$payload]);
    }

    #[Test]
    public function the_household_page_hints_at_own_dues_and_links_the_preview(): void
    {
        $this->own($this->anna);
        $this->own($this->ben, ['status' => 'bezahlt']);

        $this->actingAs($this->admin())->get("/members/households/{$this->h->id}")
            ->assertOk()
            ->assertSeeText(__('members::admin.households.replace.hint_replaceable', ['name' => 'Anna Muster', 'year' => '2026', 'amount' => '150,00 €']))
            ->assertSeeText(__('members::admin.households.replace.hint_paid', ['name' => 'Ben Muster', 'year' => '2026', 'amount' => '150,00 €']))
            ->assertSee(route('members.households.replace-dues.preview', ['household' => $this->h->id, 'fiscal_year_id' => $this->year->id]), false);
    }

    #[Test]
    public function the_preview_lists_what_will_be_replaced_and_what_stays(): void
    {
        $this->own($this->anna);
        $this->own($this->ben, ['teilbetrag' => 50]);

        $this->actingAs($this->admin())->get("/members/households/{$this->h->id}/replace-dues?fiscal_year_id={$this->year->id}")
            ->assertOk()
            ->assertSeeText('Anna Muster')
            ->assertSeeText('235,00 €')
            ->assertSeeText(__('members::admin.households.replace.untouched_hint'))
            ->assertSeeText(__('members::admin.households.replace.confirm'));

        $this->assertSame(2, MemberDue::count());
        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function confirming_replaces_only_unpaid_dues_and_covers_those_members(): void
    {
        $this->own($this->anna);

        $this->replace()->assertRedirect(route('members.households.show', $this->h->id))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('member_dues', ['member_id' => $this->anna->id, 'fiscal_year_id' => $this->year->id]);
        $due = HouseholdDue::sole();
        $this->assertSame('235.00', $due->amount);
        $this->assertTrue($due->coveredMembers()->whereKey($this->anna->id)->exists());
        $this->assertSame(3, $due->coveredMembers()->count());
    }

    #[Test]
    public function an_existing_household_due_is_reused(): void
    {
        $existing = HouseholdDue::create(['household_id' => $this->h->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 200]);
        $existing->coveredMembers()->attach([$this->ben->id => ['fiscal_year_id' => $this->year->id], $this->clara->id => ['fiscal_year_id' => $this->year->id]]);
        $this->own($this->anna);

        $this->replace()->assertSessionHasNoErrors();

        $this->assertSame(1, HouseholdDue::count());
        $this->assertSame('200.00', $existing->fresh()?->amount);
        $this->assertSame(3, $existing->coveredMembers()->count());
        $this->assertSame(0, MemberDue::count());
    }

    #[Test]
    public function replace_never_touches_paid_or_partly_paid_dues(): void
    {
        $dora = Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Beispiel']);
        $emil = Member::factory()->create(['firstname' => 'Emil', 'lastname' => 'Probe']);
        app(HouseholdService::class)->addMember($this->h, $dora);
        app(HouseholdService::class)->addMember($this->h, $emil);
        $this->own($this->anna);
        $this->own($this->ben, ['teilbetrag' => 50]);
        $this->own($dora, ['status' => 'bezahlt']);
        $emilDue = $this->own($emil);
        $payment = Payment::create(['member_id' => $emil->id, 'amount' => 150, 'paid_at' => '2026-03-01']);
        $emilDue->payments()->attach($payment->id);

        $ids = [$this->ben->id, $dora->id, $emil->id];
        $before = MemberDue::whereIn('member_id', $ids)->orderBy('id')->get()->map->only(['id', 'status', 'amount', 'teilbetrag'])->all();
        $this->replace()->assertSessionHasNoErrors();
        $after = MemberDue::whereIn('member_id', $ids)->orderBy('id')->get()->map->only(['id', 'status', 'amount', 'teilbetrag'])->all();

        $this->assertSame($before, $after);
        $this->assertSame(0, DB::table('household_due_member')->whereIn('member_id', $ids)->count());
        $this->assertDatabaseMissing('member_dues', ['member_id' => $this->anna->id]);
    }

    #[Test]
    public function replacing_without_confirmation_changes_nothing(): void
    {
        $this->own($this->anna);

        $this->replace(['confirm' => null])->assertSessionHasErrors('confirm');

        $this->assertSame(1, MemberDue::count());
        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function nothing_to_replace_no_rate_or_a_closed_year_is_refused(): void
    {
        $this->replace()->assertSessionHasErrors('fiscal_year_id');

        $next = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        MemberDue::create(['member_id' => $this->anna->id, 'fiscal_year_id' => $next->id, 'fee_category_id' => $this->adults->id, 'amount' => 150]);
        $this->replace(['fiscal_year_id' => $next->id])->assertSessionHasErrors('fiscal_year_id');

        $this->own($this->anna);
        $this->year->close(1);
        $this->replace()->assertSessionHasErrors('fiscal_year_id');

        $this->assertSame(2, MemberDue::count());
        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function the_preview_explains_why_replacing_is_not_possible(): void
    {
        $this->actingAs($this->admin())->get("/members/households/{$this->h->id}/replace-dues?fiscal_year_id={$this->year->id}")
            ->assertOk()
            ->assertSeeText(__('members::admin.households.replace.nothing'))
            ->assertDontSeeText(__('members::admin.households.replace.confirm'));
        $this->actingAs($this->admin())->get("/members/households/{$this->h->id}/replace-dues?fiscal_year_id=abc")->assertNotFound();
    }
}
