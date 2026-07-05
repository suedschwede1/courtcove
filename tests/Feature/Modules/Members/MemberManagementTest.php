<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_members_is_a_registered_privilege(): void
    {
        $this->assertContains('admin.members', User::PRIVILEGES);
    }

    #[Test]
    public function admin_holds_the_members_privilege(): void
    {
        $this->assertTrue($this->admin()->can('admin.members'));
    }

    #[Test]
    public function a_regular_enabled_user_does_not_hold_the_members_privilege(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);

        $this->assertFalse($user->can('admin.members'));
    }

    #[Test]
    public function a_non_admin_cannot_access_the_members_area(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)->get('/members')->assertForbidden();
    }

    #[Test]
    public function index_lists_active_members_and_hides_departed_ones(): void
    {
        \App\Modules\Members\Models\Member::factory()->create(['firstname' => 'Aktiv', 'lastname' => 'Mitglied']);
        \App\Modules\Members\Models\Member::factory()->create(['firstname' => 'Ausgetreten', 'lastname' => 'Mitglied', 'left_at' => '2025-01-01']);

        $this->actingAs($this->admin())->get('/members')
            ->assertOk()->assertSee('Aktiv Mitglied')->assertDontSee('Ausgetreten Mitglied');
    }

    #[Test]
    public function admin_can_create_a_member(): void
    {
        $this->actingAs($this->admin())->post('/members', [
            'member_number' => 1,
            'firstname' => 'Neu',
            'lastname' => 'Mitglied',
            'email' => 'neu@example.com',
            'joined_at' => '2026-01-01',
        ])->assertRedirect(route('members.index'));

        $this->assertDatabaseHas('members', ['member_number' => 1, 'firstname' => 'Neu', 'lastname' => 'Mitglied']);
    }

    #[Test]
    public function create_form_prefills_the_next_free_member_number(): void
    {
        \App\Modules\Members\Models\Member::factory()->create(['member_number' => 5]);

        $this->actingAs($this->admin())->get('/members/create')
            ->assertOk()->assertSee('value="6"', false);
    }

    #[Test]
    public function creating_a_member_with_a_duplicate_number_fails_validation(): void
    {
        \App\Modules\Members\Models\Member::factory()->create(['member_number' => 7]);

        $this->actingAs($this->admin())->post('/members', [
            'member_number' => 7,
            'firstname' => 'Doppelt',
            'lastname' => 'Vergeben',
            'joined_at' => '2026-01-01',
        ])->assertSessionHasErrors('member_number');
    }

    #[Test]
    public function admin_can_mark_a_member_as_departed(): void
    {
        $member = \App\Modules\Members\Models\Member::factory()->create();

        $this->actingAs($this->admin())->delete("/members/{$member->id}")
            ->assertRedirect(route('members.index'));

        $this->assertNotNull($member->fresh()->left_at);
    }

    #[Test]
    public function members_store_the_new_master_data_fields(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);

        $member = Member::factory()->create([
            'gender' => 'w',
            'street' => 'Teststr. 1',
            'zip' => '4400',
            'city' => 'Sampletown',
            'nuliga_id' => 288563,
            'fee_category_id' => $category->id,
        ]);

        $this->assertSame('Vollzahler', $member->feeCategory->name);
        $this->assertSame('w', $member->fresh()->gender);
        $this->assertSame(288563, $member->fresh()->nuliga_id);
    }

    #[Test]
    public function age_is_computed_from_birthdate(): void
    {
        $member = Member::factory()->create(['birthdate' => now()->subYears(46)->subDay()->toDateString()]);

        $this->assertSame(46, $member->age());
        $this->assertNull(Member::factory()->create(['birthdate' => null])->age());
    }

    #[Test]
    public function admin_can_create_a_member_with_all_master_data_fields(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);

        $this->actingAs($this->admin())->post('/members', [
            'member_number' => 1,
            'firstname' => 'Neu',
            'lastname' => 'Mitglied',
            'gender' => 'w',
            'street' => 'Teststr. 1',
            'zip' => '4400',
            'city' => 'Sampletown',
            'nuliga_id' => 288563,
            'fee_category_id' => $category->id,
            'joined_at' => '2026-01-01',
        ])->assertRedirect(route('members.index'));

        $this->assertDatabaseHas('members', [
            'member_number' => 1, 'gender' => 'w', 'street' => 'Teststr. 1', 'zip' => '4400',
            'city' => 'Sampletown', 'nuliga_id' => 288563, 'fee_category_id' => $category->id,
        ]);
    }

    #[Test]
    public function an_invalid_gender_fails_validation(): void
    {
        $this->actingAs($this->admin())->post('/members', [
            'member_number' => 1, 'firstname' => 'A', 'lastname' => 'B',
            'gender' => 'x', 'joined_at' => '2026-01-01',
        ])->assertSessionHasErrors('gender');
    }

    #[Test]
    public function a_duplicate_nuliga_id_fails_validation(): void
    {
        Member::factory()->create(['nuliga_id' => 111]);

        $this->actingAs($this->admin())->post('/members', [
            'member_number' => 99, 'firstname' => 'A', 'lastname' => 'B',
            'nuliga_id' => 111, 'joined_at' => '2026-01-01',
        ])->assertSessionHasErrors('nuliga_id');
    }

    #[Test]
    public function updating_a_member_keeps_its_own_nuliga_id_valid(): void
    {
        $member = Member::factory()->create(['nuliga_id' => 222]);

        $this->actingAs($this->admin())->put("/members/{$member->id}", [
            'member_number' => $member->member_number,
            'firstname' => $member->firstname,
            'lastname' => $member->lastname,
            'nuliga_id' => 222,
            'joined_at' => $member->joined_at->toDateString(),
        ])->assertSessionDoesntHaveErrors('nuliga_id')->assertRedirect(route('members.index'));
    }

    #[Test]
    public function index_can_search_members_by_first_or_last_name(): void
    {
        Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        Member::factory()->create(['firstname' => 'Bernd', 'lastname' => 'Alt']);

        $this->actingAs($this->admin())->get('/members?search=must')
            ->assertOk()->assertSee('Anna Muster')->assertDontSee('Bernd Alt');

        $this->actingAs($this->admin())->get('/members?search=bernd')
            ->assertOk()->assertSee('Bernd Alt')->assertDontSee('Anna Muster');
    }

    #[Test]
    public function index_can_filter_members_by_fee_category(): void
    {
        $vollzahler = FeeCategory::create(['name' => 'Vollzahler']);
        $pensionist = FeeCategory::create(['name' => 'Pensionisten']);
        Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster', 'fee_category_id' => $vollzahler->id]);
        Member::factory()->create(['firstname' => 'Bernd', 'lastname' => 'Alt', 'fee_category_id' => $pensionist->id]);

        $this->actingAs($this->admin())->get("/members?fee_category={$vollzahler->id}")
            ->assertOk()->assertSee('Anna Muster')->assertDontSee('Bernd Alt');
    }

    #[Test]
    public function index_shows_age_and_fee_category_columns(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        Member::factory()->create([
            'firstname' => 'Anna', 'lastname' => 'Muster',
            'birthdate' => now()->subYears(46)->subDay()->toDateString(),
            'fee_category_id' => $category->id,
        ]);

        $this->actingAs($this->admin())->get('/members')
            ->assertOk()->assertSee('Vollzahler')->assertSee('<td class="text-[#6a6e73]">46</td>', false);
    }

}
