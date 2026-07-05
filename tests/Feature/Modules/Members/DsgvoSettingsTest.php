<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\Option;
use App\Models\User;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DsgvoSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function config_page_shows_the_default_retention_period(): void
    {
        $response = $this->actingAs($this->admin())->get(route('members.dsgvo.index'));

        $response->assertOk();
        $response->assertSee('value="3"', false);
    }

    #[Test]
    public function config_page_shows_a_saved_retention_period(): void
    {
        Option::create(['key' => 'members.anonymization.retention_years', 'value' => '5', 'locale' => null]);

        $response = $this->actingAs($this->admin())->get(route('members.dsgvo.index'));

        $response->assertOk();
        $response->assertSee('value="5"', false);
    }

    #[Test]
    public function admin_can_change_the_retention_period(): void
    {
        $this->actingAs($this->admin())->post(route('members.dsgvo.update'), [
            'retention_years' => 5,
        ])->assertRedirect(route('members.dsgvo.index'));

        $this->assertDatabaseHas('bs_options', ['key' => 'members.anonymization.retention_years', 'value' => '5']);
    }

    #[Test]
    public function retention_period_must_be_a_positive_integer(): void
    {
        $this->actingAs($this->admin())->post(route('members.dsgvo.update'), [
            'retention_years' => 0,
        ])->assertSessionHasErrors('retention_years');
    }

    #[Test]
    public function changing_the_retention_period_affects_whether_a_member_can_be_anonymized(): void
    {
        $member = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => now()->subYears(4)]);
        $this->assertTrue($member->isAnonymizable());

        $this->actingAs($this->admin())->post(route('members.dsgvo.update'), ['retention_years' => 5]);

        $this->assertFalse($member->fresh()->isAnonymizable());
    }

    #[Test]
    public function regular_member_cannot_view_or_change_dsgvo_settings(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)->get(route('members.dsgvo.index'))->assertForbidden();
        $this->actingAs($user)->post(route('members.dsgvo.update'), ['retention_years' => 5])->assertForbidden();
    }
}
