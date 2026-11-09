<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberImportCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // A missing rate is no longer a zero-price tariff. These import scenarios use configured rates.
        foreach ([now()->year - 1, now()->year] as $year) {
            $fiscalYear = FiscalYear::create(['name' => (string) $year, 'starts_on' => "$year-01-01", 'ends_on' => "$year-12-31"]);
            foreach (['Vollzahler', 'Pensionisten'] as $name) {
                $category = FeeCategory::firstOrCreate(['name' => $name]);
                FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $fiscalYear->id, 'amount' => 120]);
            }
        }
    }

    private function writeCsv(): string
    {
        $currentYy = now()->year % 100;
        $lastYy = $currentYy - 1;

        $csv = <<<CSV
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Muster,Anna,Vollmitglied,bezahlt {$currentYy},,anna@example.com,Teststr. 1,4400,Teststadt,,0650 1234567,weiblich,01.02.1980,123456,46,Ja
        Alt,Bernd,Pensionist,bezahlt {$lastYy},,,Weg 2,4400,Teststadt,70 658,,maennlich,03.04.1950,,76,Nein
        CSV;

        $file = sys_get_temp_dir().'/members-import-test.csv';
        file_put_contents($file, $csv);

        return $file;
    }

    #[Test]
    public function imports_members_categories_and_paid_status_from_csv(): void
    {
        $this->artisan('members:import-csv', ['file' => $this->writeCsv()])->assertSuccessful();

        $currentYear = now()->year;
        $lastYear = $currentYear - 1;

        $anna = Member::where('lastname', 'Muster')->firstOrFail();
        $this->assertSame(1, $anna->member_number);
        $this->assertSame('Anna', $anna->firstname);
        $this->assertSame('1980-02-01', $anna->birthdate->toDateString());
        $this->assertSame('anna@example.com', $anna->email);
        $this->assertSame('0650 1234567', $anna->phone);
        $this->assertSame('Teststr. 1', $anna->street);
        $this->assertSame('4400', $anna->zip);
        $this->assertSame('Teststadt', $anna->city);
        $this->assertSame('w', $anna->gender);
        $this->assertSame(123456, $anna->nuliga_id);

        $bernd = Member::where('lastname', 'Alt')->firstOrFail();
        $this->assertSame(2, $bernd->member_number);
        $this->assertNull($bernd->email);
        $this->assertSame('70 658', $bernd->phone);
        $this->assertSame('m', $bernd->gender);
        $this->assertNull($bernd->nuliga_id);

        // Mitgliedsarten aus der CSV werden auf die Kategorienamen der App gemappt.
        $this->assertNotNull(FeeCategory::where('name', 'Vollzahler')->first());
        $this->assertNotNull(FeeCategory::where('name', 'Pensionisten')->first());
        $this->assertSame('Vollzahler', $anna->feeCategory->name);
        $this->assertSame('Pensionisten', $bernd->feeCategory->name);

        $currentFy = FiscalYear::where('name', (string) $currentYear)->firstOrFail();
        $lastFy = FiscalYear::where('name', (string) $lastYear)->firstOrFail();

        // Anna paid the current year: one due, marked paid.
        $annaDue = MemberDue::where('member_id', $anna->id)->where('fiscal_year_id', $currentFy->id)->firstOrFail();
        $this->assertTrue($annaDue->isPaid());
        $this->assertSame('Vollzahler', $annaDue->feeCategory->name);

        // Bernd paid last year: paid due last year, open due this year.
        $berndLast = MemberDue::where('member_id', $bernd->id)->where('fiscal_year_id', $lastFy->id)->firstOrFail();
        $this->assertTrue($berndLast->isPaid());
        $berndCurrent = MemberDue::where('member_id', $bernd->id)->where('fiscal_year_id', $currentFy->id)->firstOrFail();
        $this->assertFalse($berndCurrent->isPaid());
    }

    #[Test]
    public function rerunning_the_import_skips_existing_members(): void
    {
        $file = $this->writeCsv();

        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();
        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();

        $this->assertSame(2, Member::count());
        $this->assertSame(1, FeeCategory::where('name', 'Vollzahler')->count());
        $this->assertSame(3, MemberDue::count());
    }

    #[Test]
    public function rerun_generates_dues_for_existing_members(): void
    {
        $currentYear = now()->year;
        $currentYy = $currentYear % 100;

        $this->artisan('members:import-csv', ['file' => $this->writeCsv()])->assertSuccessful();

        // Nächster Export: Bernd hat inzwischen das aktuelle Jahr bezahlt.
        $csv = <<<CSV
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Alt,Bernd,Pensionist,bezahlt {$currentYy},,,Weg 2,4400,Teststadt,70 658,,maennlich,03.04.1950,,76,Nein
        CSV;
        $file = sys_get_temp_dir().'/members-import-test-rerun.csv';
        file_put_contents($file, $csv);

        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();

        $this->assertSame(2, Member::count());

        $bernd = Member::where('lastname', 'Alt')->firstOrFail();
        $currentFy = FiscalYear::where('name', (string) $currentYear)->firstOrFail();
        $due = MemberDue::where('member_id', $bernd->id)->where('fiscal_year_id', $currentFy->id)->firstOrFail();
        $this->assertTrue($due->isPaid());

        // Nochmaliger Lauf derselben Datei erzeugt keine doppelte Zahlung.
        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();
        $this->assertSame(1, $due->payments()->count());
    }

    #[Test]
    public function fails_for_missing_file(): void
    {
        $this->artisan('members:import-csv', ['file' => 'C:/does/not/exist.csv'])->assertFailed();
    }

    #[Test]
    public function rerun_backfills_missing_master_data_without_overwriting(): void
    {
        Member::factory()->create([
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'birthdate' => '1980-02-01',
            'gender' => 'm',
            'street' => 'Alte Str. 9',
            'nuliga_id' => null,
            'zip' => null,
            'city' => null,
            'fee_category_id' => null,
        ]);

        $this->artisan('members:import-csv', ['file' => $this->writeCsv()])->assertSuccessful();

        $this->assertSame(2, Member::count());

        $anna = Member::where('lastname', 'Muster')->firstOrFail();
        // Bereits gesetzte Werte bleiben unverändert.
        $this->assertSame('m', $anna->gender);
        $this->assertSame('Alte Str. 9', $anna->street);
        // Fehlende Werte werden aus der CSV nachgetragen.
        $this->assertSame(123456, $anna->nuliga_id);
        $this->assertSame('4400', $anna->zip);
        $this->assertSame('Teststadt', $anna->city);
        $this->assertNotNull($anna->fee_category_id);
        $this->assertSame('Vollzahler', $anna->feeCategory->name);
    }

    #[Test]
    public function warns_about_unparsable_nuliga_id(): void
    {
        $csv = <<<'CSV'
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Muster,Anna,Vollmitglied,bezahlt 25,,anna@example.com,Teststr. 1,4400,Teststadt,,0650 1234567,weiblich,01.02.1980,NU123456,46,Ja
        CSV;
        $file = sys_get_temp_dir().'/members-import-test-bad-nuliga.csv';
        file_put_contents($file, $csv);

        // Anna, Muster und NU123456 stammen alle aus derselben Warnzeile; expectsOutputToContain()
        // erfüllt pro Konsolenzeile nur die erste registrierte Erwartung, daher hier ein
        // einziger kombinierter Substring statt mehrerer einzelner Erwartungen.
        $this->artisan('members:import-csv', ['file' => $file])
            ->expectsOutputToContain('Anna Muster nicht lesbar: "NU123456"')
            ->assertSuccessful();

        $anna = Member::where('lastname', 'Muster')->firstOrFail();
        $this->assertNull($anna->nuliga_id);
    }

    #[Test]
    public function treats_a_zero_nuliga_id_as_missing_without_warning(): void
    {
        $csv = <<<'CSV'
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Muster,Anna,Vollmitglied,bezahlt 25,,anna@example.com,Teststr. 1,4400,Teststadt,,0650 1234567,weiblich,01.02.1980,0,46,Ja
        Alt,Bernd,Pensionist,bezahlt 25,,,Weg 2,4400,Teststadt,70 658,,maennlich,03.04.1950,0,76,Nein
        CSV;
        $file = sys_get_temp_dir().'/members-import-test-zero-nuliga.csv';
        file_put_contents($file, $csv);

        $this->artisan('members:import-csv', ['file' => $file])
            ->doesntExpectOutputToContain('Nuliga-ID')
            ->assertSuccessful();

        $this->assertNull(Member::where('lastname', 'Muster')->firstOrFail()->nuliga_id);
        $this->assertNull(Member::where('lastname', 'Alt')->firstOrFail()->nuliga_id);
    }

    #[Test]
    public function warns_about_duplicate_nuliga_id_and_keeps_first(): void
    {
        $csv = <<<'CSV'
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Muster,Anna,Vollmitglied,bezahlt 25,,anna@example.com,Teststr. 1,4400,Teststadt,,0650 1234567,weiblich,01.02.1980,123456,46,Ja
        Alt,Bernd,Pensionist,bezahlt 25,,,Weg 2,4400,Teststadt,70 658,,maennlich,03.04.1950,123456,76,Nein
        CSV;
        $file = sys_get_temp_dir().'/members-import-test-dup-nuliga.csv';
        file_put_contents($file, $csv);

        $this->artisan('members:import-csv', ['file' => $file])
            ->expectsOutputToContain('123456')
            ->assertSuccessful();

        $anna = Member::where('lastname', 'Muster')->firstOrFail();
        $bernd = Member::where('lastname', 'Alt')->firstOrFail();

        $this->assertSame(123456, $anna->nuliga_id);
        $this->assertNull($bernd->nuliga_id);
    }

    private function writeDeactivatedCategoryCsv(): string
    {
        $csv = <<<'CSV'
        Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem
        Muster,Anna,Vollmitglied,bezahlt 25,,anna@example.com,Teststr. 1,4400,Teststadt,,,weiblich,01.02.1980,,46,Ja
        Neu,Nora,Altkategorie,bezahlt 25,,nora@example.com,Weg 3,4400,Teststadt,,,weiblich,05.06.1990,222333,36,Nein
        Klein,Kurt,Kind,bezahlt 25,,,Weg 4,4400,Teststadt,,,maennlich,07.08.2015,,11,Nein
        Da,Dora,Altkategorie,bezahlt 25,,,Weg 5,4400,Teststadt,,,weiblich,09.10.1970,,56,Nein
        CSV;
        $file = sys_get_temp_dir().'/members-import-test-deactivated.csv';
        file_put_contents($file, $csv);

        return $file;
    }

    #[Test]
    public function rows_with_a_deactivated_category_are_skipped_and_counted(): void
    {
        FeeCategory::create(['name' => 'Altkategorie'])->deactivate();
        FeeCategory::create(['name' => 'Kinder, Schüler'])->deactivate(); // reached through the alias "Kind"
        $dora = Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Da', 'birthdate' => '1970-10-09', 'fee_category_id' => null, 'street' => null]);

        $this->artisan('members:import-csv', ['file' => $this->writeDeactivatedCategoryCsv()])
            ->expectsOutputToContain('Zeilen mit deaktivierter Beitragskategorie übersprungen: 3 (Altkategorie: 2, Kinder, Schüler: 1).')
            ->assertSuccessful();

        // Only Anna (active category) is imported; no member, due, payment or backfill for the others.
        $this->assertSame(2, Member::query()->count());
        $this->assertNotNull(Member::where('lastname', 'Muster')->first());
        $this->assertNull(Member::where('lastname', 'Neu')->first());
        $this->assertNull(Member::where('lastname', 'Klein')->first());
        $this->assertNull($dora->fresh()->fee_category_id);
        $this->assertNull($dora->fresh()->street);
        $this->assertSame(0, MemberDue::where('member_id', $dora->id)->count());
        $this->assertSame(0, MemberDue::query()->whereHas('feeCategory', fn ($query) => $query->whereNotNull('deactivated_at'))->count());
    }

    #[Test]
    public function the_summary_counts_exclude_skipped_rows(): void
    {
        FeeCategory::create(['name' => 'Altkategorie'])->deactivate();
        FeeCategory::create(['name' => 'Kinder, Schüler'])->deactivate();
        Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Da', 'birthdate' => '1970-10-09']);
        $dues = now()->year === 2025 ? 1 : 2;

        $this->artisan('members:import-csv', ['file' => $this->writeDeactivatedCategoryCsv()])
            ->expectsOutputToContain("Import abgeschlossen: 1 Mitglieder angelegt, 0 übersprungen (bereits vorhanden), 0 aktualisiert, {$dues} Vorschreibungen erstellt.")
            ->assertSuccessful();
    }

    #[Test]
    public function the_import_summary_is_translated(): void
    {
        FeeCategory::create(['name' => 'Altkategorie'])->deactivate();
        FeeCategory::create(['name' => 'Kinder, Schüler'])->deactivate();
        app()->setLocale('en');
        $dues = now()->year === 2025 ? 1 : 2;

        $this->artisan('members:import-csv', ['file' => $this->writeDeactivatedCategoryCsv()])
            ->expectsOutputToContain("Import finished: 1 members created, 0 skipped (already present), 0 updated, {$dues} dues created.")
            ->assertSuccessful();

        $this->artisan('members:import-csv', ['file' => $this->writeDeactivatedCategoryCsv()])
            ->expectsOutputToContain('Rows skipped because the fee category is deactivated: 3 (Altkategorie: 2, Kinder, Schüler: 1).')
            ->assertSuccessful();
    }

    #[Test]
    public function without_deactivated_categories_no_skip_line_is_printed(): void
    {
        $this->artisan('members:import-csv', ['file' => $this->writeCsv()])
            ->doesntExpectOutputToContain('deaktivierter Beitragskategorie')
            ->assertSuccessful();
    }
}
