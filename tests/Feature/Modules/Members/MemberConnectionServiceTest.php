<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Support\HouseholdRuleException;
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\MemberConnectionException;
use App\Modules\Members\Support\MemberConnectionService;
use App\Modules\Members\Support\MemberConnectionWarnings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Anschlussmitglied: a member with the Anschluss category is connected directly to one Vollzahler (exactly 2 persons),
 * logged on both sides; invalid states are only reported (warnings), never changed silently.
 */
class MemberConnectionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FeeCategory $full;

    private FeeCategory $partner;

    private FeeCategory $youth;

    private MemberConnectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->full = FeeCategory::create(['name' => 'Vollzahler', 'connection_role' => 'vollzahler']);
        $this->partner = FeeCategory::create(['name' => 'Ehepartner/in, Lebensgefährte/in', 'connection_role' => 'anschluss']);
        $this->youth = FeeCategory::create(['name' => 'Jugend']);
        $this->service = app(MemberConnectionService::class);
    }

    private function heinz(array $extra = []): Member
    {
        return Member::factory()->create([...['firstname' => 'Heinz', 'lastname' => 'Muster', 'fee_category_id' => $this->full->id], ...$extra]);
    }

    private function helga(array $extra = []): Member
    {
        return Member::factory()->create([...['firstname' => 'Helga', 'lastname' => 'Beispiel', 'fee_category_id' => $this->partner->id], ...$extra]);
    }

    #[Test]
    public function an_anschluss_member_is_connected_to_a_vollzahler_and_both_sides_see_it(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'admin']));
        [$heinz, $helga] = [$this->heinz(), $this->helga()];

        $this->service->connect($helga, $heinz);

        $helga = $helga->fresh();
        $this->assertSame($heinz->id, $helga?->connected_to_member_id);
        $this->assertTrue($helga?->connectedTo?->is($heinz));
        $this->assertSame([$helga->id], $heinz->fresh()?->anschlussMembers()->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($heinz->id, MemberChange::where('member_id', $helga->id)->latest('id')->first()?->after['connected_to_member_id']);
        $this->assertSame(1, MemberChange::where('member_id', $heinz->id)->where('action', 'connection')->count());
        $this->assertSame([], MemberConnectionWarnings::forMember($helga));
        $this->assertSame([], MemberConnectionWarnings::forMember($heinz->fresh() ?? $heinz));
    }

    #[Test]
    public function the_rules_keep_it_to_exactly_two_persons(): void
    {
        $heinz = $this->heinz();
        $helga = $this->helga();
        $this->service->connect($helga, $heinz);

        $this->assertThrows(fn () => $this->service->connect($this->helga(['firstname' => 'Hanna']), $heinz->fresh() ?? $heinz), MemberConnectionException::class);
        $this->assertThrows(fn () => $this->service->connect($this->helga(), $helga->fresh() ?? $helga), MemberConnectionException::class);
        $this->assertThrows(fn () => $this->service->connect($heinz->fresh() ?? $heinz, $heinz->fresh() ?? $heinz), MemberConnectionException::class);
        $this->assertSame(1, Member::whereNotNull('connected_to_member_id')->count());
    }

    #[Test]
    public function a_connection_and_a_familientarif_household_exclude_each_other(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $households = app(HouseholdService::class);
        [$heinz, $helga] = [$this->heinz(), $this->helga()];
        $household = $households->create([$heinz->id], $family->id, null);

        $this->assertThrows(fn () => $this->service->connect($helga, $heinz->fresh() ?? $heinz), MemberConnectionException::class);

        $households->removeMember($household, $heinz->fresh() ?? $heinz);
        $this->service->connect($helga, $heinz->fresh() ?? $heinz);
        $this->assertThrows(fn () => $households->addMember($household, $helga->fresh() ?? $helga), HouseholdRuleException::class);
        $this->assertThrows(fn () => $households->addMember($household, $heinz->fresh() ?? $heinz), HouseholdRuleException::class);

        $grouping = $households->create([], null, null);
        $households->addMember($grouping, $helga->fresh() ?? $helga);
        $this->assertThrows(fn () => $households->updateDetails($grouping->fresh() ?? $grouping, null, $family->id), HouseholdRuleException::class);
    }

    #[Test]
    public function disconnecting_clears_the_link_and_is_logged(): void
    {
        [$heinz, $helga] = [$this->heinz(), $this->helga()];
        $this->service->connect($helga, $heinz);

        $this->service->disconnect($helga->fresh() ?? $helga);

        $this->assertNull($helga->fresh()?->connected_to_member_id);
        $this->assertSame(2, MemberChange::where('member_id', $heinz->id)->where('action', 'connection')->count());
    }

    #[Test]
    public function invalid_states_are_reported_not_changed(): void
    {
        [$heinz, $helga] = [$this->heinz(), $this->helga()];
        $this->service->connect($helga, $heinz);
        $lonely = $this->helga(['firstname' => 'Lena']);

        // Categories are not checked any more (club decision 2026-11-08): a category change gives no warning.
        $heinz->update(['fee_category_id' => $this->youth->id]);
        $this->assertSame([], MemberConnectionWarnings::forMember($helga->fresh() ?? $helga));
        $this->assertSame([], MemberConnectionWarnings::forMember($heinz->fresh() ?? $heinz));

        // A departure written past the departure flow (old data) is still reported; the form/inline flow ends the link.
        $heinz->fresh()?->setLeftAt('2026-06-30');
        $this->assertContains(__('members::admin.connection.warnings.partner_departed', ['name' => 'Heinz Muster']), MemberConnectionWarnings::forMember($helga->fresh() ?? $helga));
        $this->assertSame($heinz->id, $helga->fresh()?->connected_to_member_id);

        $this->assertContains(__('members::admin.connection.warnings.anschluss_without_connection'), MemberConnectionWarnings::forMember($lonely));
    }

    #[Test]
    public function two_anschluss_members_on_one_vollzahler_from_old_data_are_reported(): void
    {
        $heinz = $this->heinz();
        foreach (['Helga', 'Hanna'] as $first) {
            $this->helga(['firstname' => $first])->forceFill(['connected_to_member_id' => $heinz->id])->save();
        }

        $warnings = MemberConnectionWarnings::forMember($heinz->fresh() ?? $heinz);

        $this->assertContains(__('members::admin.connection.warnings.too_many_anschluss', ['names' => 'Hanna Beispiel, Helga Beispiel']), $warnings);
        $this->assertArrayHasKey($heinz->id, MemberConnectionWarnings::all());
    }

    #[Test]
    public function anonymising_removes_the_link_in_the_same_protocol_entry(): void
    {
        [$heinz, $helga] = [$this->heinz(), $this->helga(['status' => 'ausgetreten', 'left_at' => '2020-01-01'])];
        $helga->forceFill(['connected_to_member_id' => $heinz->id])->save();

        $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->post("/members/{$helga->id}/anonymize", ['confirmation' => 'ANONYMISIEREN'])->assertRedirect();

        $this->assertNull($helga->fresh()?->connected_to_member_id);
        $change = MemberChange::where('member_id', $helga->id)->latest('id')->first();
        $this->assertSame('anonymized', $change?->action);
        $this->assertSame($heinz->id, $change?->before['connected_to_member_id']);
        // The Vollzahler's protocol records that it lost its Anschlussmitglied.
        $vollzahlerChange = MemberChange::where('member_id', $heinz->id)->where('action', 'connection')->latest('id')->first();
        $this->assertNotNull($vollzahlerChange);
        $this->assertSame($helga->id, $vollzahlerChange->before['anschluss_member']);
        $this->assertNull($vollzahlerChange->after['anschluss_member']);
    }

    #[Test]
    public function anonymising_the_vollzahler_removes_the_link_of_the_partner_with_a_log_entry(): void
    {
        [$heinz, $helga] = [$this->heinz(['status' => 'ausgetreten', 'left_at' => '2020-01-01']), $this->helga()];
        $helga->forceFill(['connected_to_member_id' => $heinz->id])->save();

        $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->post("/members/{$heinz->id}/anonymize", ['confirmation' => 'ANONYMISIEREN'])->assertRedirect();

        $this->assertNull($helga->fresh()?->connected_to_member_id);
        $change = MemberChange::where('member_id', $helga->id)->latest('id')->first();
        $this->assertNotNull($change);
        $this->assertSame($heinz->id, $change->before['connected_to_member_id']);
        $this->assertArrayHasKey('connected_to_member_id', $change->after);
        $this->assertNull($change->after['connected_to_member_id']);
        $this->assertSame(1, MemberChange::where('member_id', $helga->id)->count());
    }

    #[Test]
    public function a_departed_anschluss_member_is_reported_on_the_vollzahler_and_in_the_run(): void
    {
        [$heinz, $helga] = [$this->heinz(), $this->helga()];
        $this->service->connect($helga, $heinz);

        $helga->fresh()?->setLeftAt('2026-06-30');

        $this->assertContains(__('members::admin.connection.warnings.anschluss_departed', ['name' => 'Helga Beispiel']), MemberConnectionWarnings::forMember($heinz->fresh() ?? $heinz));
        $this->assertArrayHasKey($heinz->id, MemberConnectionWarnings::all());
    }

    #[Test]
    public function a_familientarif_household_can_be_renamed_despite_an_old_link_but_not_given_a_tariff_change(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $other = FeeCategory::create(['name' => 'Familie 2', 'billing_unit' => 'household']);
        $households = app(HouseholdService::class);
        [$heinz, $helga] = [$this->heinz(), $this->helga()];
        $household = $households->create([$heinz->id], $family->id, null);
        $helga->forceFill(['connected_to_member_id' => $heinz->id])->save(); // old, invalid data

        $households->updateDetails($household->fresh() ?? $household, 'Familie Muster', $family->id);
        $this->assertSame('Familie Muster', $household->fresh()?->name);

        $this->assertThrows(fn () => $households->updateDetails($household->fresh() ?? $household, 'Familie Muster', $other->id), HouseholdRuleException::class);
    }

    #[Test]
    public function connected_to_member_id_is_not_mass_assignable(): void
    {
        [$heinz, $helga] = [$this->heinz(), $this->helga()];

        $helga->update(['connected_to_member_id' => $heinz->id]);

        $this->assertNull($helga->fresh()?->connected_to_member_id);
    }
}
