<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FiscalYear;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fiscal years must not overlap: a day belongs to at most one year. Adjacent years
 * (end = the day before the next start) are fine; ending on the day another starts is an overlap.
 */
class FiscalYearOverlapTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const OVERLAP_2025 = 'Der Zeitraum überschneidet sich mit Geschäftsjahr 2025 (01.01.2025 – 31.12.2025).';

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function year2025(): FiscalYear
    {
        return FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
    }

    /** @return array<string, array{string, string}> */
    public static function overlappingRanges(): array
    {
        return [
            'same range' => ['2025-01-01', '2025-12-31'],
            'ends on the first day of the other' => ['2024-01-01', '2025-01-01'],
            'starts on the last day of the other' => ['2025-12-31', '2026-12-30'],
            'inside the other' => ['2025-03-01', '2025-04-30'],
            'around the other' => ['2024-06-01', '2026-06-30'],
            'tail overlap' => ['2025-09-01', '2026-08-31'],
            'single day inside' => ['2025-07-01', '2025-07-01'],
        ];
    }

    #[Test]
    #[DataProvider('overlappingRanges')]
    public function creating_an_overlapping_year_is_refused_naming_the_other_year(string $start, string $end): void
    {
        $this->year2025();

        $this->actingAs($this->admin())->from('/members/fiscal-years/create')->post('/members/fiscal-years', [
            'name' => 'Neu', 'starts_on' => $start, 'ends_on' => $end,
        ])->assertRedirect('/members/fiscal-years/create')
            ->assertSessionHasErrors(['starts_on' => self::OVERLAP_2025]);

        $this->assertDatabaseMissing('fiscal_years', ['name' => 'Neu']);
    }

    #[Test]
    public function the_overlap_message_is_shown_on_the_form_in_german_and_english(): void
    {
        $this->year2025();
        $admin = $this->admin();
        $data = ['name' => 'Neu', 'starts_on' => '2025-06-01', 'ends_on' => '2026-05-31'];

        $this->actingAs($admin)->from('/members/fiscal-years/create')->followingRedirects()
            ->post('/members/fiscal-years', $data)
            ->assertOk()->assertSee(self::OVERLAP_2025);

        $this->withUnencryptedCookie('locale', 'en')->from('/members/fiscal-years/create')->followingRedirects()
            ->post('/members/fiscal-years', $data)
            ->assertOk()->assertSee('The date range overlaps fiscal year 2025 (01/01/2025 – 12/31/2025).');
    }

    #[Test]
    public function adjacent_years_are_allowed_on_both_sides(): void
    {
        $this->year2025();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/members/fiscal-years', ['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])
            ->assertSessionHasNoErrors()->assertRedirect(route('members.fiscal-years.index'));
        $this->actingAs($admin)->post('/members/fiscal-years', ['name' => '2024', 'starts_on' => '2024-01-01', 'ends_on' => '2024-12-31'])
            ->assertSessionHasNoErrors()->assertRedirect(route('members.fiscal-years.index'));

        $this->assertSame(3, FiscalYear::query()->count());
    }

    #[Test]
    public function a_closed_year_counts_for_the_overlap_check(): void
    {
        $this->year2025()->close(1);

        $this->actingAs($this->admin())->post('/members/fiscal-years', ['name' => 'Neu', 'starts_on' => '2025-12-01', 'ends_on' => '2026-11-30'])
            ->assertSessionHasErrors(['starts_on' => self::OVERLAP_2025]);
        $this->assertDatabaseMissing('fiscal_years', ['name' => 'Neu']);
    }

    #[Test]
    public function editing_a_year_does_not_collide_with_itself(): void
    {
        $year = $this->year2025();

        $this->actingAs($this->admin())->put("/members/fiscal-years/{$year->id}", [
            'name' => '2025 (korrigiert)', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-30',
        ])->assertSessionHasNoErrors()->assertRedirect(route('members.fiscal-years.index'));

        $this->assertSame('2025 (korrigiert)', $year->fresh()->name);
        $this->assertSame('2025-12-30', $year->fresh()->ends_on->toDateString());
    }

    #[Test]
    public function editing_a_year_into_another_one_is_refused(): void
    {
        $this->year2025();
        $next = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

        $this->actingAs($this->admin())->from("/members/fiscal-years/{$next->id}/edit")->put("/members/fiscal-years/{$next->id}", [
            'name' => '2026', 'starts_on' => '2025-12-31', 'ends_on' => '2026-12-31',
        ])->assertRedirect("/members/fiscal-years/{$next->id}/edit")
            ->assertSessionHasErrors(['starts_on' => self::OVERLAP_2025]);

        $this->assertSame('2026-01-01', $next->fresh()->starts_on->toDateString());
    }

    #[Test]
    public function the_end_may_equal_the_start_but_not_lie_before_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/members/fiscal-years', ['name' => 'Ein Tag', 'starts_on' => '2030-05-05', 'ends_on' => '2030-05-05'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fiscal_years', ['name' => 'Ein Tag']);

        $this->actingAs($admin)->post('/members/fiscal-years', ['name' => 'Verkehrt', 'starts_on' => '2031-05-05', 'ends_on' => '2031-05-04'])
            ->assertSessionHasErrors(['ends_on' => 'Das Ende darf nicht vor dem Beginn liegen.']);
        $this->assertDatabaseMissing('fiscal_years', ['name' => 'Verkehrt']);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function oddInput(): array
    {
        return [
            'array start' => [['starts_on' => ['2025-01-01'], 'ends_on' => '2025-12-31']],
            'array end' => [['starts_on' => '2025-01-01', 'ends_on' => ['x' => '2025-12-31']]],
            'no date' => [['starts_on' => 'gestern', 'ends_on' => 'morgen']],
            'impossible date' => [['starts_on' => '2025-02-30', 'ends_on' => '2025-12-31']],
            'date with time' => [['starts_on' => '2026-01-01 10:00', 'ends_on' => '2026-12-31']],
            'empty' => [['starts_on' => '', 'ends_on' => '']],
            'missing' => [[]],
        ];
    }

    /** @param  array<string, mixed>  $dates */
    #[Test]
    #[DataProvider('oddInput')]
    public function odd_input_is_a_validation_error_never_a_500(array $dates): void
    {
        $year = $this->year2025();
        $admin = $this->admin();

        $create = $this->actingAs($admin)->from('/members/fiscal-years/create')->post('/members/fiscal-years', ['name' => 'Neu'] + $dates);
        $create->assertRedirect('/members/fiscal-years/create')->assertSessionHasErrors();
        $this->actingAs($admin)->get('/members/fiscal-years/create')->assertOk();

        $edit = $this->actingAs($admin)->from("/members/fiscal-years/{$year->id}/edit")->put("/members/fiscal-years/{$year->id}", ['name' => '2025'] + $dates);
        $edit->assertRedirect("/members/fiscal-years/{$year->id}/edit")->assertSessionHasErrors();
        $this->actingAs($admin)->get("/members/fiscal-years/{$year->id}/edit")->assertOk();

        $this->assertSame(1, FiscalYear::query()->count());
        $this->assertSame('2025-12-31', $year->fresh()->ends_on->toDateString());
    }
}
