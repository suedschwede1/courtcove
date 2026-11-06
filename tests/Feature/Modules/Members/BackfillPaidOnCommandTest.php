<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * members:backfill-paid-at fills "Bezahlt am" of paid dues that have no date yet from the one
 * payment linked to them. It never overwrites a date and skips every ambiguous case.
 */
class BackfillPaidOnCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<string, MemberDue> */
    private array $dues = [];

    private function seedCases(): void
    {
        $category = FeeCategory::create(['name' => 'Erwachsene']);
        $years = [];
        foreach (['2023', '2024', '2025', '2026'] as $y) {
            $years[] = FiscalYear::create(['name' => $y, 'starts_on' => "{$y}-01-01", 'ends_on' => "{$y}-12-31"]);
        }

        $make = function (string $key, array $attributes, array $payments) use ($category, $years): void {
            $member = Member::factory()->create();
            $due = MemberDue::create([
                'member_id' => $member->id, 'fiscal_year_id' => $years[0]->id, 'fee_category_id' => $category->id,
                'amount' => 100, ...$attributes,
            ]);
            foreach ($payments as [$date, $note]) {
                $due->payments()->attach(Payment::create(['member_id' => $member->id, 'amount' => 100, 'paid_at' => $date, 'note' => $note]));
            }
            $this->dues[$key] = $due;
        };

        $make('fillable', ['status' => 'bezahlt'], [['2025-05-04', null]]);
        $make('fillable_with_note', ['status' => 'bezahlt'], [['2025-06-01', 'Überweisung']]);
        $make('already_dated', ['status' => 'bezahlt', 'bezahlt_am' => '2025-01-15'], [['2025-05-04', null]]);
        $make('no_payment', ['status' => 'bezahlt'], []);
        $make('two_payments', ['status' => 'bezahlt'], [['2025-05-04', null], ['2025-06-04', null]]);
        $make('import_placeholder', ['status' => 'bezahlt'], [['2025-01-01', 'CSV-Import: bezahlt 25']]);
        $make('reopened', ['status' => 'ausstehend'], [['2025-05-04', null]]);
        $make('open', ['status' => 'ausstehend'], []);
    }

    /** @return array<string, string|null> */
    private function snapshot(): array
    {
        return array_map(fn (MemberDue $due) => $due->fresh()->bezahlt_am?->toDateString(), $this->dues);
    }

    #[Test]
    public function dry_run_reports_counts_and_writes_nothing(): void
    {
        $this->seedCases();
        $before = $this->snapshot();
        $updatedAt = MemberDue::query()->pluck('updated_at', 'id')->map(fn ($at) => (string) $at)->all();

        $this->artisan('members:backfill-paid-at', ['--dry-run' => true])
            ->expectsOutputToContain('2 Beiträge würden')
            ->expectsOutputToContain('ohne Zahlung: 1')
            ->expectsOutputToContain('mehrere Zahlungen: 1')
            ->expectsOutputToContain('CSV-Import-Platzhalter: 1')
            ->expectsOutputToContain('nicht als bezahlt markiert: 1')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame($updatedAt, MemberDue::query()->pluck('updated_at', 'id')->map(fn ($at) => (string) $at)->all());
    }

    #[Test]
    public function real_run_fills_only_the_unambiguous_rows(): void
    {
        $this->seedCases();

        $this->artisan('members:backfill-paid-at')
            ->expectsOutputToContain('2 Beiträge aktualisiert')
            ->assertSuccessful();

        $this->assertSame([
            'fillable' => '2025-05-04',
            'fillable_with_note' => '2025-06-01',
            'already_dated' => '2025-01-15',
            'no_payment' => null,
            'two_payments' => null,
            'import_placeholder' => null,
            'reopened' => null,
            'open' => null,
        ], $this->snapshot());
        $this->assertSame('bezahlt', $this->dues['fillable']->fresh()->status);
        $this->assertSame('ausstehend', $this->dues['reopened']->fresh()->status);
    }

    #[Test]
    public function a_second_run_changes_nothing(): void
    {
        $this->seedCases();
        $this->artisan('members:backfill-paid-at')->assertSuccessful();
        $after = $this->snapshot();

        $this->artisan('members:backfill-paid-at')
            ->expectsOutputToContain('0 Beiträge aktualisiert')
            ->assertSuccessful();

        $this->assertSame($after, $this->snapshot());
    }
}
