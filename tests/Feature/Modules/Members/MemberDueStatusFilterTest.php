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
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decision: Mitglieder > Beiträge opens with status "Offen" (outstanding and overdue, i.e.
 * every due that is not paid); "Alle" must be chosen explicitly and then sticks in the URL.
 */
class MemberDueStatusFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<int, FiscalYear> */
    private array $years = [];

    private FeeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->category = FeeCategory::create(['name' => 'Erwachsene']);
        foreach ([2026, 2027] as $y) {
            $this->years[$y] = FiscalYear::create(['name' => (string) $y, 'starts_on' => "{$y}-01-01", 'ends_on' => "{$y}-12-31", 'faellig_am' => "{$y}-03-31"]);
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function due(int $year, string $firstname, array $attributes = []): MemberDue
    {
        $member = Member::factory()->create(['firstname' => $firstname, 'lastname' => 'Beispiel']);

        return MemberDue::create([
            'member_id' => $member->id, 'fiscal_year_id' => $this->years[$year]->id, 'fee_category_id' => $this->category->id,
            'amount' => 100, 'status' => 'ausstehend', 'faellig_am' => $this->years[$year]->faellig_am, ...$attributes,
        ]);
    }

    /** 2026: one overdue by date, one marked overdue, one not yet due, one paid, one settled by a payment. */
    private function seedYear2026(): void
    {
        $this->due(2026, 'UeberfaelligDatum');
        $this->due(2026, 'UeberfaelligMarkiert', ['status' => 'ueberfaellig']);
        $this->due(2026, 'AusstehendSpaeter', ['faellig_am' => '2026-12-15', 'teilbetrag' => 40]);
        $this->due(2026, 'Bezahlt', ['status' => 'bezahlt', 'bezahlt_am' => '2026-04-01']);
        $settled = $this->due(2026, 'MitZahlung');
        $settled->payments()->attach(Payment::create(['member_id' => $settled->member_id, 'amount' => 100, 'paid_at' => '2026-05-01']));
    }

    private function statusSelect(TestResponse $response): string
    {
        preg_match('/<select name="status" id="due-status".*?<\/select>/s', $response->getContent(), $m);

        return $m[0] ?? '';
    }

    /** @param list<string> $names */
    private function assertRows(TestResponse $response, array $names): void
    {
        foreach (['UeberfaelligDatum', 'UeberfaelligMarkiert', 'AusstehendSpaeter', 'Bezahlt', 'MitZahlung'] as $name) {
            in_array($name, $names, true)
                ? $response->assertSee('>'.$name.' Beispiel</td>', false)
                : $response->assertDontSee('>'.$name.' Beispiel</td>', false);
        }
    }

    #[Test]
    public function without_a_status_the_list_shows_open_dues_outstanding_and_overdue(): void
    {
        $this->seedYear2026();

        $response = $this->actingAs($this->admin())->get('/members/dues?fiscal_year_id='.$this->years[2026]->id)->assertOk();

        $this->assertRows($response, ['UeberfaelligDatum', 'UeberfaelligMarkiert', 'AusstehendSpaeter']);
        $this->assertStringContainsString('<option value="offen" selected>Offen</option>', $this->statusSelect($response));
    }

    #[Test]
    public function the_options_are_open_all_outstanding_overdue_paid_in_this_order(): void
    {
        $response = $this->actingAs($this->admin())->get('/members/dues')->assertOk();

        preg_match_all('/<option value="([^"]*)"[^>]*>([^<]*)<\/option>/', $this->statusSelect($response), $m);
        $this->assertSame(['offen', 'alle', 'ausstehend', 'ueberfaellig', 'bezahlt'], $m[1]);
        $this->assertSame(['Offen', 'Alle', 'Ausstehend', 'Überfällig', 'Bezahlt'], $m[2]);

        $en = $this->withUnencryptedCookie('locale', 'en')->get('/members/dues')->assertOk();
        preg_match_all('/<option value="([^"]*)"[^>]*>([^<]*)<\/option>/', $this->statusSelect($en), $m);
        $this->assertSame(['Open', 'All', 'Outstanding', 'Overdue', 'Paid'], $m[2]);
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function statusOptions(): array
    {
        return [
            'offen' => ['offen', ['UeberfaelligDatum', 'UeberfaelligMarkiert', 'AusstehendSpaeter']],
            'alle' => ['alle', ['UeberfaelligDatum', 'UeberfaelligMarkiert', 'AusstehendSpaeter', 'Bezahlt', 'MitZahlung']],
            'ausstehend' => ['ausstehend', ['AusstehendSpaeter']],
            'ueberfaellig' => ['ueberfaellig', ['UeberfaelligDatum', 'UeberfaelligMarkiert']],
            'bezahlt' => ['bezahlt', ['Bezahlt']],
        ];
    }

    /** @param list<string> $expected */
    #[Test]
    #[DataProvider('statusOptions')]
    public function each_option_lists_its_dues_and_stays_selected(string $status, array $expected): void
    {
        $this->seedYear2026();

        $response = $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2026]->id.'&status='.$status)
            ->assertOk();

        $this->assertRows($response, $expected);
        $this->assertStringContainsString('<option value="'.$status.'" selected>', $this->statusSelect($response));
    }

    #[Test]
    public function future_year_dues_not_yet_due_count_as_open(): void
    {
        $this->due(2027, 'Zukunft');
        $this->due(2027, 'ZukunftBezahlt', ['status' => 'bezahlt']);

        $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2027]->id)
            ->assertOk()
            ->assertSee('>Zukunft Beispiel</td>', false)
            ->assertDontSee('>ZukunftBezahlt Beispiel</td>', false);
    }

    #[Test]
    public function an_explicit_all_survives_a_year_change_and_search_and_paging(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->due(2027, 'Mitglied'.$i, ['status' => $i % 2 ? 'bezahlt' : 'ausstehend']);
        }

        // The filter form submits the year, the search and the status select together.
        $response = $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2027]->id.'&search=Mitglied&status=alle')
            ->assertOk();

        $this->assertStringContainsString('<option value="alle" selected>', $this->statusSelect($response));
        $response->assertSee('search=Mitglied&amp;status=alle&amp;page=2', false);
        $this->get('/members/dues?fiscal_year_id='.$this->years[2027]->id.'&search=Mitglied&status=alle&page=2')
            ->assertOk()
            ->assertSee('Beispiel</td>', false);
    }

    #[Test]
    public function the_default_open_filter_also_keeps_year_and_search_in_the_pager(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->due(2027, 'Mitglied'.$i);
        }

        $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2027]->id.'&search=Mitglied')
            ->assertOk()
            ->assertSee('fiscal_year_id='.$this->years[2027]->id.'&amp;search=Mitglied&amp;page=2', false);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function oddStatus(): array
    {
        return [
            'unknown' => [['status' => 'irgendwas']],
            'array' => [['status' => ['alle']]],
            'nested' => [['status' => ['a' => ['b']]]],
            'empty' => [['status' => '']],
        ];
    }

    /** @param array<string, mixed> $query */
    #[Test]
    #[DataProvider('oddStatus')]
    public function odd_status_input_falls_back_to_open_without_error(array $query): void
    {
        $this->seedYear2026();

        $response = $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2026]->id.'&'.http_build_query($query))
            ->assertOk();

        $this->assertRows($response, ['UeberfaelligDatum', 'UeberfaelligMarkiert', 'AusstehendSpaeter']);
        $this->assertStringContainsString('<option value="offen" selected>', $this->statusSelect($response));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function totals(): array
    {
        return [
            'offen (default)' => ['', '260,00 €'],
            'alle' => ['alle', '260,00 €'],
            'ausstehend' => ['ausstehend', '60,00 €'],
            'ueberfaellig' => ['ueberfaellig', '200,00 €'],
            'bezahlt' => ['bezahlt', '0,00 €'],
        ];
    }

    #[Test]
    #[DataProvider('totals')]
    public function the_outstanding_total_follows_the_filter(string $status, string $total): void
    {
        $this->seedYear2026();

        $html = $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2026]->id.($status !== '' ? '&status='.$status : ''))
            ->assertOk()
            ->getContent();

        preg_match('/data-testid="dues-outstanding-total">.*?<strong[^>]*>([^<]*)<\/strong>/s', $html, $m);
        $this->assertSame($total, html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5));
    }

    #[Test]
    public function the_paid_filter_keeps_the_bezahlt_am_column(): void
    {
        $this->seedYear2026();

        $this->actingAs($this->admin())
            ->get('/members/dues?fiscal_year_id='.$this->years[2026]->id.'&status=bezahlt')
            ->assertOk()
            ->assertSee('>Bezahlt am</th>', false)
            ->assertSee('01.04.2026');
    }

    #[Test]
    public function the_query_count_does_not_grow_with_the_rows(): void
    {
        $admin = $this->admin();
        $this->due(2026, 'Eins');
        $count = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get('/members/dues?fiscal_year_id='.$this->years[2026]->id)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $few = $count();
        for ($i = 0; $i < 8; $i++) {
            $this->due(2026, 'Mehr'.$i, ['teilbetrag' => 10]);
        }

        $this->assertSame($few, $count());
    }
}
