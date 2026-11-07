<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Support\MemberConnectionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Member page: the Anschlussmitglied card on both sides, connect/disconnect, warnings; category page role. */
class MemberConnectionPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FeeCategory $full;

    private FeeCategory $partner;

    private Member $heinz;

    private Member $helga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->full = FeeCategory::create(['name' => 'Vollzahler', 'connection_role' => 'vollzahler']);
        $this->partner = FeeCategory::create(['name' => 'Ehepartner/in, Lebensgefährte/in', 'connection_role' => 'anschluss']);
        $this->heinz = Member::factory()->create(['firstname' => 'Heinz', 'lastname' => 'Muster', 'fee_category_id' => $this->full->id]);
        $this->helga = Member::factory()->create(['firstname' => 'Helga', 'lastname' => 'Beispiel', 'fee_category_id' => $this->partner->id]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function an_unconnected_anschluss_member_sees_a_warning_and_the_vollzahler_choice(): void
    {
        $this->actingAs($this->admin())->get("/members/{$this->helga->id}/edit")
            ->assertOk()
            ->assertSeeText(__('members::admin.connection.card_title'))
            ->assertSeeText(__('members::admin.connection.warnings.anschluss_without_connection'))
            ->assertSee('<option value="'.$this->heinz->id.'">', false)
            ->assertSeeText(__('members::admin.connection.connect'));
    }

    #[Test]
    public function connecting_shows_the_link_on_both_sides(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post("/members/{$this->helga->id}/connection", ['vollzahler_id' => $this->heinz->id])
            ->assertRedirect(route('members.edit', $this->helga))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->heinz->id, $this->helga->fresh()?->connected_to_member_id);
        $this->actingAs($admin)->get("/members/{$this->helga->id}/edit")
            ->assertSeeText(__('members::admin.connection.connected_to'))
            ->assertSeeText('Heinz Muster')
            ->assertDontSeeText(__('members::admin.connection.warnings.anschluss_without_connection'));
        $this->actingAs($admin)->get("/members/{$this->heinz->id}/edit")
            ->assertSeeText(__('members::admin.connection.anschluss_member'))
            ->assertSeeText('Helga Beispiel')
            ->assertSeeText(__('members::admin.connection.history_set', ['name' => 'Helga Beispiel']));
    }

    #[Test]
    public function rule_violations_and_bad_input_come_back_as_errors(): void
    {
        $youth = FeeCategory::create(['name' => 'Jugend']);
        $kid = Member::factory()->create(['fee_category_id' => $youth->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/members/{$this->helga->id}/connection", ['vollzahler_id' => $kid->id])->assertSessionHasErrors('connection');
        $this->actingAs($admin)->post("/members/{$this->helga->id}/connection", ['vollzahler_id' => ['x']])->assertSessionHasErrors('vollzahler_id');
        $this->actingAs($admin)->post("/members/{$this->helga->id}/connection", ['vollzahler_id' => 999])->assertSessionHasErrors('connection');
        $this->actingAs($admin)->post('/members/999/connection', ['vollzahler_id' => $this->heinz->id])->assertNotFound();
        $this->assertNull($this->helga->fresh()?->connected_to_member_id);
    }

    #[Test]
    public function disconnecting_from_the_page(): void
    {
        app(MemberConnectionService::class)->connect($this->helga, $this->heinz);

        $this->actingAs($this->admin())->delete("/members/{$this->helga->id}/connection")->assertRedirect(route('members.edit', $this->helga));

        $this->assertNull($this->helga->fresh()?->connected_to_member_id);
    }

    #[Test]
    public function the_routes_are_forbidden_without_the_members_privilege(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $this->actingAs($user)->post("/members/{$this->helga->id}/connection", ['vollzahler_id' => $this->heinz->id])->assertForbidden();
        $this->actingAs($user)->delete("/members/{$this->helga->id}/connection")->assertForbidden();
        $this->assertNull($this->helga->fresh()?->connected_to_member_id);
    }

    #[Test]
    public function the_category_page_sets_the_role_and_badges_it(): void
    {
        $admin = $this->admin();
        $other = FeeCategory::create(['name' => 'Pensionisten']);

        $this->actingAs($admin)->put("/members/fee-categories/{$other->id}", ['name' => 'Pensionisten', 'connection_role' => 'vollzahler'])->assertSessionHasNoErrors();
        $this->assertSame('vollzahler', $other->fresh()?->connection_role);
        $this->actingAs($admin)->put("/members/fee-categories/{$other->id}", ['name' => 'Pensionisten', 'connection_role' => 'boss'])->assertSessionHasErrors('connection_role');
        $this->actingAs($admin)->put("/members/fee-categories/{$other->id}", ['name' => 'Pensionisten neu'])->assertSessionHasNoErrors();
        $this->assertSame('vollzahler', $other->fresh()?->connection_role);

        $this->actingAs($admin)->get('/members/fee-categories')->assertSeeText(__('members::admin.connection.role_badge_anschluss'));
        $this->actingAs($admin)->get("/members/fee-categories/{$this->partner->id}/edit")->assertSeeText(__('members::admin.connection.role'));
    }

    #[Test]
    public function a_role_used_by_a_connection_cannot_be_removed(): void
    {
        app(MemberConnectionService::class)->connect($this->helga, $this->heinz);

        $this->actingAs($this->admin())->put("/members/fee-categories/{$this->partner->id}", ['name' => $this->partner->name, 'connection_role' => ''])
            ->assertSessionHasErrors('connection_role');

        $this->assertSame('anschluss', $this->partner->fresh()?->connection_role);
    }
}
