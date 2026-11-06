<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug report: on /members/dues the business year kept jumping back to an older year and
 * outstanding dues of a later year (2027) could not be listed. These tests pin which year is
 * shown, that it survives filters, paging, actions and navigation, and that odd input is safe.
 */
class MemberDueYearSelectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<int, FiscalYear> */
    private array $years = [];

    private FeeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** Calendar years 2025, 2026, 2027 with due date 31 March. */
    private function seedYears(): void
    {
        $this->category = FeeCategory::create(['name' => 'Erwachsene']);
        foreach ([2025, 2026, 2027] as $y) {
            $this->years[$y] = FiscalYear::create(['name' => (string) $y, 'starts_on' => "{$y}-01-01", 'ends_on' => "{$y}-12-31", 'faellig_am' => "{$y}-03-31"]);
        }
    }

    private function due(int $year, string $firstname, string $status = 'ausstehend'): MemberDue
    {
        $member = Member::factory()->create(['firstname' => $firstname, 'lastname' => 'Beispiel']);

        return MemberDue::create([
            'member_id' => $member->id, 'fiscal_year_id' => $this->years[$year]->id, 'fee_category_id' => $this->category->id,
            'amount' => 100, 'status' => $status, 'faellig_am' => $this->years[$year]->faellig_am,
        ]);
    }

    private function assertYearSelected(TestResponse $response, FiscalYear $year): void
    {
        $response->assertSee('<option value="'.$year->id.'" selected>'.$year->name.'</option>', false);
    }

    private function filterSelect(TestResponse $response): string
    {
        preg_match('/<select name="fiscal_year_id" id="due-fiscal-year".*?<\/select>/s', $response->getContent(), $m);

        return $m[0] ?? '';
    }

    #[Test]
    public function the_filter_select_has_its_own_id_and_marks_the_requested_year(): void
    {
        $this->seedYears();
        $this->due(2027, 'Zukunft');

        $response = $this->actingAs($this->admin())->get('/members/dues?fiscal_year_id='.$this->years[2027]->id)->assertOk();

        $this->assertStringContainsString('<option value="'.$this->years[2027]->id.'" selected>2027</option>', $this->filterSelect($response));
        $response->assertSee('>Zukunft Beispiel</td>', false);
    }

    /** @return array<string, array{0: string, 1: list<string>, 2: list<string>}> */
    public static function filterCombinations(): array
    {
        return [
            'no status' => ['', ['Offen27', 'Bezahlt27'], ['Offen26']],
            'ausstehend' => ['ausstehend', ['Offen27'], ['Bezahlt27', 'Offen26']],
            'offen' => ['offen', ['Offen27'], ['Bezahlt27', 'Offen26']],
            'bezahlt' => ['bezahlt', ['Bezahlt27'], ['Offen27']],
            'ueberfaellig' => ['ueberfaellig', [], ['Offen27', 'Bezahlt27']],
        ];
    }

    /**
     * @param  list<string>  $see
     * @param  list<string>  $dontSee
     */
    #[Test]
    #[DataProvider('filterCombinations')]
    public function a_later_year_is_listed_with_every_status_filter(string $status, array $see, array $dontSee): void
    {
        $this->seedYears();
        $this->due(2027, 'Offen27');
        $this->due(2027, 'Bezahlt27', 'bezahlt');
        $this->due(2026, 'Offen26');

        $response = $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2027]->id.'&status='.$status.'&search=')
            ->assertOk();

        $this->assertStringContainsString('value="'.$this->years[2027]->id.'" selected', $this->filterSelect($response));
        foreach ($see as $name) {
            $response->assertSee('>'.$name.' Beispiel</td>', false);
        }
        foreach ($dontSee as $name) {
            $response->assertDontSee('>'.$name.' Beispiel</td>', false);
        }
    }

    #[Test]
    public function the_offen_filter_lists_every_unpaid_due_of_the_year_overdue_or_not(): void
    {
        $this->seedYears();
        $this->due(2026, 'Ueberfaellig26');
        $this->due(2026, 'Markiert26', 'ueberfaellig');
        $this->due(2026, 'Bezahlt26', 'bezahlt');

        $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2026]->id.'&status=offen')
            ->assertOk()
            ->assertSee('>Ueberfaellig26 Beispiel</td>', false)
            ->assertSee('>Markiert26 Beispiel</td>', false)
            ->assertDontSee('>Bezahlt26 Beispiel</td>', false);
    }

    #[Test]
    public function pagination_links_keep_the_year_and_filters(): void
    {
        $this->seedYears();
        for ($i = 1; $i <= 27; $i++) {
            $this->due(2027, 'Mitglied'.$i);
        }

        $response = $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2027]->id.'&status=offen')
            ->assertOk();

        $response->assertSee('fiscal_year_id='.$this->years[2027]->id.'&amp;status=offen&amp;page=2', false);
    }

    #[Test]
    public function without_a_year_the_current_business_year_is_shown(): void
    {
        $this->seedYears();
        $this->due(2025, 'Alt');
        $this->due(2026, 'Aktuell');

        $response = $this->actingAs($this->admin())->get('/members/dues')->assertOk();

        $this->assertStringContainsString('value="'.$this->years[2026]->id.'" selected', $this->filterSelect($response));
        $response->assertSee('>Aktuell Beispiel</td>', false)->assertDontSee('>Alt Beispiel</td>', false);
    }

    #[Test]
    public function with_overlapping_years_the_most_recent_one_containing_today_is_the_default(): void
    {
        $this->category = FeeCategory::create(['name' => 'Erwachsene']);
        // A mis-entered older year that still covers today must not win over the newer one.
        $this->years[2025] = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2026-12-31']);
        $this->years[2026] = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

        $response = $this->actingAs($this->admin())->get('/members/dues')->assertOk();

        $this->assertStringContainsString('value="'.$this->years[2026]->id.'" selected', $this->filterSelect($response));
    }

    #[Test]
    public function the_last_day_of_a_year_still_belongs_to_it(): void
    {
        $this->seedYears();
        $this->travelTo('2026-12-31 18:00:00');

        $response = $this->actingAs($this->admin())->get('/members/dues')->assertOk();

        $this->assertStringContainsString('value="'.$this->years[2026]->id.'" selected', $this->filterSelect($response));
    }

    #[Test]
    public function when_no_year_covers_today_the_latest_year_with_dues_is_the_default(): void
    {
        $this->category = FeeCategory::create(['name' => 'Erwachsene']);
        $this->years[2024] = FiscalYear::create(['name' => '2024', 'starts_on' => '2024-01-01', 'ends_on' => '2024-12-31']);
        $this->years[2025] = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $this->years[2028] = FiscalYear::create(['name' => '2028', 'starts_on' => '2028-01-01', 'ends_on' => '2028-12-31']);
        $this->due(2024, 'Alt');
        $this->due(2025, 'Neuer');

        $response = $this->actingAs($this->admin())->get('/members/dues')->assertOk();

        $this->assertStringContainsString('value="'.$this->years[2025]->id.'" selected', $this->filterSelect($response));
    }

    #[Test]
    public function the_chosen_year_is_remembered_when_coming_back_without_a_year(): void
    {
        $this->seedYears();
        $this->due(2027, 'Zukunft');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/members/dues?fiscal_year_id='.$this->years[2027]->id)->assertOk();
        $this->get('/members/'.Member::first()->id.'/edit')->assertOk();

        $response = $this->get('/members/dues')->assertOk();
        $this->assertStringContainsString('value="'.$this->years[2027]->id.'" selected', $this->filterSelect($response));
        $response->assertSee('>Zukunft Beispiel</td>', false);
    }

    #[Test]
    public function marking_paid_redirects_back_to_the_same_year_and_filters(): void
    {
        $this->seedYears();
        $due = $this->due(2027, 'Zukunft');

        $this->actingAs($this->admin())
            ->from('/members/dues?fiscal_year_id='.$this->years[2027]->id.'&status=offen')
            ->post('/members/dues/bulk-mark-paid', ['due_ids' => [$due->id], 'fiscal_year_id' => $this->years[2027]->id])
            ->assertRedirect(route('members.dues.index', ['fiscal_year_id' => $this->years[2027]->id, 'status' => 'offen']));
    }

    #[Test]
    public function generating_redirects_to_the_generated_year(): void
    {
        $this->seedYears();

        $this->actingAs($this->admin())
            ->post('/members/dues/generate', ['fiscal_year_id' => $this->years[2027]->id])
            ->assertRedirect(route('members.dues.index', ['fiscal_year_id' => $this->years[2027]->id]));
    }

    #[Test]
    public function the_generate_select_follows_the_selected_year(): void
    {
        $this->seedYears();

        $response = $this->actingAs($this->admin())->get('/members/dues?fiscal_year_id='.$this->years[2027]->id)->assertOk();

        preg_match('/<select name="fiscal_year_id" id="due-generate-year".*?<\/select>/s', $response->getContent(), $m);
        $this->assertStringContainsString('value="'.$this->years[2027]->id.'" selected', $m[0] ?? '');
        $response->assertSee('Beiträge für 2027 generieren');
    }

    #[Test]
    public function a_year_without_dues_explains_that_none_were_generated_yet(): void
    {
        $this->seedYears();
        $this->due(2026, 'Aktuell');

        $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2027]->id)
            ->assertOk()
            ->assertSee('Für Geschäftsjahr 2027 sind noch keine Beiträge erzeugt.');

        $this->get('/members/dues?fiscal_year_id='.$this->years[2026]->id.'&status=bezahlt')
            ->assertOk()
            ->assertSee('Keine Beiträge für diese Filter.')
            ->assertDontSee('sind noch keine Beiträge erzeugt');
    }

    #[Test]
    public function the_empty_state_is_translated(): void
    {
        $this->seedYears();

        $this->actingAs($this->admin())
            ->withUnencryptedCookie('locale', 'en')
            ->get('/members/dues?fiscal_year_id='.$this->years[2027]->id)
            ->assertOk()
            ->assertSee('No dues have been generated for business year 2027 yet.');
    }

    #[Test]
    public function generating_zero_dues_because_of_missing_rates_says_so(): void
    {
        $this->seedYears();
        $this->due(2026, 'Aktuell');

        $this->actingAs($this->admin())
            ->post('/members/dues/generate', ['fiscal_year_id' => $this->years[2027]->id])
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'Für Geschäftsjahr 2027 sind keine Beitragssätze hinterlegt'));

        FeeCategoryRate::create(['fee_category_id' => $this->category->id, 'fiscal_year_id' => $this->years[2027]->id, 'amount' => 100]);
        $this->post('/members/dues/generate', ['fiscal_year_id' => $this->years[2027]->id])
            ->assertSessionHas('status', fn (string $s) => ! str_contains($s, 'keine Beitragssätze'));
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function oddYearInput(): array
    {
        return [
            'unknown id' => [['fiscal_year_id' => '999']],
            'text' => [['fiscal_year_id' => 'abc']],
            'array' => [['fiscal_year_id' => ['1']]],
            'nested array' => [['fiscal_year_id' => ['a' => ['b' => '2']]]],
            'negative' => [['fiscal_year_id' => '-1']],
        ];
    }

    /** @param array<string, mixed> $query */
    #[Test]
    #[DataProvider('oddYearInput')]
    public function odd_year_input_falls_back_to_the_default_with_a_notice(array $query): void
    {
        $this->seedYears();
        $this->due(2026, 'Aktuell');

        $response = $this->actingAs($this->admin())->get('/members/dues?'.http_build_query($query))->assertOk();

        $this->assertStringContainsString('value="'.$this->years[2026]->id.'" selected', $this->filterSelect($response));
        $response->assertSee('Das gewählte Geschäftsjahr gibt es nicht. Angezeigt wird 2026.')
            ->assertSee('>Aktuell Beispiel</td>', false);
    }

    #[Test]
    public function odd_status_or_search_input_never_breaks_the_list(): void
    {
        $this->seedYears();

        $this->actingAs($this->admin())
            ->get('/members/dues?'.http_build_query(['status' => ['offen'], 'search' => ['x'], 'page' => ['2']]))
            ->assertOk();
    }

    #[Test]
    public function the_member_page_preselects_the_current_year_for_a_new_due(): void
    {
        $this->seedYears();
        $member = Member::factory()->create();

        $response = $this->actingAs($this->admin())->get('/members/'.$member->id.'/edit')->assertOk();

        preg_match('/<select name="fiscal_year_id" id="md-fiscal-year".*?<\/select>/s', $response->getContent(), $m);
        $this->assertStringContainsString('value="'.$this->years[2026]->id.'" selected', $m[0] ?? '');
    }

    #[Test]
    public function the_dashboard_uses_the_same_default_year(): void
    {
        $this->category = FeeCategory::create(['name' => 'Erwachsene']);
        $this->years[2025] = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2026-12-31']);
        $this->years[2026] = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

        $this->actingAs($this->admin())->get('/members/dashboard')->assertOk()->assertSee('Beitrag 2026 offen');
    }
}
