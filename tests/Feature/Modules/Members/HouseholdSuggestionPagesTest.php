<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\HouseholdSuggestionDismissal;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdSuggestionFinder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Mögliche Familien": confirm (only the ticked members) or dismiss by hand; viewing creates nothing. */
class HouseholdSuggestionPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Member $anna;

    private Member $ben;

    private Member $max;

    private string $sig;

    protected function setUp(): void
    {
        parent::setUp();
        $make = fn (string $first) => Member::factory()->create(['firstname' => $first, 'lastname' => 'Muster', 'street' => 'Musterweg 1', 'zip' => '1000', 'email' => null, 'phone' => null]);
        $this->anna = $make('Anna');
        $this->ben = $make('Ben');
        $this->max = $make('Max');
        $this->sig = HouseholdSuggestionFinder::signature([$this->anna->id, $this->ben->id, $this->max->id]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function suggestions_are_forbidden_without_the_members_privilege(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $this->actingAs($user)->get('/members/households/suggestions')->assertForbidden();
        $this->actingAs($user)->post('/members/households/suggestions/confirm', ['signature' => $this->sig, 'member_ids' => [$this->anna->id, $this->ben->id]])->assertForbidden();
        $this->actingAs($user)->post('/members/households/suggestions/dismiss', ['signature' => $this->sig])->assertForbidden();
        $this->assertSame(0, MemberHousehold::count());
        $this->assertSame(0, HouseholdSuggestionDismissal::count());
    }

    #[Test]
    public function the_page_lists_the_group_and_creates_nothing_on_view(): void
    {
        $this->actingAs($this->admin())->get('/members/households/suggestions')
            ->assertOk()
            ->assertSeeText('Anna Muster')
            ->assertSeeText('Max Muster')
            ->assertSeeText(__('members::admin.households.suggestions.only_suggestions'))
            ->assertSeeText(__('members::admin.dsgvo.purpose_households'))
            ->assertSeeText(__('members::admin.households.suggestions.reason_address'));

        $this->assertSame(0, MemberHousehold::count());
    }

    #[Test]
    public function the_households_list_links_the_suggestions(): void
    {
        $this->actingAs($this->admin())->get('/members/households')
            ->assertSee(route('members.households.suggestions'), false)
            ->assertSeeText(__('members::admin.households.tab_suggestions'));
    }

    #[Test]
    public function confirming_creates_a_household_with_only_the_ticked_members(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);

        $response = $this->actingAs($this->admin())->post('/members/households/suggestions/confirm', [
            'signature' => $this->sig, 'member_ids' => [$this->anna->id, $this->ben->id], 'fee_category_id' => $family->id, 'name' => 'Familie Muster',
        ]);

        $household = MemberHousehold::sole();
        $response->assertRedirect(route('members.households.show', $household->id));
        $this->assertSame($household->id, $this->anna->fresh()?->household_id);
        $this->assertSame($household->id, $this->ben->fresh()?->household_id);
        $this->assertNull($this->max->fresh()?->household_id);
        $this->assertSame('Familie Muster', $household->name);
        $this->assertSame($family->id, $household->fee_category_id);
    }

    #[Test]
    public function confirming_needs_two_members_of_that_suggestion(): void
    {
        $dora = Member::factory()->create(['street' => null, 'zip' => null, 'email' => null, 'phone' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/members/households/suggestions/confirm', ['signature' => $this->sig, 'member_ids' => [$this->anna->id]])->assertSessionHasErrors('member_ids');
        $this->actingAs($admin)->post('/members/households/suggestions/confirm', ['signature' => $this->sig, 'member_ids' => [$this->anna->id, $dora->id]])->assertSessionHasErrors('member_ids');
        $this->actingAs($admin)->post('/members/households/suggestions/confirm', ['signature' => str_repeat('a', 64), 'member_ids' => [$this->anna->id, $this->ben->id]])->assertSessionHasErrors('signature');
        $this->actingAs($admin)->post('/members/households/suggestions/confirm', ['signature' => [$this->sig], 'member_ids' => 'x'])->assertSessionHasErrors(['signature', 'member_ids']);

        $this->assertSame(0, MemberHousehold::count());
    }

    #[Test]
    public function dismissing_hides_the_group_and_creates_nothing(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/members/households/suggestions/dismiss', ['signature' => $this->sig])->assertRedirect(route('members.households.suggestions'));

        $this->assertSame(0, MemberHousehold::count());
        $this->assertSame($admin->uid, HouseholdSuggestionDismissal::sole()->dismissed_by_user_id);
        $this->actingAs($admin)->get('/members/households/suggestions')->assertDontSeeText('Anna Muster');
        $this->actingAs($admin)->post('/members/households/suggestions/dismiss', ['signature' => str_repeat('b', 64)])->assertSessionHasErrors('signature');
    }
}
