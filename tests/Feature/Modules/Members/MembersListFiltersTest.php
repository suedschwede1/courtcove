<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Server-side quick filters of the members list (chips Alle / Aktiv / Ruhend / Jugend / Beitrag offen, plus departed
 * members), combined with the search, the more filters and the pager; input is validated and never fails.
 */
class MembersListFiltersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    private FiscalYear $fiscalYear;

    private FeeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['status' => 'admin']);
        $this->fiscalYear = FiscalYear::create(['name' => 'Laufend', 'starts_on' => now()->subMonths(2)->toDateString(), 'ends_on' => now()->addMonths(10)->toDateString()]);
        $this->category = FeeCategory::create(['name' => 'Erwachsen']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function member(string $lastname, array $attributes = []): Member
    {
        return Member::factory()->create(array_merge([
            'firstname' => 'Test',
            'lastname' => $lastname,
            'birthdate' => now()->subYears(40)->toDateString(),
            'email' => strtolower($lastname).'@example.com',
            'status' => 'aktiv',
        ], $attributes));
    }

    private function due(Member $member, string $status, ?FiscalYear $fiscalYear = null): MemberDue
    {
        return MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => ($fiscalYear ?? $this->fiscalYear)->id,
            'fee_category_id' => $this->category->id,
            'amount' => 120,
            'status' => $status,
        ]);
    }

    /** The fixture: one member per rule, named after what it tests. */
    private function fixture(): void
    {
        $this->member('Aktiv');
        $this->member('Ruhend', ['status' => 'ruhend']);
        $this->member('Weg', ['status' => 'ausgetreten', 'left_at' => '2025-12-31']);
        $this->member('Jugend', ['birthdate' => now()->subYears(18)->toDateString()]);
        $this->member('Neunzehn', ['birthdate' => now()->subYears(19)->toDateString()]);
        $this->member('Ohnegeburtstag', ['birthdate' => null]);
        $this->member('Ruhendjung', ['status' => 'ruhend', 'birthdate' => now()->subYears(12)->toDateString()]);
        $this->member('Wegjung', ['status' => 'ausgetreten', 'left_at' => '2025-12-31', 'birthdate' => now()->subYears(12)->toDateString()]);

        $this->due($this->member('Offen'), 'ausstehend');
        $this->due($this->member('Ueberfaellig'), 'ueberfaellig');
        $old = FiscalYear::create(['name' => 'Alt', 'starts_on' => now()->subYears(2)->toDateString(), 'ends_on' => now()->subYear()->toDateString()]);
        $this->due($this->member('Altoffen'), 'ausstehend', $old);
        $this->due($this->member('Bezahlt'), 'bezahlt');
        $paidByPayment = $this->member('Ueberwiesen');
        $due = $this->due($paidByPayment, 'ausstehend');
        $payment = Payment::create(['member_id' => $paidByPayment->id, 'amount' => 120, 'paid_at' => now()->toDateString()]);
        $payment->dues()->attach($due->id);
        $this->due($this->member('Wegoffen', ['status' => 'ausgetreten', 'left_at' => '2025-12-31']), 'ausstehend');
    }

    /** @return list<string> Last names in the rendered table, in order. */
    private function names(string $url): array
    {
        $html = (string) $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        if (! str_contains($html, '<tbody>')) {
            return [];
        }
        $body = substr($html, (int) strpos($html, '<tbody>'), (int) strpos($html, '</tbody>') - (int) strpos($html, '<tbody>'));
        preg_match_all('#<span x-show="!editing" x-text="lastname \+ \', \' \+ firstname" class="mbr-name"[^>]*>([^,<]+), #', $body, $m);

        return $m[1];
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function chips(): array
    {
        $current = ['Aktiv', 'Altoffen', 'Bezahlt', 'Jugend', 'Neunzehn', 'Offen', 'Ohnegeburtstag', 'Ruhend', 'Ruhendjung', 'Ueberfaellig', 'Ueberwiesen'];

        return [
            'default' => ['/members', $current],
            'alle' => ['/members?filter=alle', $current],
            'aktiv' => ['/members?filter=aktiv', ['Aktiv', 'Altoffen', 'Bezahlt', 'Jugend', 'Neunzehn', 'Offen', 'Ohnegeburtstag', 'Ueberfaellig', 'Ueberwiesen']],
            'ruhend' => ['/members?filter=ruhend', ['Ruhend', 'Ruhendjung']],
            'jugend (18 or younger, current members, with birth date)' => ['/members?filter=jugend', ['Jugend', 'Ruhendjung']],
            'offen (any open due without payment, current members)' => ['/members?filter=offen', ['Altoffen', 'Offen', 'Ueberfaellig']],
            'ausgetreten' => ['/members?filter=ausgetreten', ['Weg', 'Wegjung', 'Wegoffen']],
            'unknown chip falls back to alle' => ['/members?filter=bogus', $current],
            'array chip falls back to alle' => ['/members?filter[]=ruhend', $current],
            'legacy status=ruhend' => ['/members?status=ruhend', ['Ruhend', 'Ruhendjung']],
            'legacy status=all' => ['/members?status=all', $current],
            'filter wins over legacy status' => ['/members?filter=aktiv&status=ruhend', ['Aktiv', 'Altoffen', 'Bezahlt', 'Jugend', 'Neunzehn', 'Offen', 'Ohnegeburtstag', 'Ueberfaellig', 'Ueberwiesen']],
        ];
    }

    /** @param  list<string>  $expected */
    #[Test]
    #[DataProvider('chips')]
    public function each_chip_filters_on_the_server(string $url, array $expected): void
    {
        $this->fixture();

        $this->assertSame($expected, $this->names($url));
    }

    #[Test]
    public function the_active_chip_carries_the_count_of_the_result(): void
    {
        $this->fixture();

        $html = (string) $this->actingAs($this->admin)->get('/members?filter=offen')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#class="ds-chip"[^>]*aria-current="true"[^>]*>\s*Beitrag offen \(3\)\s*</a>#', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
        $this->assertStringContainsString('1–3 von 3', $html);
    }

    #[Test]
    public function the_departed_filter_shows_its_own_chip_and_hides_the_link(): void
    {
        $this->fixture();

        $html = (string) $this->actingAs($this->admin)->get('/members?filter=ausgetreten')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#class="ds-chip"[^>]*aria-current="true"[^>]*>\s*Ausgetreten \(3\)\s*</a>#', $html);
        $this->assertStringNotContainsString('Ausgetretene Mitglieder anzeigen', $html);
        $this->assertStringContainsString('<input type="hidden" name="filter" value="ausgetreten">', $html);
    }

    #[Test]
    public function search_matches_name_words_member_number_and_email(): void
    {
        $this->member('Muster', ['firstname' => 'Anna', 'member_number' => 47, 'email' => 'anna.m@example.com']);
        $this->member('Muster', ['firstname' => 'Bernd', 'member_number' => 48, 'email' => 'b@example.com']);
        $this->member('Alt', ['firstname' => 'Anna', 'member_number' => 470, 'email' => 'alt@example.com']);

        $this->assertSame(['Muster'], $this->names('/members?search=Anna+Muster'));
        $this->assertSame(['Muster'], $this->names('/members?search=0047'));
        $this->assertSame(['Muster'], $this->names('/members?search=anna.m%40'));
        $this->assertSame(['Alt', 'Muster'], $this->names('/members?search=anna'));
    }

    #[Test]
    public function chips_combine_with_search_and_the_pager_keeps_everything(): void
    {
        foreach (range(1, 27) as $i) {
            $this->member('Huber', ['firstname' => sprintf('Kind%02d', $i), 'birthdate' => now()->subYears(10)->toDateString()]);
        }
        $this->member('Huber', ['firstname' => 'Alt']);
        $this->member('Maier', ['firstname' => 'Jung', 'birthdate' => now()->subYears(10)->toDateString()]);

        $page1 = (string) $this->actingAs($this->admin)->get('/members?filter=jugend&search=Huber')->assertOk()->getContent();
        $this->assertStringContainsString('1–25 von 27', $page1);
        $this->assertMatchesRegularExpression('#class="ds-chip"[^>]*aria-current="true"[^>]*>\s*Jugend \(27\)\s*</a>#', $page1);

        preg_match('/<a class="ds-pagination__item" href="([^"]*page=2[^"]*)"/', $page1, $next);
        $this->assertNotEmpty($next);
        $nextUrl = html_entity_decode($next[1]);
        $this->assertStringContainsString('filter=jugend', $nextUrl);
        $this->assertStringContainsString('search=Huber', $nextUrl);

        $page2 = (string) $this->actingAs($this->admin)->get($nextUrl)->assertOk()->getContent();
        $this->assertStringContainsString('26–27 von 27', $page2);
        $this->assertSame(['Huber', 'Huber'], $this->names($nextUrl));

        // Chip links keep the search and the more filters but start again at page 1.
        preg_match('#<a href="([^"]*)" class="ds-chip"[^>]*>\s*Aktiv\s*</a>#', $page2, $chip);
        $chipUrl = html_entity_decode($chip[1]);
        $this->assertStringContainsString('filter=aktiv', $chipUrl);
        $this->assertStringContainsString('search=Huber', $chipUrl);
        $this->assertStringNotContainsString('page=', $chipUrl);
        // The "Alle" chip drops the default value from the URL.
        preg_match('#<a href="([^"]*)" class="ds-chip"[^>]*>\s*Alle\s*</a>#', $page2, $all);
        $this->assertSame(route('members.index', ['search' => 'Huber']), html_entity_decode($all[1]));
    }

    #[Test]
    public function chips_combine_with_the_more_filters(): void
    {
        $other = FeeCategory::create(['name' => 'Senior']);
        $this->due($this->member('Erwachsenoffen', ['fee_category_id' => $this->category->id]), 'ueberfaellig');
        $this->due($this->member('Senioroffen', ['fee_category_id' => $other->id]), 'ausstehend');
        $this->member('Seniorbezahlt', ['fee_category_id' => $other->id]);

        $this->assertSame(['Senioroffen'], $this->names("/members?filter=offen&fee_category={$other->id}"));
        $this->assertSame(['Erwachsenoffen'], $this->names('/members?filter=offen&zahlung=ueberfaellig'));

        $html = (string) $this->actingAs($this->admin)->get("/members?filter=offen&fee_category={$other->id}")->getContent();
        preg_match('#<a href="([^"]*)" class="ds-chip"[^>]*>\s*Ruhend\s*</a>#', $html, $chip);
        $this->assertStringContainsString('fee_category='.$other->id, html_entity_decode($chip[1]));
    }

    /** @return array<string, array{0: string}> */
    public static function garbage(): array
    {
        return [
            'array search' => ['/members?search[]=x'],
            'array fee category' => ['/members?fee_category[]=1'],
            'text fee category' => ['/members?fee_category=abc'],
            'negative fee category' => ['/members?fee_category=-3'],
            'array due status' => ['/members?zahlung[]=bezahlt'],
            'unknown due status' => ['/members?zahlung=egal'],
            'array page' => ['/members?page[]=2'],
            'array status' => ['/members?status[]=ruhend'],
            'nested filter' => ['/members?filter[a][b]=c'],
            'huge number search' => ['/members?search=99999999999999999999'],
            'long search' => ['/members?search='.str_repeat('a', 500)],
            'like wildcards' => ['/members?search=%25_%25'],
        ];
    }

    #[Test]
    #[DataProvider('garbage')]
    public function garbage_input_never_fails(string $url): void
    {
        $this->member('Aktiv');

        $html = (string) $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('class="mbr-list-filters"', $html);
    }

    #[Test]
    public function an_overlong_search_is_cut_to_100_characters(): void
    {
        $html = (string) $this->actingAs($this->admin)->get('/members?search='.str_repeat('b', 300))->assertOk()->getContent();

        $this->assertStringContainsString('value="'.str_repeat('b', 100).'"', $html);
        $this->assertStringNotContainsString(str_repeat('b', 101), $html);
    }

    #[Test]
    public function the_query_count_does_not_grow_with_the_members(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get('/members?filter=alle')->assertOk();
            $this->actingAs($this->admin)->get('/members?filter=offen')->assertOk();
            $this->actingAs($this->admin)->get('/members?filter=jugend&zahlung=ausstehend')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $seed = function (int $n, int $offset): void {
            foreach (range(1, $n) as $i) {
                $user = User::factory()->create();
                $member = $this->member('Name'.($offset + $i), [
                    'fee_category_id' => $this->category->id,
                    'user_id' => $i % 2 === 0 ? $user->uid : null,
                    'birthdate' => now()->subYears(10 + $i)->toDateString(),
                ]);
                $this->due($member, $i % 3 === 0 ? 'bezahlt' : 'ausstehend');
            }
        };

        $seed(3, 0);
        $small = $count();
        $seed(20, 100);
        $large = $count();

        $this->assertSame($small, $large);
    }

    #[Test]
    public function the_export_ignores_the_list_filters(): void
    {
        $this->fixture();

        $all = $this->actingAs($this->admin)->get('/members/export')->assertOk()->streamedContent();
        $filtered = $this->actingAs($this->admin)->get('/members/export?filter=ruhend&search=x')->assertOk()->streamedContent();

        $this->assertSame($all, $filtered);
        $this->assertStringContainsString('Weg', $all);
        $this->assertStringContainsString('Aktiv', $all);
    }
}
