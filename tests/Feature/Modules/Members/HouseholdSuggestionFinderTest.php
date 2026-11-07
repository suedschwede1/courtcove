<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Models\HouseholdSuggestionDismissal;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdSuggestionFinder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Mögliche Familien" (decision 8): suggestions from shared address, e-mail or phone; read-only, never automatic. */
class HouseholdSuggestionFinderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(string $first, string $last, ?string $street = null, ?string $zip = null, ?string $email = null, ?string $phone = null, array $extra = []): Member
    {
        return Member::factory()->create([...['firstname' => $first, 'lastname' => $last, 'street' => $street, 'zip' => $zip, 'email' => $email, 'phone' => $phone], ...$extra]);
    }

    #[Test]
    public function members_sharing_an_address_form_one_suggestion(): void
    {
        $anna = $this->member('Anna', 'Muster', street: 'Musterweg 1', zip: '1000');
        $ben = $this->member('Ben', 'Muster', street: 'musterweg  1', zip: '1000');
        $this->member('Dora', 'Beispiel', street: 'Probeweg 2', zip: '2000');

        $suggestions = app(HouseholdSuggestionFinder::class)->find();

        $this->assertCount(1, $suggestions);
        $this->assertSame([$anna->id, $ben->id], $suggestions[0]->memberIds);
        $this->assertSame(['address', 'lastname'], $suggestions[0]->reasons);
        $this->assertSame(HouseholdSuggestionFinder::signature([$ben->id, $anna->id]), $suggestions[0]->signature);
    }

    #[Test]
    public function street_abbreviations_are_normalised(): void
    {
        $this->member('Anna', 'Muster', street: 'Hauptstr. 5', zip: '1000');
        $this->member('Emil', 'Probe', street: 'Hauptstraße 5', zip: '1000');

        $this->assertSame(['address'], app(HouseholdSuggestionFinder::class)->find()[0]->reasons);
    }

    #[Test]
    public function phone_numbers_match_across_national_and_international_format(): void
    {
        $this->member('Anna', 'Muster', phone: '0664 1234567');
        $this->member('Emil', 'Probe', phone: '+43 664 1234567');

        $this->assertSame(['phone'], app(HouseholdSuggestionFinder::class)->find()[0]->reasons);
    }

    #[Test]
    public function email_matches_case_insensitive_and_groups_are_connected(): void
    {
        $anna = $this->member('Anna', 'Muster', email: 'familie@example.test');
        $ben = $this->member('Ben', 'Muster', email: 'Familie@Example.test ', phone: '0664 9999999');
        $clara = $this->member('Clara', 'Probe', phone: '06649999999');

        $suggestion = app(HouseholdSuggestionFinder::class)->find()[0];

        $this->assertSame([$anna->id, $ben->id, $clara->id], $suggestion->memberIds);
        $this->assertSame(['email', 'phone', 'lastname'], $suggestion->reasons);
    }

    #[Test]
    public function same_last_name_alone_is_never_a_suggestion(): void
    {
        $this->member('Anna', 'Muster');
        $this->member('Max', 'Muster');

        $this->assertCount(0, app(HouseholdSuggestionFinder::class)->find());
    }

    #[Test]
    public function departed_anonymised_and_already_grouped_members_are_ignored_and_dismissals_hide_groups(): void
    {
        $this->member('Gone', 'Muster', street: 'Musterweg 1', zip: '1000', extra: ['status' => 'ausgetreten', 'left_at' => '2025-01-01']);
        $this->member('Anon', 'X', street: 'Musterweg 1', zip: '1000', extra: ['anonymized_at' => now()]);
        $grouped = $this->member('Grouped', 'Muster', street: 'Musterweg 1', zip: '1000');
        $grouped->forceFill(['household_id' => MemberHousehold::create([])->id])->save();
        $a = $this->member('Anna', 'Muster', street: 'Musterweg 1', zip: '1000');
        $this->assertCount(0, app(HouseholdSuggestionFinder::class)->find());

        $b = $this->member('Ben', 'Muster', street: 'Musterweg 1', zip: '1000');
        $this->assertCount(1, app(HouseholdSuggestionFinder::class)->find());

        HouseholdSuggestionDismissal::create(['signature' => HouseholdSuggestionFinder::signature([$a->id, $b->id])]);
        $this->assertCount(0, app(HouseholdSuggestionFinder::class)->find());
    }

    #[Test]
    public function finder_never_creates_a_household(): void
    {
        $this->member('Anna', 'Muster', email: 'familie@example.test');
        $this->member('Ben', 'Muster', email: 'Familie@Example.test ');

        app(HouseholdSuggestionFinder::class)->find();

        $this->assertSame(0, MemberHousehold::count());
        $this->assertSame(0, Member::whereNotNull('household_id')->count());
        $this->assertSame(0, HouseholdSuggestionDismissal::count());
    }

    #[Test]
    public function suggestions_with_more_reasons_come_first(): void
    {
        $this->member('Dora', 'Beispiel', phone: '0664 1111111');
        $this->member('Emil', 'Probe', phone: '0664 1111111');
        $this->member('Anna', 'Muster', street: 'Musterweg 1', zip: '1000', email: 'm@example.test');
        $this->member('Ben', 'Muster', street: 'Musterweg 1', zip: '1000', email: 'm@example.test');

        $suggestions = app(HouseholdSuggestionFinder::class)->find();

        $this->assertSame(['address', 'email', 'lastname'], $suggestions[0]->reasons);
        $this->assertSame(['phone'], $suggestions[1]->reasons);
    }
}
