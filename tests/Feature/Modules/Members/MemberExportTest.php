<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function export_streams_a_csv_with_all_members(): void
    {
        Member::factory()->create(['firstname' => 'Erika', 'lastname' => 'Musterfrau', 'email' => 'erika@example.com']);

        $response = $this->actingAs($this->admin())->get('/members/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Erika', $content);
        $this->assertStringContainsString('Musterfrau', $content);
        $this->assertStringContainsString('erika@example.com', $content);
    }

    #[Test]
    public function the_export_appends_household_and_payer_columns(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Beispiel']);
        $household = app(HouseholdService::class)->create([$anna->id, $ben->id], $family->id, 'Familie Muster');
        app(HouseholdService::class)->setPayer($household, $anna);

        $csv = $this->actingAs($this->admin())->get('/members/export')->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));

        $this->assertSame(['Vorname', 'Nachname', 'E-Mail', 'Eingetreten', 'Ausgetreten', 'Haushalt', 'Zahlende Person'], $lines[0]);
        $byName = collect($lines)->keyBy(fn ($line) => $line[0]);
        $this->assertSame(['Familie Muster', 'ja'], array_slice($byName['Anna'], 5));
        $this->assertSame(['Familie Muster', ''], array_slice($byName['Ben'], 5));
        $this->assertSame(['', ''], array_slice($byName['Dora'], 5));
    }
}
