<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function dashboard_shows_age_structure_of_active_members(): void
    {
        Member::factory()->create(['birthdate' => now()->subYears(16)->toDateString()]);
        Member::factory()->create(['birthdate' => now()->subYears(36)->toDateString()]);
        Member::factory()->create(['birthdate' => now()->subYears(41)->toDateString()]);
        Member::factory()->create(['birthdate' => now()->subYears(76)->toDateString()]);
        Member::factory()->create(['birthdate' => null]);
        // Departed members are not part of the age structure.
        Member::factory()->create(['birthdate' => now()->subYears(20)->toDateString(), 'left_at' => '2025-01-01']);

        $response = $this->actingAs($this->admin())->get('/members/dashboard')->assertOk();

        $response->assertSeeText('Altersstruktur');
        $response->assertSeeTextInOrder(['bis 18', '1']);
        $response->assertSeeTextInOrder(['19–35', '0']);
        $response->assertSeeTextInOrder(['36–50', '2']);
        $response->assertSeeTextInOrder(['51–65', '0']);
        $response->assertSeeTextInOrder(['66+', '1']);
        $response->assertSeeTextInOrder(['ohne Geburtsdatum', '1']);
    }

    #[Test]
    public function dashboard_shows_active_member_count_and_open_dues_total(): void
    {
        Member::factory()->create();
        Member::factory()->create(['left_at' => '2025-01-01']);

        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        MemberDue::create(['member_id' => Member::query()->active()->first()->id, 'fiscal_year_id' => $fiscalYear->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->get('/members/dashboard')
            ->assertOk()
            ->assertSee('1') // active member count
            ->assertSee('120');
    }

    #[Test]
    public function open_and_paid_dues_are_scoped_to_the_fiscal_year_running_today(): void
    {
        // Frozen inside fiscal year 2025/26 so the fixture years stay past/current/future.
        $this->travelTo('2026-07-01 10:00:00');

        $paidMember = Member::factory()->create();
        $openMember = Member::factory()->create();
        $futureMember = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $pastYear = FiscalYear::create(['name' => '2024/25', 'starts_on' => '2024-09-01', 'ends_on' => '2025-08-31']);
        $currentYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $futureYear = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        MemberDue::create(['member_id' => $paidMember->id, 'fiscal_year_id' => $pastYear->id, 'fee_category_id' => $category->id, 'amount' => 90, 'status' => 'bezahlt']);
        $paid = MemberDue::create(['member_id' => $paidMember->id, 'fiscal_year_id' => $currentYear->id, 'fee_category_id' => $category->id, 'amount' => 100, 'status' => 'bezahlt']);
        $paid->payments()->attach(Payment::create(['member_id' => $paidMember->id, 'amount' => 100, 'paid_at' => now()]));
        MemberDue::create(['member_id' => $openMember->id, 'fiscal_year_id' => $currentYear->id, 'fee_category_id' => $category->id, 'amount' => 50, 'status' => 'ausstehend']);
        MemberDue::create(['member_id' => $futureMember->id, 'fiscal_year_id' => $futureYear->id, 'fee_category_id' => $category->id, 'amount' => 200, 'status' => 'ausstehend']);

        $this->actingAs($this->admin())->get('/members/dashboard')
            ->assertOk()
            ->assertSeeTextInOrder(['Beitrag 2025/26 offen', '1', '50,00 €'])
            ->assertSeeTextInOrder(['Bezahlte Beiträge', '1', '100,00 €']);
    }

    #[Test]
    public function dashboard_shows_active_members_per_fee_category(): void
    {
        $vollmitglied = FeeCategory::create(['name' => 'Vollmitglied']);
        $ehrenmitglied = FeeCategory::create(['name' => 'Ehrenmitglied']);
        Member::factory()->count(2)->create(['fee_category_id' => $vollmitglied->id]);
        Member::factory()->create(['fee_category_id' => $ehrenmitglied->id]);
        Member::factory()->create(['fee_category_id' => null]);
        // Departed members are not part of the breakdown.
        Member::factory()->create(['fee_category_id' => $vollmitglied->id, 'left_at' => '2025-01-01']);

        $response = $this->actingAs($this->admin())->get('/members/dashboard')->assertOk();

        $response->assertSeeText('Mitglieder pro Beitragskategorie');
        $response->assertSeeTextInOrder(['Vollmitglied', '2']);
        $response->assertSeeTextInOrder(['Ehrenmitglied', '1']);
        $response->assertSeeTextInOrder(['Ohne Kategorie', '1']);
    }

    #[Test]
    public function dashboard_counts_status_paid_dues_without_payment_records_as_paid(): void
    {
        $paidMember = Member::factory()->create();
        $openMember = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $currentYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        MemberDue::create(['member_id' => $paidMember->id, 'fiscal_year_id' => $currentYear->id, 'fee_category_id' => $category->id, 'amount' => 100, 'status' => 'bezahlt']);
        MemberDue::create(['member_id' => $openMember->id, 'fiscal_year_id' => $currentYear->id, 'fee_category_id' => $category->id, 'amount' => 50, 'status' => 'ausstehend']);

        $this->actingAs($this->admin())->get('/members/dashboard')
            ->assertOk()
            ->assertSeeTextInOrder(['Beitrag 2025/26 offen', '1', '50,00 €'])
            ->assertSeeTextInOrder(['Bezahlte Beiträge', '1', '100,00 €']);
    }

    #[Test]
    public function kpis_count_members_youth_active_dormant_and_members_without_account(): void
    {
        $user = User::factory()->create();
        Member::factory()->create(['status' => 'aktiv', 'birthdate' => now()->subYears(15)->toDateString(), 'user_id' => $user->uid]);
        Member::factory()->create(['status' => 'aktiv', 'birthdate' => now()->subYears(40)->toDateString()]);
        Member::factory()->create(['status' => 'ruhend', 'birthdate' => null]);
        Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2025-01-01', 'birthdate' => now()->subYears(12)->toDateString()]);

        $html = (string) $this->actingAs($this->admin())->get('/members/dashboard')->assertOk()->getContent();

        $kpi = fn (string $label, string $value, string $meta) => '#<p class="ds-kpi__label">'.preg_quote($label, '#').'</p>\s*<p class="ds-kpi__value">'.preg_quote($value, '#').'</p>\s*<p class="ds-kpi__meta">'.preg_quote($meta, '#').'</p>#';
        $this->assertMatchesRegularExpression($kpi('Mitglieder', '3', 'davon 1 Jugend'), $html);
        $this->assertMatchesRegularExpression($kpi('Aktiv', '2', '1 ruhend'), $html);
        $this->assertMatchesRegularExpression($kpi('Ohne Benutzerkonto', '2', 'nur Vereinsmitglied'), $html);
    }

    #[Test]
    public function overview_lists_open_dues_and_recent_changes_with_links(): void
    {
        $this->travelTo('2026-07-01 10:00:00');
        $admin = $this->admin();
        $category = FeeCategory::create(['name' => 'Erwachsene']);
        $year = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        foreach (['Aigner', 'Berger', 'Cerny', 'Doppler', 'Egger', 'Fink'] as $lastname) {
            $member = Member::factory()->create(['firstname' => 'Test', 'lastname' => $lastname]);
            MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $category->id, 'amount' => 80, 'status' => 'ausstehend']);
        }
        $paid = Member::factory()->create(['firstname' => 'Test', 'lastname' => 'Bezahlt']);
        MemberDue::create(['member_id' => $paid->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $category->id, 'amount' => 80, 'status' => 'bezahlt']);
        $changed = Member::factory()->create(['firstname' => 'Gerda', 'lastname' => 'Geaendert']);
        MemberChange::create(['member_id' => $changed->id, 'admin_user_id' => $admin->uid, 'action' => 'updated', 'before' => ['phone' => '1'], 'after' => ['phone' => '2']]);

        $html = (string) $this->actingAs($admin)->get('/members/dashboard')->assertOk()->getContent();

        $open = substr($html, (int) strpos($html, 'Beitrag offen</h2>'));
        $this->assertSame(5, substr_count(substr($open, 0, (int) strpos($open, '</section>')), 'class="mbr-due-row"'));
        $this->assertStringNotContainsString('Bezahlt, Test', $open);
        $this->assertStringContainsString('Aigner, Test', $open);
        $this->assertStringContainsString('href="'.route('members.dues.index').'"', $html);

        $this->assertStringContainsString('Geaendert, Gerda', $html);
        $this->assertStringContainsString('Telefon geändert', $html);
        $this->assertStringContainsString('href="'.route('members.changes.index').'"', $html);

        $this->assertStringContainsString('href="'.route('members.create').'"', $html);
    }
}
