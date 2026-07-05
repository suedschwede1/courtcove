<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberDueFieldUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function makeDue(): MemberDue
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        return MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
        ]);
    }

    #[Test]
    public function admin_can_update_the_status_field(): void
    {
        $due = $this->makeDue();

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'ueberfaellig'])
            ->assertOk()
            ->assertJson(['value' => 'ueberfaellig']);

        $this->assertSame('ueberfaellig', $due->fresh()->status);
    }

    #[Test]
    public function an_unknown_status_value_is_rejected(): void
    {
        $due = $this->makeDue();

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'irgendwas'])
            ->assertStatus(422);

        $this->assertSame('ausstehend', $due->fresh()->status);
    }

    #[Test]
    public function admin_can_set_teilbetrag(): void
    {
        $due = $this->makeDue();

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'teilbetrag', 'value' => 42.50])
            ->assertOk()
            ->assertJson(['value' => '42,50 €']);

        $this->assertSame('42.50', $due->fresh()->teilbetrag);
    }

    #[Test]
    public function clearing_teilbetrag_returns_a_dash(): void
    {
        $due = $this->makeDue();
        $due->update(['teilbetrag' => 42.50]);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'teilbetrag', 'value' => null])
            ->assertOk()
            ->assertJson(['value' => '–']);

        $this->assertNull($due->fresh()->teilbetrag);
    }

    #[Test]
    public function admin_can_set_kommentar(): void
    {
        $due = $this->makeDue();

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'kommentar', 'value' => 'Halber Beitrag erlassen'])
            ->assertOk()
            ->assertJson(['value' => 'Halber Beitrag erlassen']);

        $this->assertSame('Halber Beitrag erlassen', $due->fresh()->kommentar);
    }

    #[Test]
    public function admin_can_update_faellig_am(): void
    {
        $due = $this->makeDue();

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'faellig_am', 'value' => '2026-01-15'])
            ->assertOk()
            ->assertJson(['value' => '15.01.2026']);

        $this->assertSame('2026-01-15', $due->fresh()->faellig_am->toDateString());
    }

    #[Test]
    public function a_non_whitelisted_field_is_rejected(): void
    {
        $due = $this->makeDue();

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'amount', 'value' => 999])
            ->assertStatus(422);

        $this->assertSame('120.00', $due->fresh()->amount);
    }

    #[Test]
    public function a_regular_user_cannot_update_a_field(): void
    {
        $due = $this->makeDue();
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])
            ->assertForbidden();
    }
}
