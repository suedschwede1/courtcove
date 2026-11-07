<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Mail\MemberBulkMail;
use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Rundmail "Beitrag {Jahr} offen" (decision 4): for household dues only the paying person; households without one are counted. */
class HouseholdMailAudienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $family;

    private FeeCategory $adults;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $this->adults = FeeCategory::create(['name' => 'Erwachsene']);
    }

    private function member(string $first, string $email): Member
    {
        return Member::factory()->create(['firstname' => $first, 'lastname' => 'Muster', 'email' => $email]);
    }

    /** @param  list<Member>  $members */
    private function household(array $members, ?Member $payer): MemberHousehold
    {
        $service = app(HouseholdService::class);
        $household = $service->create(array_map(fn (Member $m) => $m->id, $members), $this->family->id, null);
        if ($payer !== null) {
            $service->setPayer($household, $payer);
        }
        $due = HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
        foreach ($members as $member) {
            $due->coveredMembers()->attach($member->id, ['fiscal_year_id' => $this->year->id]);
        }

        return $household;
    }

    #[Test]
    public function open_dues_audience_mails_only_payers_and_counts_households_without_payer(): void
    {
        Mail::fake();
        $anna = $this->member('Anna', 'anna@example.test');
        $ben = $this->member('Ben', 'ben@example.test');
        $this->household([$anna, $ben], $anna);
        $clara = $this->member('Clara', 'clara@example.test');
        $this->household([$clara, $this->member('Carl', 'carl@example.test')], null);
        $dora = $this->member('Dora', 'dora@example.test');
        MemberDue::create(['member_id' => $dora->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150]);
        $emil = $this->member('Emil', 'emil@example.test');
        MemberDue::create(['member_id' => $emil->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150, 'status' => 'bezahlt']);
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->get('/members/mail')
            ->assertOk()
            ->assertSee(__('members::admin.mail.to_open_dues', ['year' => '2026']), false)
            ->assertViewHas('audienceOptions', fn ($options) => $options->firstWhere('key', 'open_dues')['note'] === __('members::admin.mail.households_without_payer', ['count' => 1])
                && $options->firstWhere('key', 'open_dues')['count'] === 2);

        $this->actingAs($admin)->post('/members/mail/send', ['audience' => 'open_dues', 'subject' => 'Beitrag', 'body' => 'Text'])->assertRedirect();

        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail): bool {
            $bcc = collect($mail->to)->merge($mail->bcc)->pluck('address')->filter(fn ($a) => str_ends_with((string) $a, '@example.test'))->sort()->values()->all();

            return $bcc === ['anna@example.test', 'dora@example.test'];
        });
    }

    #[Test]
    public function a_paid_household_due_mails_nobody(): void
    {
        $anna = $this->member('Anna', 'anna@example.test');
        $household = $this->household([$anna, $this->member('Ben', 'ben@example.test')], $anna);
        HouseholdDue::where('household_id', $household->id)->update(['status' => 'bezahlt']);

        $this->actingAs(User::factory()->create(['status' => 'admin']))->get('/members/mail')
            ->assertOk()
            ->assertViewHas('audienceOptions', fn ($options) => $options->firstWhere('key', 'open_dues')['count'] === 0
                && $options->firstWhere('key', 'open_dues')['note'] === null);
    }
}
