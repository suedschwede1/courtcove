<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\MailSend;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StageMemberAccountingRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private array $csvPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');
        $this->actingAs(User::factory()->create(['status' => 'admin']));
    }

    protected function tearDown(): void
    {
        foreach ($this->csvPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function year(int $year): FiscalYear
    {
        return FiscalYear::create(['name' => (string) $year, 'starts_on' => "$year-01-01", 'ends_on' => "$year-12-31"]);
    }

    private function due(Member $member, FiscalYear $year, FeeCategory $category, string $status = 'ausstehend'): MemberDue
    {
        return MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $category->id, 'amount' => 120, 'status' => $status, 'faellig_am' => $year->starts_on]);
    }

    private function import(string $paid = '', string $email = ''): void
    {
        $path = __DIR__.'/members-review-'.bin2hex(random_bytes(5)).'.csv';
        $this->csvPaths[] = $path;
        file_put_contents($path, "Name,Vorname,GebDatum,Mitgliedsart,MB,Email\nExample,Review,01.01.1990,Vollmitglied,$paid,$email\n");
        $this->artisan('members:import-csv', ['file' => $path])->assertSuccessful();
    }

    #[Test]
    public function imported_paid_dues_agree_with_the_paid_filter_and_dashboard(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $year = $this->year(2026);
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $year->id, 'amount' => 120]);
        $this->import('bezahlt 26');
        $due = MemberDue::firstOrFail();
        $this->assertTrue($due->isPaid());
        $this->assertSame('bezahlt', $due->status);
        $this->assertSame('bezahlt', $due->displayStatus());
        $this->get('/members/dues?fiscal_year_id='.$year->id.'&status=bezahlt')->assertOk()->assertViewHas('dues', fn ($dues) => $dues->total() === 1);
        $this->get('/members/dashboard')->assertOk()->assertViewHas('paidDuesCount', 1);
    }

    #[Test]
    public function a_missing_rate_defers_the_due_until_import_is_rerun_with_a_configured_rate(): void
    {
        $this->import();
        $this->assertSame(0, MemberDue::count());
        $member = Member::firstOrFail();
        $year = FiscalYear::firstOrFail();
        FeeCategoryRate::create(['fee_category_id' => $member->fee_category_id, 'fiscal_year_id' => $year->id, 'amount' => 120]);
        $this->import();
        $this->assertSame('120.00', MemberDue::firstOrFail()->amount);
        $this->assertSame(1, Member::count());
    }

    #[Test]
    public function generation_uses_the_previous_year_and_ignores_future_categories(): void
    {
        $member = Member::factory()->create();
        $a = FeeCategory::create(['name' => 'Prior category']);
        $b = FeeCategory::create(['name' => 'Future category']);
        $prior = $this->year(2025);
        $target = $this->year(2026);
        $future = $this->year(2027);
        $this->due($member, $prior, $a);
        $this->due($member, $future, $b);
        FeeCategoryRate::create(['fee_category_id' => $a->id, 'fiscal_year_id' => $target->id, 'amount' => 100]);
        FeeCategoryRate::create(['fee_category_id' => $b->id, 'fiscal_year_id' => $target->id, 'amount' => 150]);
        $this->post('/members/dues/generate', ['fiscal_year_id' => $target->id])->assertRedirect();
        $generated = MemberDue::where('fiscal_year_id', $target->id)->firstOrFail();
        $this->assertSame($a->id, $generated->fee_category_id);
        $this->assertSame('100.00', $generated->amount);
    }

    #[Test]
    public function paid_member_filter_uses_the_selected_year(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Standard']);
        $this->due($member, $this->year(2025), $category, 'bezahlt');
        $this->due($member, $this->year(2026), $category);
        $this->get('/members?zahlung=bezahlt')->assertOk()->assertViewHas('members', fn ($members) => ! $members->contains('id', $member->id));
        $this->assertSame('ausstehend', $member->dues()->orderByDesc('fiscal_year_id')->first()->status);
    }

    #[Test]
    public function a_paid_contribution_cannot_be_marked_open_without_voiding_payment(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Standard']);
        $year = $this->year(2026);
        $due = $this->due($member, $year, $category);
        $this->post('/members/'.$member->id.'/payments', ['amount' => 120, 'paid_at' => '2026-11-09', 'member_due_ids' => [$due->id]])->assertRedirect();
        $this->patchJson('/members/dues/'.$due->id.'/field', ['field' => 'status', 'value' => 'ausstehend'])->assertOk();
        $this->assertTrue($due->fresh()->isPaid());
        $this->assertSame('bezahlt', $due->fresh()->displayStatus());
        $this->get('/members/dues?fiscal_year_id='.$year->id.'&status=ueberfaellig')->assertOk()->assertViewHas('dues', fn ($dues) => $dues->total() === 0);
        $this->get('/members/dashboard')->assertOk()->assertViewHas('openDuesCount', 0)->assertViewHas('paidDuesCount', 1);
    }

    #[Test]
    public function the_rest_api_rejects_changes_to_a_paid_amount(): void
    {
        Sanctum::actingAs(auth()->user());
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Standard']);
        $due = $this->due($member, $this->year(2026), $category);
        $this->post('/members/'.$member->id.'/payments', ['amount' => 120, 'paid_at' => '2026-11-09', 'member_due_ids' => [$due->id]])->assertRedirect();
        $this->putJson('/api/v1/dues/'.$due->id, ['amount' => 999])->assertUnprocessable();
        $this->assertSame('120.00', $due->fresh()->amount);
        $this->assertTrue($due->fresh()->isPaid());
        $this->assertSame('120.00', Payment::first()->amount);
    }

    #[Test]
    public function the_create_form_saves_departure_date_and_status(): void
    {
        $this->get('/members/create')->assertOk()->assertSee('name="left_at"', false);
        $this->post('/members', ['member_number' => 10, 'firstname' => 'New', 'lastname' => 'Example', 'joined_at' => '2020-01-01', 'left_at' => '2025-01-01'])->assertRedirect()->assertSessionHasNoErrors();
        $member = Member::where('member_number', 10)->firstOrFail();
        $this->assertSame('2025-01-01', $member->left_at->toDateString());
        $this->assertSame('ausgetreten', $member->status);
    }

    #[Test]
    public function a_rest_contact_update_without_roles_preserves_all_role_assignments(): void
    {
        Sanctum::actingAs(auth()->user());
        $role = ClubRole::create(['name' => 'Board', 'is_board' => true]);
        $member = Member::factory()->create();
        $member->clubRoles()->attach($role);
        $this->putJson('/api/v1/members/'.$member->id, ['member_number' => $member->member_number, 'firstname' => $member->firstname, 'lastname' => $member->lastname, 'joined_at' => $member->joined_at->toDateString(), 'phone' => '123456'])->assertOk();
        $this->assertSame(1, $member->clubRoles()->count());
    }

    #[Test]
    public function anonymized_members_remove_personal_free_text_from_record_and_history(): void
    {
        $member = Member::factory()->create(['left_at' => '2020-01-01', 'notiz' => 'Contact FormerPerson at personal-review@example.test']);
        $this->post('/members/'.$member->id.'/anonymize', ['confirmation' => 'ANONYMISIEREN'])->assertRedirect();
        $this->assertNotNull($member->fresh()->anonymized_at);
        $this->get('/members/'.$member->id.'/edit')->assertOk()->assertDontSee('personal-review@example.test');
        $this->assertNull($member->fresh()->notiz);
        $this->assertArrayNotHasKey('notiz', MemberChange::where('member_id', $member->id)->first()->before);
    }

    #[Test]
    public function new_members_with_a_category_receive_their_first_due(): void
    {
        $category = FeeCategory::create(['name' => 'Standard']);
        $year = $this->year(2026);
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $year->id, 'amount' => 120]);
        $member = Member::factory()->create(['fee_category_id' => $category->id]);
        $this->post('/members/dues/generate', ['fiscal_year_id' => $year->id])->assertRedirect();
        $this->assertSame(1, $member->dues()->count());
    }

    #[Test]
    public function the_payment_workflow_is_reachable_from_member_details(): void
    {
        $member = Member::factory()->create();
        $this->get('/members/'.$member->id.'/payments/create')->assertOk();
        $this->get('/members/'.$member->id.'/edit')->assertOk()->assertSee(route('members.payments.create', $member), false);
    }

    #[Test]
    public function invalid_addresses_are_reported_before_import_or_sending(): void
    {
        config(['mail.default' => 'array', 'queue.default' => 'sync', 'mail.from.address' => 'review@example.test', 'mail.from.name' => 'Review']);
        $path = __DIR__.'/members-invalid-review.csv';
        $this->csvPaths[] = $path;
        file_put_contents($path, "Name,Vorname,Email\nInvalid,Review,invalid-email\n");
        $this->artisan('members:import-csv', ['file' => $path])->assertFailed();
        $this->assertSame(0, Member::count());
        Member::factory()->create(['email' => 'invalid-email']);
        $this->post('/members/mail/send', ['audience' => 'all', 'subject' => 'Review only', 'body' => 'In-memory transport only'])->assertSessionHasErrors('audience');
        $this->assertSame(0, MailSend::count());
    }

    #[Test]
    public function a_valid_address_control_uses_only_the_array_transport_successfully(): void
    {
        config(['mail.default' => 'array', 'queue.default' => 'sync', 'mail.from.address' => 'review@example.test', 'mail.from.name' => 'Review']);
        $this->import('', 'valid-review@example.test');
        $this->post('/members/mail/send', ['audience' => 'all', 'subject' => 'Review only', 'body' => 'In-memory transport only'])->assertRedirect();
        $this->assertSame(1, MailSend::count());
    }

    #[Test]
    public function role_only_edits_record_names_and_ids_in_history(): void
    {
        $a = ClubRole::create(['name' => 'Old role', 'is_board' => false]);
        $b = ClubRole::create(['name' => 'New board role', 'is_board' => true]);
        $member = Member::factory()->create();
        $member->clubRoles()->attach($a);
        $this->put('/members/'.$member->id, ['member_number' => $member->member_number, 'firstname' => $member->firstname, 'lastname' => $member->lastname, 'joined_at' => $member->joined_at->toDateString(), 'club_roles' => [$b->id]])->assertRedirect();
        $this->assertSame([$b->id], $member->clubRoles()->pluck('club_roles.id')->all());
        $change = MemberChange::where('member_id', $member->id)->firstOrFail();
        $this->assertStringContainsString('Old role', $change->before['club_roles']);
        $this->assertStringContainsString('New board role', $change->after['club_roles']);
        $this->assertSame('club_roles', $change->changedFields()[0]['field']);
        $this->get('/members/'.$member->id.'/edit')->assertOk()->assertSee('Old role')->assertSee('New board role');
    }
}
