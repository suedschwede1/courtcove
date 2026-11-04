<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberAnonymizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function the_delete_button_is_hidden_for_an_active_member(): void
    {
        $member = Member::factory()->create(['left_at' => null]);

        $this->actingAs($this->admin())->get("/members/{$member->id}/edit")
            ->assertOk()
            ->assertDontSee(route('members.anonymize', $member), false);
    }

    #[Test]
    public function the_delete_button_is_shown_once_a_departure_date_is_set(): void
    {
        $member = Member::factory()->create(['left_at' => now()->subDay()->toDateString()]);

        $this->actingAs($this->admin())->get("/members/{$member->id}/edit")
            ->assertOk()
            ->assertSee(route('members.anonymize', $member), false);
    }

    #[Test]
    public function a_non_admin_cannot_anonymize_a_member(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $member = Member::factory()->create(['left_at' => now()->subYears(5)->toDateString()]);

        $this->actingAs($user)->post("/members/{$member->id}/anonymize", [
            'confirmation' => 'ANONYMISIEREN',
        ])->assertForbidden();
    }

    #[Test]
    public function anonymizing_requires_the_exact_confirmation_keyword(): void
    {
        $member = Member::factory()->create(['left_at' => now()->subYears(5)->toDateString()]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/anonymize", [
            'confirmation' => 'wrong',
        ])->assertSessionHasErrors('confirmation');

        $this->assertNull($member->fresh()->anonymized_at);
    }

    #[Test]
    public function a_member_that_is_not_yet_eligible_cannot_be_anonymized(): void
    {
        $member = Member::factory()->create(['left_at' => now()->subYear()->toDateString()]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/anonymize", [
            'confirmation' => 'ANONYMISIEREN',
        ])->assertSessionHasErrors('confirmation');

        $this->assertNull($member->fresh()->anonymized_at);
    }

    #[Test]
    public function an_already_anonymized_member_cannot_be_anonymized_again(): void
    {
        $member = Member::factory()->create([
            'left_at' => now()->subYears(5)->toDateString(),
            'anonymized_at' => now()->subDay(),
            'email' => null,
        ]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/anonymize", [
            'confirmation' => 'ANONYMISIEREN',
        ])->assertSessionHasErrors('confirmation');

        $this->assertSame(
            $member->anonymized_at->timestamp,
            $member->fresh()->anonymized_at->timestamp,
        );
    }

    #[Test]
    public function anonymizing_an_eligible_member_clears_personal_data_and_keeps_the_rest(): void
    {
        $member = Member::factory()->create([
            'member_number' => 42,
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'birthdate' => '1980-01-01',
            'gender' => 'w',
            'email' => 'anna@example.com',
            'phone' => '0664 1234567',
            'street' => 'Teststr. 1',
            'zip' => '4400',
            'city' => 'Teststadt',
            'nuliga_id' => 123456,
            'joined_at' => '2010-01-01',
            'left_at' => now()->subYears(5)->toDateString(),
        ]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/anonymize", [
            'confirmation' => 'ANONYMISIEREN',
        ])->assertRedirect(route('members.edit', $member));

        $fresh = $member->fresh();
        $this->assertSame('Ehemaliges', $fresh->firstname);
        $this->assertSame('Mitglied #42', $fresh->lastname);
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->phone);
        $this->assertNull($fresh->street);
        $this->assertNull($fresh->zip);
        $this->assertNull($fresh->city);
        $this->assertNull($fresh->nuliga_id);
        $this->assertNotNull($fresh->anonymized_at);
        $this->assertSame(42, $fresh->member_number);
        $this->assertSame('1980-01-01', $fresh->birthdate->toDateString());
        $this->assertSame('w', $fresh->gender);
        $this->assertSame('2010-01-01', $fresh->joined_at->toDateString());
    }

    #[Test]
    public function anonymized_fields_are_locked_from_inline_editing(): void
    {
        $member = Member::factory()->create([
            'left_at' => now()->subYears(5)->toDateString(),
            'anonymized_at' => now(),
            'email' => null,
        ]);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'email', 'value' => 'new@example.com'])
            ->assertStatus(422);

        $this->assertNull($member->fresh()->email);
    }
}
