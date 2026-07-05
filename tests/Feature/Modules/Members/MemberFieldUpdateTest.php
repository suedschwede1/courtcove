<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberFieldUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_can_update_the_firstname_field(): void
    {
        $member = Member::factory()->create(['firstname' => 'Alt']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'firstname', 'value' => 'Neu'])
            ->assertOk()
            ->assertJson(['value' => 'Neu']);

        $this->assertSame('Neu', $member->fresh()->firstname);
    }

    #[Test]
    public function admin_can_update_the_lastname_field(): void
    {
        $member = Member::factory()->create(['lastname' => 'Alt']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'lastname', 'value' => 'Neu'])
            ->assertOk()
            ->assertJson(['value' => 'Neu']);

        $this->assertSame('Neu', $member->fresh()->lastname);
    }

    #[Test]
    public function firstname_cannot_be_cleared(): void
    {
        $member = Member::factory()->create(['firstname' => 'Anna']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'firstname', 'value' => ''])
            ->assertStatus(422)
            ->assertExactJson(['message' => $this->firstErrorMessage('firstname', ['field' => 'firstname', 'value' => ''])]);
    }

    #[Test]
    public function lastname_cannot_be_cleared(): void
    {
        $member = Member::factory()->create(['lastname' => 'Muster']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'lastname', 'value' => ''])
            ->assertStatus(422)
            ->assertExactJson(['message' => $this->firstErrorMessage('lastname', ['field' => 'lastname', 'value' => ''])]);
    }

    #[Test]
    public function admin_can_clear_the_email_field(): void
    {
        $member = Member::factory()->create(['email' => 'alt@example.com']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'email', 'value' => null])
            ->assertOk()
            ->assertJson(['value' => '—']);

        $this->assertNull($member->fresh()->email);
    }

    #[Test]
    public function invalid_email_is_rejected(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'email', 'value' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function admin_can_update_the_fee_category_and_response_contains_its_name(): void
    {
        $member = Member::factory()->create(['fee_category_id' => null]);
        $category = FeeCategory::create(['name' => 'Vollzahler']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'fee_category_id', 'value' => $category->id])
            ->assertOk()
            ->assertJson(['value' => 'Vollzahler']);

        $this->assertSame($category->id, $member->fresh()->fee_category_id);
    }

    #[Test]
    public function clearing_the_fee_category_returns_a_dash(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $member = Member::factory()->create(['fee_category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'fee_category_id', 'value' => null])
            ->assertOk()
            ->assertJson(['value' => '—']);

        $this->assertNull($member->fresh()->fee_category_id);
    }

    #[Test]
    public function an_unknown_fee_category_is_rejected(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'fee_category_id', 'value' => 999])
            ->assertStatus(422);

        $this->assertNull($member->fresh()->fee_category_id);
    }

    #[Test]
    public function admin_can_update_joined_at_and_response_is_formatted(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'joined_at', 'value' => '2026-03-15'])
            ->assertOk()
            ->assertJson(['value' => '15.03.2026']);

        $this->assertSame('2026-03-15', $member->fresh()->joined_at->toDateString());
    }

    #[Test]
    public function setting_left_at_via_field_update_marks_the_member_ausgetreten(): void
    {
        $member = Member::factory()->create(['status' => 'aktiv']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'left_at', 'value' => '2026-03-01'])
            ->assertOk()
            ->assertJson(['value' => '01.03.2026']);

        $this->assertSame('2026-03-01', $member->fresh()->left_at->toDateString());
        $this->assertSame('ausgetreten', $member->fresh()->status);
    }

    #[Test]
    public function clearing_left_at_via_field_update_marks_the_member_aktiv(): void
    {
        $member = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2026-03-01']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'left_at', 'value' => null])
            ->assertOk()
            ->assertJson(['value' => '—']);

        $this->assertNull($member->fresh()->left_at);
        $this->assertSame('aktiv', $member->fresh()->status);
    }

    #[Test]
    public function admin_can_set_status_to_ruhend(): void
    {
        $member = Member::factory()->create(['status' => 'aktiv']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'status', 'value' => 'ruhend'])
            ->assertOk()
            ->assertJson(['value' => 'ruhend']);

        $this->assertSame('ruhend', $member->fresh()->status);
        $this->assertNull($member->fresh()->left_at);
    }

    #[Test]
    public function setting_status_to_aktiv_via_field_update_clears_left_at(): void
    {
        $member = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2026-03-01']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'status', 'value' => 'aktiv'])
            ->assertOk();

        $this->assertSame('aktiv', $member->fresh()->status);
        $this->assertNull($member->fresh()->left_at);
    }

    #[Test]
    public function setting_status_to_ausgetreten_via_field_update_is_rejected(): void
    {
        $member = Member::factory()->create(['status' => 'aktiv']);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'status', 'value' => 'ausgetreten'])
            ->assertStatus(422);

        $this->assertSame('aktiv', $member->fresh()->status);
    }

    #[Test]
    public function a_non_whitelisted_field_is_rejected(): void
    {
        $member = Member::factory()->create(['member_number' => 5]);

        $this->actingAs($this->admin())
            ->patchJson("/members/{$member->id}/field", ['field' => 'member_number', 'value' => 999])
            ->assertStatus(422);

        $this->assertSame(5, $member->fresh()->member_number);
    }

    #[Test]
    public function a_regular_user_cannot_update_a_field(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)
            ->patchJson("/members/{$member->id}/field", ['field' => 'firstname', 'value' => 'X'])
            ->assertForbidden();
    }

    /** Baut die erwartete Validierungsmeldung für ein Feld über einen echten Validator-Aufruf, um keinen Laravel-Text hart zu kodieren. */
    private function firstErrorMessage(string $field, array $input): string
    {
        $rules = [
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
        ];

        return validator($input, [
            'field' => ['required', 'string'],
            'value' => $rules[$field],
        ])->errors()->first('value');
    }
}
